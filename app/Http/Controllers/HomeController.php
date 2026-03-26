<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventItem;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
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
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();
        
        // Active/upcoming events only (end_datetime > now). Rejected events belong in history.
        $events = Event::with(['creator', 'approver', 'eventItems.inventoryItem'])
            ->where('status', '!=', 'rejected')
            ->future()
            ->orderBy('event_date', 'asc')
            ->get();

        $now = now();
        $today = $now->toDateString();
        $nowTime = $now->format('H:i:s');

        $rejectedEventsCount = Event::where('status', 'rejected')->count();
        $completedEventsCount = Event::where('status', '!=', 'rejected')
            ->where(function ($q) use ($today, $nowTime) {
                $q->where('event_date', '<', $today)
                    ->orWhere(function ($q2) use ($today, $nowTime) {
                        $q2->where('event_date', $today)
                           ->where('end_time', '<=', $nowTime);
                    });
            })
            ->count();

        $archivedEventsCount = $rejectedEventsCount + $completedEventsCount;

        // Get inventory items for staff and admin
        $inventoryItems = collect();
        if ($user->canManageInventory()) {
            $inventoryItems = InventoryItem::with('eventItems.event')
                ->orderBy('name', 'asc')
                ->get();
        }

        // Get pending borrowed items (approved event items that haven't been returned)
        // This includes items from both future and past events
        $pendingBorrowedItemsQuery = EventItem::with(['event.creator', 'inventoryItem'])
            ->unreturned()
            ->whereHas('event', function($query) {
                $query->where('status', 'approved');
            });

        // College Head, Senior Head, Junior Head: only see pending items for events they created
        if (in_array($user->role, ['college_head', 'senior_head', 'junior_head'])) {
            $pendingBorrowedItemsQuery->whereHas('event', function ($query) use ($user) {
                $query->where('created_by', $user->id);
            });
        } else {
            // Staff / Head Maintenance with restricted categories: only see items they handle
            $allowedCategories = $user->getAllowedInventoryCategories();
            if (!empty($allowedCategories)) {
                $pendingBorrowedItemsQuery->whereHas('inventoryItem', function ($query) use ($allowedCategories) {
                    $query->whereIn('category', $allowedCategories);
                });
            }
        }

        $pendingBorrowedItems = $pendingBorrowedItemsQuery
            ->orderBy('created_at', 'desc')
            ->get();

        return view('home', compact('events', 'inventoryItems', 'user', 'pendingBorrowedItems', 'archivedEventsCount'));
    }
}
