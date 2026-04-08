<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventHistory;
use App\Models\EventItem;
use App\Models\InventoryItem;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    private const AUTO_REJECTION_REASON = 'This event was automatically rejected because another event with higher priority has already been approved for the same schedule.';
    private const DEFAULT_LOCATIONS = [
        'Covered Court',
        'Open Court 1',
        'Open Court 2',
        'Sipag Hall',
        'Junior High School Computer Laboratory',
        'College Computer Laboratory 1',
        'College Computer Laboratory 2',
        'Studio',
    ];

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
        // Active/upcoming events only:
        // - exclude rejected events (they belong in history)
        // - use end_date + end_time vs now() to exclude completed/ended events
        $sort = request()->input('sort', 'event_date');
        $direction = strtolower((string) request()->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $search = trim((string) request()->input('search', ''));
        $status = trim((string) request()->input('status', ''));

        if (!in_array($sort, ['title', 'event_date'], true)) {
            $sort = 'event_date';
        }

        $eventsQuery = Event::with(['creator', 'approver', 'eventItems.inventoryItem'])
            ->where('status', '!=', 'rejected')
            ->future();

        if ($search !== '') {
            $eventsQuery->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('location', 'like', '%' . $search . '%');
            });
        }

        if ($status !== '' && in_array($status, ['approved', 'pending'], true)) {
            $eventsQuery->where('status', $status);
        }

        $events = $eventsQuery
            ->orderBy($sort, $direction)
            ->orderBy('created_at', 'desc') // Show newest events first for same sort key
            ->paginate(10)
            ->appends(request()->query());

        return view('events.index', compact('events', 'sort', 'direction', 'search', 'status'));
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
        $perPage = 10;
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

        $viewableEventIds = Event::query()
            ->whereIn('id', $items->pluck('event_id')->filter()->unique()->values())
            ->pluck('id')
            ->all();

        $roles = \App\Models\User::whereNotNull('role')
            ->where('role', '!=', '')
            ->orderBy('role')
            ->pluck('role')
            ->unique()
            ->values();

        return view('events.history', compact('history', 'roles', 'filters', 'viewableEventIds'));
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

            $userCache = [];
            $resolveUserName = function ($userId) use (&$userCache): string {
                if ($userId === null || $userId === '') {
                    return 'System';
                }

                $userId = (int) $userId;
                if ($userId <= 0) {
                    return 'System';
                }

                if (array_key_exists($userId, $userCache)) {
                    return $userCache[$userId] ?: 'System';
                }

                $user = \App\Models\User::find($userId);
                $userCache[$userId] = $user?->name;
                return $userCache[$userId] ?: 'System';
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
                'Role',
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
                $roleLabel = $entry->creator->role_label ?? ($entry->creator->role ?? '');

                $performedById = $entry->performed_by
                    ?? ($entry->event_data['approved_by'] ?? null)
                    ?? ($eventData['approved_by'] ?? null);

                $performedBy = $entry->approver->name
                    ?? $entry->performedBy->name
                    ?? $resolveUserName($performedById);

                $row = [
                    $entry->event_id ?? '',
                    $entry->title ?? '',
                    $this->safeDate($entry->event_date),
                    ucfirst((string) ($entry->status ?? '')),
                    $roleLabel,
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
        $sort = (string) $request->input('sort', 'event_date');
        if (!in_array($sort, ['title', 'event_date'], true)) {
            $sort = 'event_date';
        }

        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $status = (string) $request->input('status', '');
        $legacyDepartment = (string) $request->input('department', '');
        $role = (string) $request->input('role', $legacyDepartment);

        return [
            'search' => (string) $request->input('search', ''),
            'event_date' => (string) $request->input('event_date', ''),
            'status' => $status,
            'role' => $role,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    private function buildAllHistoryCollection()
    {
        // Keep Event History clean: "deleted" actions are not meaningful for auditing
        // the event lifecycle and must never be shown.
        $historyRecords = EventHistory::with('performedBy')
            ->where('action', '!=', 'deleted')
            ->orderByDesc('performed_at')
            ->orderByDesc('created_at')
            ->get();

        // Avoid duplicate “rejected” history cards if we already archived the rejection action.
        $loggedRejectedEventIds = $historyRecords
            ->where('action', 'rejected')
            ->pluck('event_id')
            ->filter()
            ->unique();

        $now = now();
        $today = $now->toDateString();
        $nowTime = $now->format('H:i:s');

        // Enforce "Event History" separation strictly:
        // - rejected events always appear
        // - non-rejected events only appear once their end_datetime has passed
        // - for ended non-rejected records, normalize status/action for consistent UI + filtering
        $historyRecords = $historyRecords->filter(function ($record) use ($today, $nowTime) {
            // Extra safety: ensure "deleted" records never get reclassified.
            if ((string) ($record->action ?? '') === 'deleted') {
                return false;
            }

            $recordStatus = (string) ($record->status ?? '');
            if ($recordStatus === 'rejected') {
                return true;
            }

            $eventData = $record->event_data;
            if (!is_array($eventData)) {
                return false;
            }

            $eventDateValue = $record->event_date ?? ($eventData['event_date'] ?? null);
            $endTimeValue = $eventData['end_time'] ?? null;
            if (!$eventDateValue || !$endTimeValue) {
                return false;
            }

            try {
                $eventDate = $eventDateValue instanceof \DateTimeInterface
                    ? $eventDateValue->format('Y-m-d')
                    : \Illuminate\Support\Carbon::parse($eventDateValue)->format('Y-m-d');

                $endTime = $endTimeValue instanceof \DateTimeInterface
                    ? $endTimeValue->format('H:i:s')
                    : \Illuminate\Support\Carbon::parse((string) $endTimeValue)->format('H:i:s');
            } catch (\Throwable $e) {
                return false;
            }

            $isFinished = $eventDate < $today || ($eventDate === $today && $endTime <= $nowTime);
            if (!$isFinished) {
                return false;
            }

            // Normalize for frontend "Completed" filtering/badges.
            $record->status = 'completed';
            $record->action = 'completed';

            return true;
        })->values();

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

        // Completed events are those whose end_datetime (event_date + end_time) is <= now,
        // regardless of whether they were pending or approved (as long as they are not rejected).
        $completedEvents = Event::with(['creator', 'approver'])
            ->where('status', '!=', 'rejected')
            ->where(function ($q) use ($today, $nowTime) {
                $q->where('event_date', '<', $today)
                    ->orWhere(function ($q2) use ($today, $nowTime) {
                        $q2->where('event_date', $today)
                            ->where('end_time', '<=', $nowTime);
                    });
            })
            ->orderByDesc('event_date')
            ->orderByDesc('created_at')
            ->get();

        // Rejected events must appear in history immediately, even if they were scheduled for the future.
        $rejectedEvents = Event::with(['creator', 'approver'])
            ->where('status', 'rejected')
            ->orderByDesc('event_date')
            ->orderByDesc('created_at')
            ->get();

        $completedEventsHistory = $completedEvents->map(function ($event) {
            $endTime = $event->end_time instanceof \DateTimeInterface
                ? $event->end_time->format('H:i:s')
                : (string) $event->end_time;

            $performedAt = \Illuminate\Support\Carbon::parse($event->event_date->toDateString() . ' ' . $endTime);

            return (object) [
                'id' => 'event_' . $event->id,
                'event_id' => $event->id,
                'action' => 'completed',
                'title' => $event->title,
                'status' => 'completed',
                'event_date' => $event->event_date,
                'performed_at' => $performedAt,
                'performed_by' => $event->approved_by,
                'created_by' => $event->created_by,
                'performedBy' => $event->approver,
                'creator' => $event->creator,
                'reason' => null,
                'is_finished_event' => true,
                'event_data' => $event->toArray(),
            ];
        });

        $rejectedEventsHistory = $rejectedEvents
            ->reject(function ($event) use ($loggedRejectedEventIds) {
                // If we already have an explicit "rejected" action record, don't duplicate it.
                return $loggedRejectedEventIds->contains($event->id);
            })
            ->map(function ($event) {
                return (object) [
                    'id' => 'event_' . $event->id,
                    'event_id' => $event->id,
                    'action' => 'rejected',
                    'title' => $event->title,
                    'status' => 'rejected',
                    'event_date' => $event->event_date,
                    'performed_at' => $event->approved_at ?? $event->updated_at,
                    'performed_by' => $event->approved_by,
                    'created_by' => $event->created_by,
                    'performedBy' => $event->approver,
                    'creator' => $event->creator,
                    'reason' => $event->rejection_reason ?: null,
                    'is_finished_event' => true,
                    'event_data' => $event->toArray(),
                ];
            });

        return $historyRecords
            ->concat($completedEventsHistory)
            ->concat($rejectedEventsHistory)
            ->sortByDesc(function ($item) {
                return $item->performed_at ?? $item->event_date;
            })
            ->values();
    }

    private function applyHistoryFilters($allHistory, array $filters)
    {
        $filtered = $allHistory->filter(function ($item) use ($filters) {
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

            if (($filters['role'] ?? '') !== '') {
                $creator = $item->creator ?? null;
                $creatorRole = $creator && is_object($creator) ? ($creator->role ?? null) : null;
                if ($creatorRole !== $filters['role']) {
                    return false;
                }
            }

            return true;
        });

        if (($filters['sort'] ?? 'event_date') === 'title') {
            $sorted = $filtered->sortBy(function ($item) {
                return strtolower((string) ($item->title ?? ''));
            }, SORT_NATURAL, ($filters['direction'] ?? 'desc') === 'desc');
        } else {
            $sorted = $filtered->sortBy(function ($item) {
                $date = $item->event_date ?? null;
                if ($date instanceof \DateTimeInterface) {
                    return $date->getTimestamp();
                }

                try {
                    return \Illuminate\Support\Carbon::parse($date)->getTimestamp();
                } catch (\Throwable $e) {
                    return 0;
                }
            }, SORT_NUMERIC, ($filters['direction'] ?? 'desc') === 'desc');
        }

        return $sorted->values();
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
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d g:i A');
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
            return \Illuminate\Support\Carbon::parse($value)->format('g:i A');
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

        $locations = $this->getAvailableLocations();

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

        return view('events.create', [
            'inventoryItems' => $inventoryItems,
            'locations' => $locations,
        ]);
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

        $locationRules = [
            'location' => 'required_without:new_location|string|max:255',
        ];

        if ($user->isAdmin()) {
            $locationRules['new_location'] = 'nullable|string|max:255';
        }

        $minimumStartDate = now()->addDays(7)->toDateString();

        // Validate the incoming request
        $validated = $request->validate(array_merge([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date|after_or_equal:' . $minimumStartDate,
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i|after_or_equal:08:00|before_or_equal:17:00',
            'end_time' => 'required|date_format:H:i|after:start_time|after_or_equal:08:00|before_or_equal:17:00',
            'inventory_items' => 'nullable|array',
            'inventory_items.*.id' => 'required|exists:inventory_items,id',
            'inventory_items.*.quantity' => 'required|integer|min:1',
        ], $locationRules), [
            'title.required' => 'The event title is required.',
            'start_date.required' => 'The start date is required.',
            'start_date.after_or_equal' => 'Events must be scheduled at least one week in advance.',
            'end_date.required' => 'The end date is required.',
            'end_date.after_or_equal' => 'The end date cannot be earlier than the start date.',
            'start_time.required' => 'The start time is required.',
            'start_time.date_format' => 'The start time format is invalid.',
            'start_time.after_or_equal' => 'Events can only be scheduled between 8:00 AM and 5:00 PM.',
            'start_time.before_or_equal' => 'Events can only be scheduled between 8:00 AM and 5:00 PM.',
            'end_time.required' => 'The end time is required.',
            'end_time.date_format' => 'The end time format is invalid.',
            'end_time.after' => 'The end time must be after the start time.',
            'end_time.after_or_equal' => 'Events can only be scheduled between 8:00 AM and 5:00 PM.',
            'end_time.before_or_equal' => 'Events can only be scheduled between 8:00 AM and 5:00 PM.',
            'location.required' => 'The location is required.',
            'location.required_without' => 'The location is required unless you add a new location.',
            'description.required' => 'The description is required.',
        ]);

        Log::info('Event creation attempt', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'title' => $validated['title'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        // Check for date/time conflicts with approved events across the selected date range
        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];

        $conflictingEvent = Event::approved()
            ->where(function ($query) use ($startDate, $endDate) {
                // New multi-day events: overlap if date ranges intersect
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereNotNull('start_date')
                      ->whereNotNull('end_date')
                      ->where(function ($q2) use ($startDate, $endDate) {
                          $q2->whereBetween('start_date', [$startDate, $endDate])
                             ->orWhereBetween('end_date', [$startDate, $endDate])
                             ->orWhere(function ($q3) use ($startDate, $endDate) {
                                 $q3->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                             });
                      });
                })
                // Legacy single-day events where only event_date is populated
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereNull('start_date')
                      ->whereNull('end_date')
                      ->whereBetween('event_date', [$startDate, $endDate]);
                });
            })
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
            // Resolve location, allowing admins to add new locations
            $locationName = $validated['location'] ?? null;
            if ($user->isAdmin() && !empty($validated['new_location'] ?? null)) {
                $locationName = trim($validated['new_location']);

                if ($locationName !== '' && Schema::hasTable('locations')) {
                    Location::firstOrCreate(['name' => $locationName]);
                }
            }

            // Set status based on who creates the event
            // Admin events are auto-approved, Head events need admin approval
            $status = $user->isAdmin() ? 'approved' : 'pending';
            $approved_by = $user->isAdmin() ? $user->id : null;
            $approved_at = $user->isAdmin() ? now() : null;

            $event = Event::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                // Keep event_date for backward compatibility; treat as start_date
                'event_date' => $validated['start_date'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'location' => $locationName,
                'department' => $user->department,
                'status' => $status,
                'created_by' => $user->id,
                'approved_by' => $approved_by,
                'approved_at' => $approved_at,
            ]);

            $autoRejectedCount = 0;
            if ($status === 'approved') {
                $autoRejectedCount = $this->autoRejectConflictingPendingEvents($event, $user->id);
            }

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
                        $availableQuantity = $inventoryItem ? $inventoryItem->getAvailableQuantityForDate($validated['start_date']) : 0;
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

            if ($autoRejectedCount > 0) {
                $message .= ' ' . $autoRejectedCount . ' conflicting pending event(s) were automatically rejected.';
            }

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
            'start_date' => 'required|date|after_or_equal:' . now()->toDateString(),
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i|after_or_equal:08:00|before_or_equal:17:00',
            'end_time' => 'required|date_format:H:i|after:start_time|after_or_equal:08:00|before_or_equal:17:00',
            'location' => 'required|string|max:255',
            'inventory_items' => 'nullable|array',
            'inventory_items.*.id' => 'required|exists:inventory_items,id',
            'inventory_items.*.quantity' => 'required|integer|min:1',
        ], [
            'start_time.required' => 'The start time is required.',
            'start_time.date_format' => 'The start time format is invalid.',
            'start_time.after_or_equal' => 'Events can only be scheduled between 8:00 AM and 5:00 PM.',
            'start_time.before_or_equal' => 'Events can only be scheduled between 8:00 AM and 5:00 PM.',
            'end_time.required' => 'The end time is required.',
            'end_time.date_format' => 'The end time format is invalid.',
            'end_time.after' => 'The end time must be after the start time.',
            'end_time.after_or_equal' => 'Events can only be scheduled between 8:00 AM and 5:00 PM.',
            'end_time.before_or_equal' => 'Events can only be scheduled between 8:00 AM and 5:00 PM.',
        ]);

        // Check for date/time conflicts with approved events (excluding current event)
        $conflictingEvent = Event::approved()
            ->where('id', '!=', $event->id)
            ->where(function ($query) use ($request) {
                $startDate = $request->start_date;
                $endDate = $request->end_date;

                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereNotNull('start_date')
                      ->whereNotNull('end_date')
                      ->where(function ($q2) use ($startDate, $endDate) {
                          $q2->whereBetween('start_date', [$startDate, $endDate])
                             ->orWhereBetween('end_date', [$startDate, $endDate])
                             ->orWhere(function ($q3) use ($startDate, $endDate) {
                                 $q3->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                             });
                      });
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereNull('start_date')
                      ->whereNull('end_date')
                      ->whereBetween('event_date', [$startDate, $endDate]);
                });
            })
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
                'event_date' => $request->start_date,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
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
            $wasApproved = false;

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
                    $wasApproved = true;
                    
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
                $wasApproved = true;
                
                $message = $allApproved 
                    ? 'Event approved successfully with all requested items.'
                    : 'Event approved successfully. Some items were not available and have been rejected.';
            }

            $autoRejectedCount = 0;
            if ($wasApproved) {
                $autoRejectedCount = $this->autoRejectConflictingPendingEvents($event, $user->id);
                if ($autoRejectedCount > 0) {
                    $message .= ' ' . $autoRejectedCount . ' conflicting pending event(s) were automatically rejected.';
                }
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

        DB::beginTransaction();

        try {
            $request->validate([
                // Require a meaningful reason (min 10 chars) so users can see why.
                'rejection_reason' => 'required|string|min:10|max:500',
            ]);

            $event->update([
                'status' => 'rejected',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            EventHistory::create([
                'event_id' => $event->id,
                'action' => 'rejected',
                'title' => $event->title,
                'status' => 'rejected',
                'event_date' => $event->event_date,
                'event_data' => $event->toArray(),
                'reason' => $request->rejection_reason,
                'performed_by' => $user->id,
                'performed_at' => $event->approved_at ?? now(),
            ]);

            // Log rejection
            Log::info('Event rejected', [
                'event_id' => $event->id,
                'rejected_by' => $user->id,
                'rejected_by_role' => $user->role,
                'reason' => $request->rejection_reason,
            ]);

            DB::commit();

            return redirect()->back()
                ->with('success', 'Event rejected successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to reject event', [
                'error' => $e->getMessage(),
                'event_id' => $event->id,
                'user_id' => $user->id ?? null,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to reject event. Please try again.');
        }
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

        $quantityApproved = (int) $eventItem->quantity_approved;

        $request->validate([
            'quantity_returned' => ['required', 'integer', 'min:0', 'max:' . $quantityApproved],
            'quantity_damaged' => ['required', 'integer', 'min:0', 'lte:quantity_returned'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $quantityReturned = (int) $request->input('quantity_returned');
        $quantityDamaged = (int) $request->input('quantity_damaged');
        $quantityAccepted = max(0, $quantityReturned - $quantityDamaged); // accepted = returned - damaged

        // completed if no damage, partially_accepted if some items damaged, damaged if all unusable
        $returnStatus = match (true) {
            $quantityDamaged === 0 => 'completed',
            $quantityAccepted > 0 => 'partially_accepted',
            default => 'damaged',
        };

        $returnRemarks = $request->input('remarks');

        DB::beginTransaction();
        
        try {
            // Reload related rows within the transaction for consistent inventory updates.
            $eventItem = EventItem::query()
                ->whereKey($eventItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($eventItem->isReturned()) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'This item has already been returned.');
            }

            $eventItem->loadMissing(['inventoryItem', 'event.creator']);
            $inventoryItem = $eventItem->inventoryItem()->lockForUpdate()->firstOrFail();

            // Mark item as returned (with inspection details)
            $eventItem->update([
                'returned_at' => now(),
                'quantity_returned' => $quantityReturned,
                'quantity_damaged' => $quantityDamaged,
                'quantity_accepted' => $quantityAccepted,
                'return_remarks' => $returnRemarks,
                'return_status' => $returnStatus,
            ]);

            // Restore inventory quantity only for accepted items.
            if ($quantityAccepted > 0) {
                $inventoryItem->increment('quantity_available', $quantityAccepted);
            }

            // Mark damaged items for repair/replacement (queue record).
            if ($quantityDamaged > 0) {
                DB::table('inventory_damage_reports')->insert([
                    'event_item_id' => $eventItem->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity_damaged' => $quantityDamaged,
                    'remarks' => $returnRemarks,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            Log::info('Item returned', [
                'event_item_id' => $eventItem->id,
                'inventory_item_id' => $inventoryItem->id,
                'quantity_returned' => $quantityReturned,
                'quantity_damaged' => $quantityDamaged,
                'quantity_accepted' => $quantityAccepted,
                'return_status' => $returnStatus,
                'returned_by' => $user->id,
            ]);

            // Notify borrower if damaged items were detected.
            if ($quantityDamaged > 0) {
                $borrower = $eventItem->event->creator ?? null;
                if ($borrower) {
                    $borrower->notify(new \App\Notifications\DamagedItemsDetected(
                        $inventoryItem->name ?? $eventItem->inventoryItem->name ?? 'Inventory Item',
                        $quantityDamaged,
                        $returnRemarks
                    ));
                }
            }

            return redirect()->back()
                ->with(
                    'success',
                    $eventItem->inventoryItem->name . ' inspection submitted. Accepted: ' . $quantityAccepted . ', Damaged: ' . $quantityDamaged . '.'
                );
                
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

    /**
     * Automatically reject pending events that overlap with an approved event.
     */
    private function autoRejectConflictingPendingEvents(Event $approvedEvent, int $performedBy): int
    {
        $now = now();
        $conflictingPendingEvents = Event::query()
            ->where('status', 'pending')
            ->where('id', '!=', $approvedEvent->id)
            ->whereDate('event_date', $approvedEvent->event_date)
            ->where('start_time', '<', $approvedEvent->end_time)
            ->where('end_time', '>', $approvedEvent->start_time)
            ->lockForUpdate()
            ->get();

        $count = 0;
        foreach ($conflictingPendingEvents as $conflictingEvent) {
            $conflictingEvent->update([
                'status' => 'rejected',
                'approved_by' => $performedBy,
                'approved_at' => $now,
                'rejection_reason' => self::AUTO_REJECTION_REASON,
            ]);

            EventHistory::create([
                'event_id' => $conflictingEvent->id,
                'action' => 'rejected',
                'title' => $conflictingEvent->title,
                'status' => 'rejected',
                'event_date' => $conflictingEvent->event_date,
                'event_data' => $conflictingEvent->toArray(),
                'reason' => self::AUTO_REJECTION_REASON,
                'performed_by' => $performedBy,
                'performed_at' => $now,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Return dropdown locations without crashing if locations table is not migrated yet.
     */
    private function getAvailableLocations()
    {
        try {
            if (Schema::hasTable('locations')) {
                $savedLocations = Location::query()
                    ->orderBy('name')
                    ->pluck('name');

                if ($savedLocations->isNotEmpty()) {
                    return $savedLocations;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Falling back to default locations; failed to read locations table.', [
                'error' => $e->getMessage(),
            ]);
        }

        return collect(self::DEFAULT_LOCATIONS);
    }
}
