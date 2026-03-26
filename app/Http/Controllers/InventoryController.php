<?php

namespace App\Http\Controllers;

use App\Models\EventItem;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user->canManageInventory()) {
            abort(403, 'Unauthorized action.');
        }

        // Optional: refresh inventory availability based on currently unreturned items (real-time)
        if (request()->boolean('refresh')) {
            // Only admins and Head Maintenance can refresh
            if (!in_array($user->role, ['admin', 'Head Maintenance'])) {
                abort(403, 'Unauthorized action.');
            }

            DB::beginTransaction();
            try {
                $items = InventoryItem::query()->get();

                foreach ($items as $item) {
                    $borrowedQty = EventItem::query()
                        ->unreturned()
                        ->where('inventory_item_id', $item->id)
                        ->sum('quantity_approved');

                    $newAvailable = max(0, (int)$item->quantity_total - (int)$borrowedQty);

                    // Keep current status but sync availability
                    if ($item->quantity_available !== $newAvailable) {
                        $item->update(['quantity_available' => $newAvailable]);
                    }
                }

                DB::commit();

                return redirect()
                    ->route('inventory.index')
                    ->with('success', 'Inventory refreshed successfully. Availability now reflects currently borrowed items.');
            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('Failed to refresh inventory availability', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);

                return redirect()
                    ->route('inventory.index')
                    ->with('error', 'Failed to refresh inventory. Please try again.');
            }
        }

        // Get base query
        $query = InventoryItem::with('eventItems.event')
            ->orderBy('category', 'asc')
            ->orderBy('name', 'asc');

        // Filter by allowed categories if user has restricted access
        $allowedCategories = $user->getAllowedInventoryCategories();
        if (!empty($allowedCategories)) {
            $query->whereIn('category', $allowedCategories);
        }

        $inventoryItems = $query->get()
            ->groupBy(function($item) {
                // Normalize category to lowercase for case-insensitive grouping
                return strtolower(trim($item->category));
            })
            ->mapWithKeys(function($items, $normalizedCategory) {
                // Use the first item's original category name as the display key
                $displayCategory = $items->first()->category;
                return [$displayCategory => $items->values()];
            });

        $borrowingQuery = EventItem::query()
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->where('quantity_requested', '>', 0);
            });

        // Keep export consistent with the inventory categories user can access
        if (!empty($allowedCategories)) {
            $borrowingQuery->whereHas('inventoryItem', function ($q) use ($allowedCategories) {
                $q->whereIn('category', $allowedCategories);
            });
        }

        $hasBorrowingRecords = $borrowingQuery->exists();

        return view('inventory.index', compact('inventoryItems', 'hasBorrowingRecords'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        
        if (!$user->canManageInventory()) {
            abort(403, 'Unauthorized action.');
        }

        // Users with restricted categories cannot create new items (only admin/staff/Head Maintenance can)
        if (!empty($user->getAllowedInventoryCategories())) {
            abort(403, 'You do not have permission to create new inventory items.');
        }

        return view('inventory.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->canManageInventory()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'quantity_total' => 'required|integer|min:0',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            $inventoryItem = InventoryItem::create([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'quantity_total' => $request->quantity_total,
                'quantity_available' => $request->quantity_total,
                'location' => $request->location,
                'notes' => $request->notes,
            ]);

            DB::commit();

            Log::info('Inventory item created', [
                'item_id' => $inventoryItem->id,
                'name' => $inventoryItem->name,
                'created_by' => $user->id,
            ]);

            return redirect()->route('inventory.index')
                ->with('success', 'Inventory item "' . $inventoryItem->name . '" has been saved successfully to the database.');
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create inventory item', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'input' => $request->only(['name', 'category', 'quantity_total']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to save inventory item: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryItem $inventoryItem)
    {
        $user = Auth::user();
        
        if (!$user->canManageInventory()) {
            abort(403, 'Unauthorized action.');
        }

        // Check if user can access this category
        if (!$user->canAccessInventoryCategory($inventoryItem->category)) {
            abort(403, 'You do not have permission to access items in this category.');
        }

        $inventoryItem->load('eventItems.event.creator');
        
        return view('inventory.show', compact('inventoryItem'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InventoryItem $inventoryItem)
    {
        $user = Auth::user();
        
        if (!$user->canManageInventory()) {
            abort(403, 'Unauthorized action.');
        }

        // Check if user can access this category
        if (!$user->canAccessInventoryCategory($inventoryItem->category)) {
            abort(403, 'You do not have permission to edit items in this category.');
        }

        return view('inventory.edit', compact('inventoryItem'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $user = Auth::user();
        
        if (!$user->canManageInventory()) {
            abort(403, 'Unauthorized action.');
        }

        // Check if user can access the current category
        if (!$user->canAccessInventoryCategory($inventoryItem->category)) {
            abort(403, 'You do not have permission to edit items in this category.');
        }

        // If category is being changed, check if user can access the new category
        if ($request->category !== $inventoryItem->category && 
            !$user->canAccessInventoryCategory($request->category)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'You do not have permission to move items to the category "' . $request->category . '".');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'quantity_total' => 'required|integer|min:0',
            'quantity_available' => 'required|integer|min:0',
            'status' => 'required|in:available,maintenance,unavailable',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Ensure quantity_available doesn't exceed quantity_total
        $quantityAvailable = min($request->quantity_available, $request->quantity_total);

        DB::beginTransaction();
        
        try {
            $inventoryItem->update([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'quantity_total' => $request->quantity_total,
                'quantity_available' => $quantityAvailable,
                'status' => $request->status,
                'location' => $request->location,
                'notes' => $request->notes,
            ]);

            DB::commit();

            Log::info('Inventory item updated', [
                'item_id' => $inventoryItem->id,
                'name' => $inventoryItem->name,
                'updated_by' => $user->id,
            ]);

            return redirect()->route('inventory.index')
                ->with('success', 'Inventory item "' . $inventoryItem->name . '" has been saved successfully to the database.');
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update inventory item', [
                'error' => $e->getMessage(),
                'item_id' => $inventoryItem->id,
                'user_id' => $user->id,
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to save changes: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing a category.
     */
    public function editCategory($category)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['admin', 'Head Maintenance'])) {
            abort(403, 'Unauthorized action.');
        }

        $category = urldecode($category);
        
        // Get all items in this category
        $items = InventoryItem::where('category', $category)->get();
        
        if ($items->isEmpty()) {
            return redirect()->route('inventory.index')
                ->with('error', 'Category not found.');
        }

        return view('inventory.edit-category', compact('category', 'items'));
    }

    /**
     * Update a category name for all items in that category.
     */
    public function updateCategory(Request $request, $category)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['admin', 'Head Maintenance'])) {
            abort(403, 'Unauthorized action.');
        }

        $oldCategory = urldecode($category);
        
        $request->validate([
            'new_category' => 'required|string|max:255',
        ]);

        $newCategory = trim($request->new_category);

        if (empty($newCategory)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Category name cannot be empty.');
        }

        // Check if any items exist in the old category
        $items = InventoryItem::where('category', $oldCategory)->get();
        
        if ($items->isEmpty()) {
            return redirect()->route('inventory.index')
                ->with('error', 'Category not found.');
        }

        DB::beginTransaction();
        
        try {
            // Update all items in the category
            $updatedCount = InventoryItem::where('category', $oldCategory)
                ->update(['category' => $newCategory]);

            DB::commit();

            Log::info('Category renamed', [
                'old_category' => $oldCategory,
                'new_category' => $newCategory,
                'items_updated' => $updatedCount,
                'updated_by' => $user->id,
            ]);

            return redirect()->route('inventory.index')
                ->with('success', 'Category "' . $oldCategory . '" has been renamed to "' . $newCategory . '" (' . $updatedCount . ' items updated).');
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update category', [
                'error' => $e->getMessage(),
                'old_category' => $oldCategory,
                'new_category' => $newCategory,
                'user_id' => $user->id,
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update category: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryItem $inventoryItem)
    {
        $user = Auth::user();
        
        if (!$user->canManageInventory()) {
            abort(403, 'Unauthorized action.');
        }

        // Block delete only if the item is currently borrowed (unreturned)
        $isCurrentlyBorrowed = EventItem::query()
            ->unreturned()
            ->where('inventory_item_id', $inventoryItem->id)
            ->exists();

        if ($isCurrentlyBorrowed) {
            return redirect()->back()
                ->with('error', 'Cannot delete this inventory item because it is currently borrowed (not yet returned).');
        }

        $inventoryItem->delete();
        
        return redirect()->route('inventory.index')
            ->with('success', 'Inventory item deleted successfully.');
    }

    /**
     * Export approved inventory borrowing records to an Excel-compatible file.
     */
    public function exportBorrowingRecords(Request $request): StreamedResponse
    {
        $user = Auth::user();

        if (!$user->canManageInventory()) {
            abort(403, 'Unauthorized action.');
        }

        $allowedCategories = $user->getAllowedInventoryCategories();

        $eventItems = EventItem::query()
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->where('quantity_requested', '>', 0);
            })
            ->with([
                'inventoryItem',
                'event.creator',
            ])
            ->when(!empty($allowedCategories), function ($q) use ($allowedCategories) {
                $q->whereHas('inventoryItem', function ($subQuery) use ($allowedCategories) {
                    $subQuery->whereIn('category', $allowedCategories);
                });
            })
            ->orderByDesc('created_at')
            ->get();

        if ($eventItems->isEmpty()) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'No borrowing records found to export.');
        }

        $filename = 'inventory-borrowing-records-' . now()->format('Ymd_His') . '.xls';

        return response()->streamDownload(function () use ($eventItems) {
            $output = fopen('php://output', 'w');
            $escape = static function ($value): string {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            };

            fwrite($output, "<?xml version=\"1.0\"?>\n");
            fwrite($output, "<?mso-application progid=\"Excel.Sheet\"?>\n");
            fwrite($output, "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n");
            fwrite($output, "<Worksheet ss:Name=\"Inventory Borrowing Records\">\n<Table>\n");

            $headers = [
                'Inventory Item Name',
                'Users Department',
                'Quantity Requested',
                'Event Title',
                'Created At',
                'Returned At',
            ];

            fwrite($output, "<Row>");
            foreach ($headers as $header) {
                fwrite($output, "<Cell><Data ss:Type=\"String\">" . $escape($header) . "</Data></Cell>");
            }
            fwrite($output, "</Row>\n");

            foreach ($eventItems as $eventItem) {
                $itemName = $eventItem->inventoryItem->name ?? '';
                $department = $eventItem->event->creator->department ?? '';
                $quantityRequested = (int) ($eventItem->quantity_requested ?? 0);
                $quantity = $quantityRequested;
                $eventTitle = $eventItem->event->title ?? '';

                $createdAt = $this->safeDateTime($eventItem->created_at);
                $returnedAt = $eventItem->returned_at
                    ? $this->safeDateTime($eventItem->returned_at)
                    : 'Not yet returned';

                $row = [
                    $itemName,
                    $department,
                    (string) $quantity,
                    $eventTitle,
                    $createdAt,
                    $returnedAt,
                ];

                fwrite($output, "<Row>");
                foreach ($row as $value) {
                    fwrite($output, "<Cell><Data ss:Type=\"String\">" . $escape($value) . "</Data></Cell>");
                }
                fwrite($output, "</Row>\n");
            }

            fwrite($output, "</Table>\n</Worksheet>\n</Workbook>");
            fclose($output);
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
    }

    private function safeDateTime($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
