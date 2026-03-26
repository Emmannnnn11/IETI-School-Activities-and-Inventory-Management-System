@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </h2>
                <div class="text-dark fw-bold fs-4">
    Welcome back, {{ Auth::user()->name }}!
</div>
            </div>
        </div>
    </div>

    <!-- Dashboard View -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                    <h4 class="text-success">{{ $events->where('status', 'approved')->count() }}</h4>
                    <p class="text-muted mb-0">Approved Events</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                    <h4 class="text-warning">{{ $events->where('status', 'pending')->count() }}</h4>
                    <p class="text-muted mb-0">Pending Events</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-archive fa-3x text-secondary mb-3"></i>
                    <h4 class="text-secondary">{{ $archivedEventsCount }}</h4>
                    <p class="text-muted mb-0">Archived (Completed/Rejected)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                    <h4 class="text-info">{{ $inventoryItems->count() }}</h4>
                    <p class="text-muted mb-0">Inventory Items</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Event Calendar
                    </h5>
                </div>
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Recent Events
                    </h5>
                </div>
                <div class="card-body">
                    @if($events->count() > 0)
                        @foreach($events->take(5) as $event)
                        <div class="d-flex align-items-center mb-3 p-2 rounded" style="background-color: #f8f9fa;">
                            <div class="me-3">
                                <span class="badge {{ $event->status_badge_class }}">{{ ucfirst($event->status) }}</span>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $event->title }}</h6>
                                <small class="text-muted">
                                    {{ $event->event_date->format('M d, Y') }} at {{ $event->start_time }}
                                </small>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center">No events found.</p>
                    @endif
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-box-open me-2"></i>
                        Borrowed Items
                    </h5>
                </div>
                <div class="card-body">
                    @if(isset($pendingBorrowedItems) && $pendingBorrowedItems->count() > 0)
                        @foreach($pendingBorrowedItems as $eventItem)
                        <div class="d-flex align-items-start mb-3 p-2 rounded border" style="background-color: #f8f9fa;">
                            <div class="me-3">
                                <i class="fas fa-box fa-2x text-info"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $eventItem->inventoryItem->name }}</h6>
                                <small class="text-muted d-block">
                                    Quantity: <strong>{{ $eventItem->quantity_approved }}</strong>
                                </small>
                                <small class="text-muted d-block">
                                    Event: {{ $eventItem->event->title }} on {{ $eventItem->event->event_date->format('M d, Y') }}
                                </small>
                                @php
                                    $isPastEvent = $eventItem->event->event_date < now()->toDateString() || 
                                                  ($eventItem->event->event_date == now()->toDateString() && 
                                                   $eventItem->event->end_time && 
                                                   \Carbon\Carbon::parse($eventItem->event->end_time)->format('H:i') < now()->format('H:i'));
                                @endphp
                                @if($isPastEvent)
                                    <small class="text-danger d-block mt-1">
                                        <i class="fas fa-exclamation-triangle"></i> Event finished - Item should be returned
                                    </small>
                                @endif
                                @if($eventItem->notes)
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-info-circle"></i> {{ \Illuminate\Support\Str::limit($eventItem->notes, 50) }}
                                </small>
                                @endif
                                @php
                                    $user = Auth::user();
                                    $canConfirm = $user->canConfirmReturns() || $user->isStaff() || $user->isAdmin();
                                @endphp
                                @if($canConfirm)
                                <div class="mt-2">
                                    @if($eventItem->isReturned())
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> Returned
                                        </span>
                                        <small class="text-muted d-block mt-1">
                                            Returned on: {{ $eventItem->returned_at->format('M d, Y g:i A') }}
                                        </small>
                                    @else
                                        <form action="{{ route('event-items.return', $eventItem->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" 
                                                    onclick="return confirm('Are you sure you want to confirm that this item has been returned?')">
                                                <i class="fas fa-check me-1"></i> Confirm Returned
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center">No items currently borrowed.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listWeek'
        },
        events: '/api/events',
        eventClick: function(info) {
            // Redirect to event details
            window.location.href = '/events/' + info.event.id;
        },
        eventDidMount: function(info) {
            // Add custom styling based on status
            if (info.event.extendedProps.status === 'approved') {
                info.el.style.backgroundColor = '#a3b18a';
                info.el.style.color = 'white';
            } else if (info.event.extendedProps.status === 'pending') {
                info.el.style.backgroundColor = '#FFA500';
                info.el.style.color = 'black';
            } else if (info.event.extendedProps.status === 'rejected') {
                info.el.style.backgroundColor = '#dc3545';
                info.el.style.color = 'white';
            }
        }
    });
    calendar.render();
    
    // Refresh calendar when page loads (useful after creating events)
    window.refreshCalendar = function() {
        calendar.refetchEvents();
    };
    
    // Immediately refresh calendar if there's a success message (event was just created)
    @if(session('success'))
        setTimeout(function() {
            calendar.refetchEvents();
        }, 500);
    @endif
    
    // Auto-refresh calendar every 30 seconds to show new events
    setInterval(function() {
        calendar.refetchEvents();
    }, 30000);
});
</script>
@endsection
