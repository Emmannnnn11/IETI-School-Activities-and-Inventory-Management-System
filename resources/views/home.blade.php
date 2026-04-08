@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <style>
        .dashboard-card {
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
            min-height: 100%;
        }

        .dashboard-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
            height: 100%;
        }

        .dashboard-card-link:hover .dashboard-card {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
        }

        .dashboard-card-disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .event-hover-tooltip {
            position: fixed;
            z-index: 9999;
            max-width: min(320px, calc(100vw - 24px));
            background: rgba(33, 37, 41, 0.95);
            color: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.875rem;
            line-height: 1.25rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            pointer-events: none;
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 120ms ease, transform 120ms ease;
        }

        .event-hover-tooltip.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .event-hover-tooltip .tt-title {
            font-weight: 600;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .event-hover-tooltip .tt-row {
            display: flex;
            gap: 8px;
        }

        .event-hover-tooltip .tt-label {
            flex: 0 0 auto;
            color: rgba(255, 255, 255, 0.7);
            min-width: 76px;
        }

        .event-hover-tooltip .tt-value {
            flex: 1 1 auto;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>

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
        @foreach($dashboardCards as $card)
            <div class="col-md-3 mb-3 mb-md-0">
                @if($card['enabled'])
                    <a href="{{ $card['route'] }}" class="dashboard-card-link" aria-label="{{ $card['title'] }}">
                        <div class="card text-center dashboard-card h-100">
                            <div class="card-body">
                                <i class="{{ $card['icon'] }} fa-3x {{ $card['textClass'] }} mb-3"></i>
                                <h4 class="{{ $card['textClass'] }}">{{ $card['count'] }}</h4>
                                <p class="text-muted mb-0">{{ $card['title'] }}</p>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="card text-center dashboard-card dashboard-card-disabled h-100" aria-disabled="true" title="You are not authorized to access this section.">
                        <div class="card-body">
                            <i class="{{ $card['icon'] }} fa-3x {{ $card['textClass'] }} mb-3"></i>
                            <h4 class="{{ $card['textClass'] }}">{{ $card['count'] }}</h4>
                            <p class="text-muted mb-0">{{ $card['title'] }}</p>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
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
                    <div class="mt-3 d-flex flex-wrap gap-3 small">
                        <span class="d-inline-flex align-items-center">
                            <span class="me-2 rounded-circle" style="width: 12px; height: 12px; background-color: #ff69b4;"></span>
                            Junior High School
                        </span>
                        <span class="d-inline-flex align-items-center">
                            <span class="me-2 rounded-circle" style="width: 12px; height: 12px; background-color: #1e90ff;"></span>
                            Senior High School
                        </span>
                        <span class="d-inline-flex align-items-center">
                            <span class="me-2 rounded-circle" style="width: 12px; height: 12px; background-color: #D3D3FF;"></span>
                            College
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @php
            $currentUser = Auth::user();
            $sidebarBorrowedFirst = in_array($currentUser->role, ['staff', 'Head Maintenance'])
                || in_array(strtolower(trim((string) $currentUser->name)), ['arthur', 'alden']);
        @endphp
        <div class="col-md-4 d-flex flex-column gap-3">
            <div class="card {{ $sidebarBorrowedFirst ? 'order-2' : '' }}">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Recent Events
                    </h5>
                </div>
                <div class="card-body">
                    @if($events->count() > 0)
                        @foreach($events->take(5) as $event)
                        <a href="{{ route('events.show', $event) }}" class="text-decoration-none text-dark d-block">
                            <div class="d-flex align-items-center mb-3 p-2 rounded" style="background-color: #f8f9fa;">
                                <div class="me-3">
                                    <span class="badge {{ $event->status_badge_class }}">Upcoming Events</span>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">{{ $event->title }}</h6>
                                    <small class="text-muted">
                                        {{ $event->date_range_label }} at {{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }}
                                    </small>
                                    <small class="text-muted d-block">
                                        Role: {{ $event->scheduler_role_label }}
                                    </small>
                                </div>
                            </div>
                        </a>
                        @endforeach
                    @else
                        <p class="text-muted text-center">No events found.</p>
                    @endif
                </div>
            </div>
            
            <div class="card {{ $sidebarBorrowedFirst ? 'order-1' : '' }}">
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
                                        @php
                                            $returnStatus = $eventItem->return_status;
                                            $badgeClass = match ($returnStatus) {
                                                'completed' => 'bg-success',
                                                'partially_accepted' => 'bg-warning',
                                                'damaged' => 'bg-danger',
                                                default => 'bg-success',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">
                                            <i class="fas fa-check-circle me-1"></i>
                                            {{
                                                $returnStatus
                                                ? ucwords(str_replace('_', ' ', $returnStatus))
                                                : 'Returned'
                                            }}
                                        </span>
                                        <small class="text-muted d-block mt-1">
                                            Returned on: {{ $eventItem->returned_at->format('M d, Y g:i A') }}
                                        </small>
                                        <small class="text-muted d-block mt-1">
                                            Returned: {{ $eventItem->quantity_returned ?? 0 }},
                                            Damaged: {{ $eventItem->quantity_damaged ?? 0 }},
                                            Accepted: {{ $eventItem->quantity_accepted ?? 0 }}
                                        </small>
                                    @else
                                        <div class="mt-2">
                                            <button type="button"
                                                    class="btn btn-sm btn-warning"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#inspectReturnModal-{{ $eventItem->id }}">
                                                <i class="fas fa-clipboard-check me-1"></i> Inspect Return
                                            </button>
                                        </div>

                                        <div class="modal fade" id="inspectReturnModal-{{ $eventItem->id }}" tabindex="-1"
                                             aria-labelledby="inspectReturnLabel-{{ $eventItem->id }}"
                                             aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="inspectReturnLabel-{{ $eventItem->id }}">
                                                            Inspect Return - {{ $eventItem->inventoryItem->name }}
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>

                                                    <form action="{{ route('event-items.return', $eventItem->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label" for="quantity_returned_{{ $eventItem->id }}">
                                                                    Quantity Returned
                                                                </label>
                                                                <input type="number"
                                                                       name="quantity_returned"
                                                                       id="quantity_returned_{{ $eventItem->id }}"
                                                                       class="form-control"
                                                                       min="0"
                                                                       step="1"
                                                                       required
                                                                       value="{{ old('quantity_returned') }}"
                                                                       max="{{ (int) $eventItem->quantity_approved }}">
                                                                <small class="text-muted">Max: approved quantity ({{ (int) $eventItem->quantity_approved }})</small>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label" for="quantity_damaged_{{ $eventItem->id }}">
                                                                    Quantity Damaged
                                                                </label>
                                                                <input type="number"
                                                                       name="quantity_damaged"
                                                                       id="quantity_damaged_{{ $eventItem->id }}"
                                                                       class="form-control"
                                                                       min="0"
                                                                       step="1"
                                                                       required
                                                                       value="{{ old('quantity_damaged') }}"
                                                                       max="{{ (int) $eventItem->quantity_approved }}">
                                                                <small class="text-muted">Damaged must be <= Returned</small>
                                                            </div>

                                                            <div class="mb-0">
                                                                <label class="form-label" for="remarks_{{ $eventItem->id }}">
                                                                    Remarks
                                                                </label>
                                                                <textarea name="remarks"
                                                                          id="remarks_{{ $eventItem->id }}"
                                                                          class="form-control"
                                                                          rows="4"
                                                                          maxlength="1000"
                                                                          required
                                                                >{{ old('remarks') }}</textarea>
                                                            </div>
                                                        </div>

                                                        <div class="modal-footer">
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="fas fa-save me-1"></i> Submit Inspection
                                                            </button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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
    var activeTooltipEl = null;
    var activeTooltipEventId = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatTime(value) {
        if (!value) return '—';
        var str = String(value).trim();
        if (!str) return '—';

        var isoMatch = str.match(/T(\d{1,2}):(\d{2})(?::(\d{2}))?/);
        if (isoMatch) {
            str = isoMatch[1] + ':' + isoMatch[2] + (isoMatch[3] ? ':' + isoMatch[3] : '');
        }

        // Accept "HH:mm" or "HH:mm:ss"
        var match = str.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
        if (!match) return str;

        var hh = parseInt(match[1], 10);
        var mm = parseInt(match[2], 10);
        if (Number.isNaN(hh) || Number.isNaN(mm)) return str;

        var ampm = hh >= 12 ? 'PM' : 'AM';
        var hour12 = hh % 12;
        if (hour12 === 0) hour12 = 12;
        return hour12 + ':' + String(mm).padStart(2, '0') + ' ' + ampm;
    }

    function buildTooltipHtml(event) {
        var props = event.extendedProps || {};
        var scheduler = props.scheduler_role || props.creator_role || props.scheduler_department || props.department || '—';
        var location = props.location || '—';
        var startTime = formatTime(props.start_time);
        var endTime = formatTime(props.end_time);
        var timeRange = (startTime === '—' && endTime === '—') ? '—' : (startTime + ' – ' + endTime);

        return (
            '<div class="tt-title">' + escapeHtml(event.title || 'Event') + '</div>' +
            '<div class="tt-row"><div class="tt-label">Scheduler</div><div class="tt-value">' + escapeHtml(scheduler) + '</div></div>' +
            '<div class="tt-row"><div class="tt-label">Time</div><div class="tt-value">' + escapeHtml(timeRange) + '</div></div>' +
            '<div class="tt-row"><div class="tt-label">Location</div><div class="tt-value">' + escapeHtml(location) + '</div></div>'
        );
    }

    function ensureTooltipEl() {
        if (activeTooltipEl) return activeTooltipEl;
        activeTooltipEl = document.createElement('div');
        activeTooltipEl.className = 'event-hover-tooltip';
        activeTooltipEl.setAttribute('role', 'tooltip');
        document.body.appendChild(activeTooltipEl);
        return activeTooltipEl;
    }

    function positionTooltip(el, clientX, clientY) {
        var offset = 12;
        var x = clientX + offset;
        var y = clientY + offset;

        el.style.left = x + 'px';
        el.style.top = y + 'px';

        // Clamp to viewport after render
        var rect = el.getBoundingClientRect();
        var maxLeft = window.innerWidth - rect.width - 12;
        var maxTop = window.innerHeight - rect.height - 12;
        var clampedLeft = Math.max(12, Math.min(x, maxLeft));
        var clampedTop = Math.max(12, Math.min(y, maxTop));
        el.style.left = clampedLeft + 'px';
        el.style.top = clampedTop + 'px';
    }

    function showTooltip(event, jsEvent) {
        var el = ensureTooltipEl();
        activeTooltipEventId = event.id;
        el.innerHTML = buildTooltipHtml(event);
        positionTooltip(el, jsEvent.clientX, jsEvent.clientY);
        requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });
    }

    function hideTooltip() {
        if (!activeTooltipEl) return;
        activeTooltipEl.classList.remove('is-visible');
        activeTooltipEventId = null;
    }

    document.addEventListener('mousemove', function (e) {
        if (!activeTooltipEl || !activeTooltipEventId) return;
        positionTooltip(activeTooltipEl, e.clientX, e.clientY);
    }, { passive: true });

    window.addEventListener('scroll', hideTooltip, { passive: true });
    window.addEventListener('resize', hideTooltip, { passive: true });

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listWeek'
        },
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short',
            hour12: true
        },
        slotLabelFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short',
            hour12: true
        },
        events: '/api/events',
        eventClick: function(info) {
            // Redirect to event details
            window.location.href = '/events/' + info.event.id;
        },
        eventMouseEnter: function(info) {
            showTooltip(info.event, info.jsEvent);
        },
        eventMouseLeave: function() {
            hideTooltip();
        },
        eventDidMount: function(info) {
            function calendarTextOnHex(hex) {
                if (!hex || typeof hex !== 'string') {
                    return '#ffffff';
                }
                var m = hex.trim().match(/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i);
                if (!m) {
                    return '#ffffff';
                }
                var r = parseInt(m[1], 16);
                var g = parseInt(m[2], 16);
                var b = parseInt(m[3], 16);
                var lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return lum > 0.55 ? '#000000' : '#ffffff';
            }

            var dept = info.event.extendedProps.department_color;

            // Remove legacy side accent (full department fill replaces it)
            info.el.style.setProperty('border-left', 'none', 'important');
            info.el.style.setProperty('box-shadow', 'none', 'important');

            if (dept) {
                var textColor = calendarTextOnHex(dept);
                info.el.style.setProperty('--fc-event-bg-color', dept, 'important');
                info.el.style.setProperty('--fc-event-border-color', dept, 'important');
                info.el.style.setProperty('--fc-event-text-color', textColor, 'important');
                return;
            }

            // No department color: use status colors (full fill via CSS variables)
            var statusBg = null;
            var statusText = null;
            if (info.event.extendedProps.status === 'approved') {
                statusBg = '#a3b18a';
                statusText = '#ffffff';
            } else if (info.event.extendedProps.status === 'pending') {
                statusBg = '#FFA500';
                statusText = '#000000';
            } else if (info.event.extendedProps.status === 'rejected') {
                statusBg = '#dc3545';
                statusText = '#ffffff';
            }

            if (statusBg) {
                info.el.style.setProperty('--fc-event-bg-color', statusBg, 'important');
                info.el.style.setProperty('--fc-event-border-color', statusBg, 'important');
                if (statusText) {
                    info.el.style.setProperty('--fc-event-text-color', statusText, 'important');
                }
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
