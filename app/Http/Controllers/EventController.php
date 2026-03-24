<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventHistory;
use App\Models\EventItem;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
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
        
        // Get all future events (approved, pending, and rejected) so they all show up
        // Admin-created approved events should appear immediately
        // Head-created pending events should appear for admin approval
        $events = Event::with(['creator', 'approver', 'eventItems.inventoryItem'])
            ->where('event_date', '>=', now()->toDateString()) // Future events only
            ->orderBy('event_date', 'asc')
            ->orderBy('created_at', 'desc') // Show newest events first for same date
            ->get();

        return view('events.index', compact('events'));
    }

    /**
     * Display archived event actions.
     */
    public function history()
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $filters = $this->extractHistoryFilters(request());
        $allHistory = $this->buildAllHistoryCollection();
        $filtered = $this->applyHistoryFilters($allHistory, $filters);

        // Manual pagination on filtered results
        $page = request()->get('page', 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $items = $filtered->slice($offset, $perPage)->values();
        $total = $filtered->count();

        // Create a paginator-like object
        $history = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $departments = \App\Models\User::whereNotNull('department')
            ->where('department', '!=', '')
            ->orderBy('department')
            ->pluck('department')
            ->unique()
            ->values();

        return view('events.history', compact('history', 'departments', 'filters'));
    }

    /**
     * Export archived event history based on active filters.
     */
    public function exportHistory(Request $request): StreamedResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $filters = $this->extractHistoryFilters($request);
        $allHistory = $this->buildAllHistoryCollection();
        $filtered = $this->applyHistoryFilters($allHistory, $filters);

        if ($filtered->isEmpty()) {
            return redirect()
                ->route('events.history', $filters)
                ->with('error', 'No history records found for the selected filters.');
        }

        $filename = 'event-history-' . now()->format('Ymd_His') . '.xls';

        return response()->streamDownload(function () use ($filtered) {
            $output = fopen('php://output', 'w');
            $escape = static function ($value): string {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            };

            fwrite($output, "<?xml version=\"1.0\"?>\n");
            fwrite($output, "<?mso-application progid=\"Excel.Sheet\"?>\n");
            fwrite($output, "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n");
            fwrite($output, "<Worksheet ss:Name=\"Event History\">\n<Table>\n");

            $headers = [
                'Reference #',
                'Event Name',
                'Date',
                'Status',
                'Department',
                'Created By',
                'Performed By',
                'Action',
                'Performed At',
                'Location',
                'Start Time',
                'End Time',
            ];
            fwrite($output, "<Row>");
            foreach ($headers as $header) {
                fwrite($output, "<Cell><Data ss:Type=\"String\">" . $escape($header) . "</Data></Cell>");
            }
            fwrite($output, "</Row>\n");

            foreach ($filtered as $entry) {
                $eventData = is_array($entry->event_data ?? null) ? $entry->event_data : [];
                $creatorName = $entry->creator->name ?? (isset($eventData['created_by']) ? 'User #' . $eventData['created_by'] : '');
                $department = $entry->creator->department ?? '';
                $performedBy = $entry->approver->name
                    ?? $entry->performedBy->name
                    ?? (isset($eventData['approved_by']) ? 'User #' . $eventData['approved_by'] : 'System');
                $row = [
                    $entry->event_id ?? '',
                    $entry->title ?? '',
                    $this->safeDate($entry->event_date),
                    ucfirst((string) ($entry->status ?? '')),
                    $department,
                    $creatorName,
                    $performedBy,
                    str_replace('_', ' ', (string) ($entry->action ?? '')),
                    $this->safeDateTime($entry->performed_at),
                    $eventData['location'] ?? '',
                    $this->safeTime($eventData['start_time'] ?? null),
                    $this->safeTime($eventData['end_time'] ?? null),
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

    private function extractHistoryFilters(Request $request): array
    {
        return [
            'search' => (string) $request->input('search', ''),
            'event_date' => (string) $request->input('event_date', ''),
            'status' => (string) $request->input('status', ''),
            'department' => (string) $request->input('department', ''),
        ];
    }

    private function buildAllHistoryCollection()
    {
        $finishedEvents = Event::with(['creator', 'approver'])
            ->where('event_date', '<', now()->toDateString())
            ->whereIn('status', ['approved', 'rejected'])
            ->orderByDesc('event_date')
            ->orderByDesc('created_at')
            ->get();

        $historyRecords = EventHistory::with('performedBy')
            ->orderByDesc('performed_at')
            ->orderByDesc('created_at')
            ->get();

        $creatorIds = $historyRecords->pluck('event_data')->filter()->map(function ($data) {
            return is_array($data) && isset($data['created_by']) ? $data['created_by'] : null;
        })->filter()->unique();

        $approverIds = $historyRecords->pluck('event_data')->filter()->map(function ($data) {
            return is_array($data) && isset($data['approved_by']) ? $data['approved_by'] : null;
        })->filter()->unique();

        $allUserIds = $creatorIds->merge($approverIds)->unique();
        $users = \App\Models\User::whereIn('id', $allUserIds)->get()->keyBy('id');

        $historyRecords = $historyRecords->map(function ($record) use ($users) {
            $eventData = $record->event_data;
            if (is_array($eventData)) {
                if (isset($eventData['created_by']) && $users->has($eventData['created_by'])) {
                    $record->creator = $users->get($eventData['created_by']);
                }
                if (isset($eventData['approved_by']) && $users->has($eventData['approved_by'])) {
                    $record->approver = $users->get($eventData['approved_by']);
                }
            }
            return $record;
        });

        $finishedEventsHistory = $finishedEvents->map(function ($event) {
            return (object) [
                'id' => 'event_' . $event->id,
                'event_id' => $event->id,
                'action' => $event->status === 'approved' ? 'approved' : 'rejected',
                'title' => $event->title,
                'status' => $event->status,
                'event_date' => $event->event_date,
                'performed_at' => $event->approved_at ?? $event->updated_at,
                'performed_by' => $event->approved_by,
                'created_by' => $event->created_by,
                'performedBy' => $event->approver,
                'creator' => $event->creator,
                'is_finished_event' => true,
                'event_data' => $event->toArray(),
            ];
        });

        return $historyRecords->concat($finishedEventsHistory)
            ->sortByDesc(function ($item) {
                return $item->performed_at ?? $item->event_date;
            })
            ->values();
    }

    private function applyHistoryFilters($allHistory, array $filters)
    {
        return $allHistory->filter(function ($item) use ($filters) {
            if ($filters['search'] !== '' && stripos($item->title ?? '', $filters['search']) === false) {
                return false;
            }

            if ($filters['event_date'] !== '') {
                $itemDate = $item->event_date instanceof \DateTimeInterface
                    ? $item->event_date->format('Y-m-d')
                    : (\Illuminate\Support\Carbon::parse($item->event_date)->format('Y-m-d') ?? null);
                if ($itemDate !== $filters['event_date']) {
                    return false;
                }
            }

            if ($filters['status'] !== '' && ($item->status ?? '') !== $filters['status']) {
                return false;
            }

            if ($filters['department'] !== '') {
                $creator = $item->creator ?? null;
                $creatorDepartment = $creator && is_object($creator) ? ($creator->department ?? null) : null;
                if ($creatorDepartment !== $filters['department']) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function safeDate($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function safeDateTime($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function safeTime($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        
        if (!$user->canCreateEvents()) {
            abort(403, 'Unauthorized action.');
        }

        $inventoryItems = InventoryItem::available()
            ->orderBy('category', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->groupBy(function($item) {
                // Normalize category to lowercase for case-insensitive grouping
                return strtolower(trim($item->category));
            })
            ->mapWithKeys(function($items, $normalizedCategory) {
                // Use the first item's original category name as the display key
                $displayCategory = $items->first()->category;
                return [$displayCategory => $items->values()];
            });

        return view('events.create', compact('inventoryItems'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->canCreateEvents()) {
            Log::warning('Unauthorized event creation attempt', ['user_id' => $user->id, 'user_role' => $user->role]);
            abort(403, 'Unauthorized action.');
        }

        // Normalize inventory items: keep only items with a quantity (i.e. items the user actually selected)
        $rawItems = $request->input('inventory_items', []);
        if (is_array($rawItems)) {
            $filteredItems = array_filter($rawItems, function ($item) {
                return is_array($item)
                    && isset($item['quantity'])
                    && $item['quantity'] !== null
                    && $item['quantity'] !== '';
            });
            $request->merge(['inventory_items' => $filteredItems]);
        }

        // Validate the incoming request
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date|after_or_equal:' . now()->toDateString(),
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'location' => 'required|string|max:255',
            'inventory_items' => 'nullable|array',
            'inventory_items.*.id' => 'required|exists:inventory_items,id',
            'inventory_items.*.quantity' => 'required|integer|min:1',
        ], [
            'title.required' => 'The event title is required.',
            'event_date.required' => 'The event date is required.',
            'event_date.after_or_equal' => 'The event date must be today or in the future.',
            'start_time.required' => 'The start time is required.',
            'end_time.required' => 'The end time is required.',
            'end_time.after' => 'The end time must be after the start time.',
            'location.required' => 'The location is required.',
        ]);

        Log::info('Event creation attempt', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'title' => $validated['title'],
            'event_date' => $validated['event_date'],
        ]);

        // Check for date/time conflicts with approved events
        $conflictingEvent = Event::approved()
            ->whereDate('event_date', $validated['event_date'])
            ->where(function($query) use ($validated) {
                $query->where(function($q) use ($validated) {
                    // Check if new event starts during an existing event
                    $q->where('start_time', '<=', $validated['start_time'])
                      ->where('end_time', '>', $validated['start_time']);
                })->orWhere(function($q) use ($validated) {
                    // Check if new event ends during an existing event
                    $q->where('start_time', '<', $validated['end_time'])
                      ->where('end_time', '>=', $validated['end_time']);
                })->orWhere(function($q) use ($validated) {
                    // Check if new event completely contains an existing event
                    $q->where('start_time', '>=', $validated['start_time'])
                      ->where('end_time', '<=', $validated['end_time']);
                });
            })
            ->first();

        if ($conflictingEvent) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'The selected date and time conflicts with an existing approved event: "' . $conflictingEvent->title . '" on ' . $conflictingEvent->event_date->format('M d, Y') . ' from ' . \Carbon\Carbon::parse($conflictingEvent->start_time)->format('g:i A') . ' to ' . \Carbon\Carbon::parse($conflictingEvent->end_time)->format('g:i A') . '.');
        }

        DB::beginTransaction();
        
        try {
            // Set status based on who creates the event
            // Admin events are auto-approved, Head events need admin approval
            $status = $user->isAdmin() ? 'approved' : 'pending';
            $approved_by = $user->isAdmin() ? $user->id : null;
            $approved_at = $user->isAdmin() ? now() : null;

            $event = Event::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'event_date' => $validated['event_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'location' => $validated['location'],
                'status' => $status,
                'created_by' => $user->id,
                'approved_by' => $approved_by,
                'approved_at' => $approved_at,
            ]);

            Log::info('Event created successfully', [
                'event_id' => $event->id,
                'title' => $event->title,
                'created_by' => $user->id,
                'status' => $status,
            ]);

            // Handle inventory items if provided
            if ($request->has('inventory_items') && is_array($request->inventory_items) && count($request->inventory_items) > 0) {
                foreach ($request->inventory_items as $itemId => $itemData) {
                    // Handle both array formats: [id => [id => x, quantity => y]] and [id => [quantity => y]]
                    $inventoryItemId = is_array($itemData) && isset($itemData['id']) ? $itemData['id'] : $itemId;
                    $quantity = is_array($itemData) && isset($itemData['quantity']) ? $itemData['quantity'] : $itemData;
                    $notes = is_array($itemData) && isset($itemData['notes']) ? $itemData['notes'] : null;
                    
                    if (is_numeric($inventoryItemId) && is_numeric($quantity) && $quantity > 0) {
                        $inventoryItem = InventoryItem::find($inventoryItemId);
                        $availableQuantity = $inventoryItem ? $inventoryItem->getAvailableQuantityForDate($validated['event_date']) : 0;
                        $quantityApproved = min($quantity, $availableQuantity);
                        
                        $eventItem = $event->eventItems()->create([
                            'inventory_item_id' => $inventoryItemId,
                            'quantity_requested' => $quantity,
                            'quantity_approved' => $status === 'approved' ? $quantityApproved : 0,
                            'status' => $status === 'approved' && $quantityApproved > 0 ? 'approved' : 'pending',
                            'notes' => $notes,
                        ]);
                        
                        // If admin and auto-approved, decrease inventory quantity
                        if ($status === 'approved' && $quantityApproved > 0 && $inventoryItem) {
                            $inventoryItem->decrement('quantity_available', $quantityApproved);
                        }
                    }
                }
                Log::info('Event items attached', ['event_id' => $event->id, 'items_count' => count($request->inventory_items)]);
            }

            DB::commit();

            // Create success message
            $message = $user->isAdmin()
                ? 'Event "' . $event->title . '" has been created and automatically approved! It will appear in the events list below.'
                : 'Event "' . $event->title . '" has been created successfully and is pending admin approval. It will appear in the events list below.';

            // Use session()->flash() to ensure message persists
            session()->flash('success', $message);
            
            Log::info('Event creation completed, redirecting to events.index', [
                'event_id' => $event->id,
                'status' => $status,
                'user_id' => $user->id,
            ]);

            // Redirect to events index so users can see their newly created event
            return redirect()->route('events.index')
                ->with('success', $message);
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            
            Log::warning('Event creation validation failed', [
                'errors' => $e->errors(),
                'user_id' => $user->id ?? null,
                'user_role' => $user->role ?? null,
            ]);

            return redirect()->back()
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', 'Please correct the errors below and try again.');
                
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'user_role' => $user->role ?? null,
                'input' => $request->only(['title', 'event_date', 'start_time', 'end_time', 'location']),
            ]);

            session()->flash('error', 'Failed to create event: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create event: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        $event->load(['creator', 'approver', 'eventItems.inventoryItem']);
        return view('events.show', compact('event'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $user = Auth::user();
        
        if (!$user->canCreateEvents() || ($event->created_by !== $user->id && !$user->isAdmin())) {
            abort(403, 'Unauthorized action.');
        }

        if ($event->status !== 'pending') {
            return redirect()->route('events.show', $event)
                ->with('error', 'Only pending events can be edited.');
        }

        $inventoryItems = InventoryItem::available()
            ->orderBy('category', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->groupBy(function($item) {
                // Normalize category to lowercase for case-insensitive grouping
                return strtolower(trim($item->category));
            })
            ->mapWithKeys(function($items, $normalizedCategory) {
                // Use the first item's original category name as the display key
                $displayCategory = $items->first()->category;
                return [$displayCategory => $items->values()];
            });

        $event->load('eventItems.inventoryItem');

        return view('events.edit', compact('event', 'inventoryItems'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $user = Auth::user();
        
        if (!$user->canCreateEvents() || ($event->created_by !== $user->id && !$user->isAdmin())) {
            abort(403, 'Unauthorized action.');
        }

        if ($event->status !== 'pending') {
            return redirect()->route('events.show', $event)
                ->with('error', 'Only pending events can be edited.');
        }

        // Normalize inventory items: keep only items with a quantity (items actually selected)
        $rawItems = $request->input('inventory_items', []);
        if (is_array($rawItems)) {
            $filteredItems = array_filter($rawItems, function ($item) {
                return is_array($item)
                    && isset($item['quantity'])
                    && $item['quantity'] !== null
                    && $item['quantity'] !== '';
            });
            $request->merge(['inventory_items' => $filteredItems]);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date|after_or_equal:' . now()->toDateString(),
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'location' => 'required|string|max:255',
            'inventory_items' => 'nullable|array',
            'inventory_items.*.id' => 'required|exists:inventory_items,id',
            'inventory_items.*.quantity' => 'required|integer|min:1',
        ]);

        // Check for date/time conflicts with approved events (excluding current event)
        $conflictingEvent = Event::approved()
            ->where('id', '!=', $event->id)
            ->whereDate('event_date', $request->event_date)
            ->where(function($query) use ($request) {
                $query->where(function($q) use ($request) {
                    $q->where('start_time', '<=', $request->start_time)
                      ->where('end_time', '>', $request->start_time);
                })->orWhere(function($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>=', $request->end_time);
                })->orWhere(function($q) use ($request) {
                    $q->where('start_time', '>=', $request->start_time)
                      ->where('end_time', '<=', $request->end_time);
                });
            })
            ->first();

        if ($conflictingEvent) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'The selected date and time conflicts with an existing approved event: "' . $conflictingEvent->title . '" on ' . $conflictingEvent->event_date->format('M d, Y') . ' from ' . \Carbon\Carbon::parse($conflictingEvent->start_time)->format('g:i A') . ' to ' . \Carbon\Carbon::parse($conflictingEvent->end_time)->format('g:i A') . '.');
        }

        DB::beginTransaction();
        
        try {
            $event->update([
                'title' => $request->title,
                'description' => $request->description,
                'event_date' => $request->event_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'location' => $request->location,
            ]);

            // Synchronize event items
            $newEventItems = collect($request->input('inventory_items', []));
            $existingEventItems = $event->eventItems()->get()->keyBy('inventory_item_id');

            // Delete items that are no longer in the request
            $deletedIds = $existingEventItems->keys()->diff($newEventItems->pluck('id'));
            if ($deletedIds->isNotEmpty()) {
                $event->eventItems()->whereIn('inventory_item_id', $deletedIds)->delete();
            }

            // Update existing items or create new ones
            foreach ($newEventItems as $item) {
                $notes = isset($item['notes']) ? $item['notes'] : null;
                $event->eventItems()->updateOrCreate(
                    ['inventory_item_id' => $item['id']],
                    [
                        'quantity_requested' => $item['quantity'],
                        'notes' => $notes,
                    ]
                );
            }

            DB::commit();
            
            // Log update
            try {
                Log::info('Event updated', [
                    'event_id' => $event->id,
                    'updated_by' => $user->id,
                    'updated_by_role' => $user->role,
                    'changes' => $request->only(['title', 'event_date', 'start_time', 'end_time', 'location'])
                ]);
            } catch (\Exception $logEx) {
                Log::error('Failed to write event update log', ['error' => $logEx->getMessage()]);
            }

            return redirect()->route('events.show', $event)
                ->with('success', 'Event updated successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'input' => $request->only(['title', 'event_date', 'start_time', 'end_time', 'location']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update event. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        if ($event->status !== 'rejected') {
            return redirect()->route('events.index')
                ->with('error', 'Only rejected events can be deleted.');
        }

        DB::beginTransaction();

        try {
            $snapshot = $event->toArray();

            EventHistory::create([
                'event_id' => $event->id,
                'action' => 'deleted',
                'title' => $event->title,
                'status' => $event->status,
                'event_date' => $event->event_date,
                'event_data' => $snapshot,
                'performed_by' => $user->id,
                'performed_at' => now(),
            ]);

            // Remove related items before deleting the event
            $event->eventItems()->delete();
            $event->delete();

            DB::commit();

            Log::info('Rejected event deleted and archived', [
                'event_id' => $event->id,
                'deleted_by' => $user->id,
            ]);

            return redirect()->route('events.index')
                ->with('success', 'Rejected event deleted. A snapshot was saved to history.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete rejected event', [
                'error' => $e->getMessage(),
                'event_id' => $event->id,
            ]);

            return redirect()->route('events.index')
                ->with('error', 'Failed to delete the rejected event. Please try again.');
        }
    }

    /**
     * Approve an event with item-level control
     */
    public function approve(Request $request, Event $event)
    {
        $user = Auth::user();
        
        if (!$user->canApproveEvents()) {
            abort(403, 'Unauthorized action.');
        }

        if ($event->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'Only pending events can be approved.');
        }

        DB::beginTransaction();
        
        try {
            // If item-level decisions are provided, process them
            if ($request->has('event_items')) {
                $allItemsApproved = true;
                $hasApprovedItems = false;

                foreach ($request->event_items as $eventItemId => $decision) {
                    $eventItem = $event->eventItems()->find($eventItemId);
                    
                    if (!$eventItem) {
                        continue;
                    }

                    $action = $decision['action'] ?? 'approve';
                    $quantityApproved = $decision['quantity_approved'] ?? $eventItem->quantity_requested;
                    $notes = $decision['notes'] ?? null;

                    if ($action === 'approve') {
                        // Check availability for the event date
                        $inventoryItem = $eventItem->inventoryItem;
                        $availableQuantity = $inventoryItem->getAvailableQuantityForDate($event->event_date);
                        
                        // Limit approved quantity to what's available
                        $quantityApproved = min($quantityApproved, $availableQuantity, $eventItem->quantity_requested);
                        
                        if ($quantityApproved > 0) {
                            $eventItem->update([
                                'status' => 'approved',
                                'quantity_approved' => $quantityApproved,
                                'notes' => $notes,
                            ]);
                            
                            // Decrease inventory quantity_available
                            $inventoryItem->decrement('quantity_available', $quantityApproved);
                            
                            $hasApprovedItems = true;
                        } else {
                            // Not enough available, reject this item
                            $eventItem->update([
                                'status' => 'rejected',
                                'quantity_approved' => 0,
                                'notes' => $notes ?: 'Item not available in sufficient quantity for this date.',
                            ]);
                            $allItemsApproved = false;
                        }
                    } else {
                        // Reject this item
                        $eventItem->update([
                            'status' => 'rejected',
                            'quantity_approved' => 0,
                            'notes' => $notes ?: 'Item not available for this date.',
                        ]);
                        $allItemsApproved = false;
                    }
                }

                // Only approve the event if at least one item was approved
                if ($hasApprovedItems) {
                    $event->update([
                        'status' => 'approved',
                        'approved_by' => $user->id,
                        'approved_at' => now(),
                    ]);
                    
                    $message = $allItemsApproved 
                        ? 'Event approved successfully with all requested items.'
                        : 'Event approved successfully. Some items were not available and have been rejected.';
                } else {
                    // No items were approved, reject the entire event
                    $event->update([
                        'status' => 'rejected',
                        'approved_by' => $user->id,
                        'approved_at' => now(),
                        'rejection_reason' => 'None of the requested items are available for this date.',
                    ]);
                    
                    $message = 'Event rejected. None of the requested items are available for this date.';
                }
            } else {
                // No item-level decisions, approve all items by default
                $allApproved = true;
                foreach ($event->eventItems as $eventItem) {
                    $inventoryItem = $eventItem->inventoryItem;
                    $availableQuantity = $inventoryItem->getAvailableQuantityForDate($event->event_date);
                    $quantityApproved = min($eventItem->quantity_requested, $availableQuantity);
                    
                    if ($quantityApproved > 0) {
                        $eventItem->update([
                            'status' => 'approved',
                            'quantity_approved' => $quantityApproved,
                        ]);
                        
                        // Decrease inventory quantity_available
                        $inventoryItem->decrement('quantity_available', $quantityApproved);
                    } else {
                        $eventItem->update([
                            'status' => 'rejected',
                            'quantity_approved' => 0,
                            'notes' => 'Item not available in sufficient quantity for this date.',
                        ]);
                        $allApproved = false;
                    }
                }

                $event->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
                
                $message = $allApproved 
                    ? 'Event approved successfully with all requested items.'
                    : 'Event approved successfully. Some items were not available and have been rejected.';
            }

            DB::commit();

            // Log approval summary
            try {
                $approvedCount = $event->eventItems()->where('status', 'approved')->count();
                $rejectedCount = $event->eventItems()->where('status', 'rejected')->count();

                Log::info('Event approval processed', [
                    'event_id' => $event->id,
                    'processed_by' => $user->id,
                    'processed_by_role' => $user->role,
                    'event_status' => $event->status,
                    'approved_items' => $approvedCount,
                    'rejected_items' => $rejectedCount,
                ]);
            } catch (\Exception $logEx) {
                Log::error('Failed to write event approval log', ['error' => $logEx->getMessage()]);
            }

            return redirect()->back()
                ->with('success', $message);
                
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to approve event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to approve event. Please try again.');
        }
    }

    /**
     * Reject an event
     */
    public function reject(Request $request, Event $event)
    {
        $user = Auth::user();
        
        if (!$user->canApproveEvents()) {
            abort(403, 'Unauthorized action.');
        }

        if ($event->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'Only pending events can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $event->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Log rejection
        try {
            Log::info('Event rejected', [
                'event_id' => $event->id,
                'rejected_by' => $user->id,
                'rejected_by_role' => $user->role,
                'reason' => $request->rejection_reason,
            ]);
        } catch (\Exception $logEx) {
            Log::error('Failed to write event rejection log', ['error' => $logEx->getMessage()]);
        }

        return redirect()->back()
            ->with('success', 'Event rejected successfully.');
    }

    /**
     * Return an item (mark as returned and restore inventory)
     * Staff and admin can confirm item returns
     */
    public function returnItem(Request $request, EventItem $eventItem)
    {
        $user = Auth::user();
        
        // Only admin and staff can confirm item returns
        if (!$user->canConfirmReturns()) {
            abort(403, 'Unauthorized action.');
        }

        if ($eventItem->isReturned()) {
            return redirect()->back()
                ->with('error', 'This item has already been returned.');
        }

        if ($eventItem->status !== 'approved' || $eventItem->quantity_approved <= 0) {
            return redirect()->back()
                ->with('error', 'This item cannot be returned.');
        }

        DB::beginTransaction();
        
        try {
            // Mark item as returned
            $eventItem->update([
                'returned_at' => now(),
            ]);

            // Restore inventory quantity
            $inventoryItem = $eventItem->inventoryItem;
            $inventoryItem->increment('quantity_available', $eventItem->quantity_approved);

            DB::commit();

            Log::info('Item returned', [
                'event_item_id' => $eventItem->id,
                'inventory_item_id' => $inventoryItem->id,
                'quantity_returned' => $eventItem->quantity_approved,
                'returned_by' => $user->id,
            ]);

            return redirect()->back()
                ->with('success', $eventItem->inventoryItem->name . ' has been returned successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to return item', [
                'error' => $e->getMessage(),
                'event_item_id' => $eventItem->id,
                'user_id' => $user->id ?? null,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to return item. Please try again.');
        }
    }
}
