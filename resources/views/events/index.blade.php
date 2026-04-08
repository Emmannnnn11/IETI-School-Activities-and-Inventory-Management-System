@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-calendar me-2"></i>
                    Events
                </h2>
                @if(Auth::user()->canCreateEvents())
                <a href="{{ route('events.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    Create New Event
                </a>
                @endif
            </div>
        </div>
    </div>

    @php
        $eventsCollection = $events->getCollection();
        $isDesc = ($direction ?? 'asc') === 'desc';
        $nextTitleDirection = (($sort ?? 'event_date') === 'title' && !$isDesc) ? 'desc' : 'asc';
        $nextDateDirection = (($sort ?? 'event_date') === 'event_date' && !$isDesc) ? 'desc' : 'asc';
    @endphp

    {{-- Show pending events for admin approval --}}
    @if(Auth::user()->canApproveEvents())
        @php
            // Filter pending events from the collection
            $pendingEvents = $eventsCollection->filter(function($event) {
                return $event->status === 'pending';
            });
        @endphp
        
        @if($pendingEvents->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0 text-dark">
                            <i class="fas fa-hourglass-half me-2"></i>
                            Events Pending Approval ({{ $pendingEvents->count() }})
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Role</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingEvents as $event)
                                    <tr class="table-warning">
                                        <td>
                                            <div>
                                                <h6 class="mb-1">{{ $event->title }}</h6>
                                                @if($event->description)
                                                <small class="text-muted">{{ Str::limit($event->description, 50) }}</small>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>{{ $event->date_range_label }}</strong><br>
                                                <small class="text-muted">
                                                    {{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }} - 
                                                    {{ \Carbon\Carbon::parse($event->end_time)->format('g:i A') }}
                                                </small>
                                            </div>
                                        </td>
                                        <td>{{ $event->location }}</td>
                                        <td>{{ $event->scheduler_role_label }}</td>
                                        <td>{{ $event->creator->name }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('events.show', $event) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <form action="{{ route('events.approve', $event) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success" 
                                                            onclick="return confirm('Are you sure you want to approve this event?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" data-bs-target="#rejectModal{{ $event->id }}">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal{{ $event->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reject Event</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="{{ route('events.reject', $event) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                                                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" 
                                                                      rows="3" required minlength="10" maxlength="500" placeholder="Please provide a reason for rejecting this event..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Reject Event</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            All Events
                        </h5>
                        <form method="GET" action="{{ route('events.index') }}" class="row g-2 align-items-center">
                            <input type="hidden" name="sort" value="{{ $sort ?? 'event_date' }}">
                            <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
                            <div class="col-auto">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search event or venue" value="{{ $search ?? '' }}">
                            </div>
                            <div class="col-auto">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All statuses</option>
                                    <option value="approved" {{ ($status ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                @if(($search ?? '') !== '' || ($status ?? '') !== '')
                                    <a href="{{ route('events.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    @if($events->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>
                                        <a href="{{ route('events.index', array_merge(request()->query(), ['sort' => 'title', 'direction' => $nextTitleDirection])) }}" class="text-decoration-none text-white">
                                            Event
                                            @if(($sort ?? 'event_date') === 'title')
                                                <i class="fas fa-sort-{{ $isDesc ? 'down' : 'up' }} ms-1"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('events.index', array_merge(request()->query(), ['sort' => 'event_date', 'direction' => $nextDateDirection])) }}" class="text-decoration-none text-white">
                                            Date & Time
                                            @if(($sort ?? 'event_date') === 'event_date')
                                                <i class="fas fa-sort-{{ $isDesc ? 'down' : 'up' }} ms-1"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>Location</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($events as $event)
                                <tr>
                                    <td>
                                        <div>
                                            <h6 class="mb-1">{{ $event->title }}</h6>
                                            @if($event->description)
                                            <small class="text-muted">{{ Str::limit($event->description, 50) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $event->date_range_label }}</strong><br>
                                            <small class="text-muted">
                                                {{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }} - 
                                                {{ \Carbon\Carbon::parse($event->end_time)->format('g:i A') }}
                                            </small>
                                        </div>
                                    </td>
                                    <td>{{ $event->location }}</td>
                                    <td>{{ $event->scheduler_role_label }}</td>
                                    <td>
                                        <span class="badge {{ $event->status_badge_class }}">
                                            {{ ucfirst($event->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $event->creator->name }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('events.show', $event) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if($event->created_by === Auth::id() || Auth::user()->isAdmin())
                                            <a href="{{ route('events.edit', $event) }}" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endif
                                            @if(Auth::user()->canApproveEvents() && $event->status === 'pending')
                                            <form action="{{ route('events.approve', $event) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" 
                                                        onclick="return confirm('Are you sure you want to approve this event?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#rejectModal{{ $event->id }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            @endif
                                            @if($event->status === 'rejected' && Auth::user()->isAdmin())
                                            <form action="{{ route('events.destroy', $event) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Delete this rejected event? This action cannot be undone, but a history entry will be created.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                <!-- Reject Modal -->
                                @if(Auth::user()->canApproveEvents() && $event->status === 'pending')
                                <div class="modal fade" id="rejectModal{{ $event->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Event</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="{{ route('events.reject', $event) }}" method="POST">
                                                @csrf
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                                                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" 
                                                                  rows="3" required minlength="10" maxlength="500" placeholder="Please provide a reason for rejecting this event..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Reject Event</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $events->links('pagination::bootstrap-5') }}
                    </div>
                    @else
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No events found</h5>
                        @if(Auth::user()->canCreateEvents())
                        <p class="text-muted">Create your first event to get started!</p>
                        <a href="{{ route('events.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            Create Event
                        </a>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
