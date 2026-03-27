@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-calendar me-2"></i>
                    Event Details
                </h2>
                <div>
                    <a href="{{ route('events.index') }}" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>
                        Back to Events
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Event Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Event Title</h6>
                            <p class="h5">{{ $event->title }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Status</h6>
                            <span class="badge {{ $event->status_badge_class }} fs-6">
                                {{ ucfirst($event->status) }}
                            </span>
                        </div>
                    </div>

                    @if($event->description)
                    <hr>
                    <div>
                        <h6 class="text-muted">Description</h6>
                        <p>{{ $event->description }}</p>
                    </div>
                    @endif

                    <hr>
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-muted">Event Date</h6>
                            <p class="h6">{{ $event->event_date->format('F d, Y') }}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Start Time</h6>
                            <p class="h6">{{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">End Time</h6>
                            <p class="h6">{{ \Carbon\Carbon::parse($event->end_time)->format('g:i A') }}</p>
                        </div>
                    </div>

                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Location</h6>
                            <p class="h6">{{ $event->location }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Created By</h6>
                            <p class="h6">{{ $event->creator->name }}</p>
                        </div>
                    </div>

                    @if($event->approver)
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Approved By</h6>
                            <p class="h6">{{ $event->approver->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Approved At</h6>
                            <p class="h6">{{ $event->approved_at->format('F d, Y g:i A') }}</p>
                        </div>
                    </div>
                    @endif

                    @if($event->rejection_reason)
                    <hr>
                    <div>
                        <h6 class="text-muted">Rejection Reason</h6>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            {{ $event->rejection_reason }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-boxes me-2"></i>
                        Requested Items
                    </h5>
                </div>
                <div class="card-body">
                    @if($event->eventItems->count() > 0)
                        @foreach($event->eventItems as $eventItem)
                        <div class="mb-3 p-3 rounded border" 
                             style="background-color: #f8f9fa;">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    @php
                                        $inv = $eventItem->inventoryItem;
                                    @endphp
                                    <h6 class="mb-1">{{ $inv->name ?? 'Item removed' }}</h6>
                                    <small class="text-muted d-block">
                                        Requested: <strong>{{ $eventItem->quantity_requested }}</strong>
                                        @if($eventItem->quantity_approved)
                                            | Approved: <strong>{{ $eventItem->quantity_approved }}</strong>
                                        @endif
                                    </small>
                                    @php
                                        $availableQuantity = ($event->status === 'pending' && $inv)
                                            ? $inv->getAvailableQuantityForDate($event->event_date)
                                            : null;
                                    @endphp
                                    @if($event->status === 'pending' && $availableQuantity !== null)
                                        <small class="d-block mt-1">
                                            <span class="badge bg-info">
                                                Available on {{ $event->event_date->format('M d, Y') }}: {{ $availableQuantity }}
                                            </span>
                                        </small>
                                    @endif
                                    @if($eventItem->notes)
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i> {{ $eventItem->notes }}
                                            </small>
                                        </div>
                                    @endif
                                </div>
                                <span class="badge {{ $eventItem->status_badge_class }} ms-2">
                                    {{ ucfirst($eventItem->status) }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center">No items requested for this event.</p>
                    @endif
                </div>
            </div>

            @if(Auth::user()->canApproveEvents() && $event->status === 'pending')
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-gavel me-2"></i>
                        Event Approval
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('events.approve', $event) }}" method="POST" id="approveForm">
                        @csrf
                        
                        @if($event->eventItems->count() > 0)
                            <div class="mb-3">
                                <h6 class="mb-3">
                                    <i class="fas fa-boxes me-2"></i>
                                    Item-Level Approval
                                </h6>
                                <p class="text-muted small mb-3">
                                    You can approve the event date while rejecting specific items that are not available.
                                </p>
                                
                                @foreach($event->eventItems as $eventItem)
                                    @php
                                        $availableQuantity = $eventItem->inventoryItem->getAvailableQuantityForDate($event->event_date);
                                        $canApprove = $availableQuantity > 0;
                                    @endphp
                                    <div class="card mb-3 border">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">{{ $eventItem->inventoryItem->name }}</h6>
                                                    <small class="text-muted">
                                                        Requested: <strong>{{ $eventItem->quantity_requested }}</strong>
                                                    </small>
                                                    <div class="mt-2">
                                                        <span class="badge {{ $canApprove ? 'bg-success' : 'bg-danger' }}">
                                                            Available: {{ $availableQuantity }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input item-action" 
                                                           type="radio" 
                                                           name="event_items[{{ $eventItem->id }}][action]" 
                                                           id="approve_{{ $eventItem->id }}" 
                                                           value="approve"
                                                           {{ $canApprove ? 'checked' : 'disabled' }}
                                                           data-item-id="{{ $eventItem->id }}">
                                                    <label class="form-check-label" for="approve_{{ $eventItem->id }}">
                                                        <strong>Approve</strong>
                                                        @if($canApprove)
                                                            <small class="text-muted">(up to {{ $availableQuantity }} available)</small>
                                                        @else
                                                            <small class="text-danger">(Not available)</small>
                                                        @endif
                                                    </label>
                                                </div>
                                                
                                                @if($canApprove)
                                                    <div class="ms-4 mb-2">
                                                        <label class="form-label small">Quantity to Approve:</label>
                                                        <input type="number" 
                                                               class="form-control form-control-sm quantity-approved-input" 
                                                               name="event_items[{{ $eventItem->id }}][quantity_approved]" 
                                                               value="{{ min($eventItem->quantity_requested, $availableQuantity) }}"
                                                               min="1" 
                                                               max="{{ min($eventItem->quantity_requested, $availableQuantity) }}"
                                                               data-item-id="{{ $eventItem->id }}">
                                                    </div>
                                                @endif
                                                
                                                <div class="form-check">
                                                    <input class="form-check-input item-action" 
                                                           type="radio" 
                                                           name="event_items[{{ $eventItem->id }}][action]" 
                                                           id="reject_{{ $eventItem->id }}" 
                                                           value="reject"
                                                           {{ !$canApprove ? 'checked' : '' }}
                                                           data-item-id="{{ $eventItem->id }}">
                                                    <label class="form-check-label" for="reject_{{ $eventItem->id }}">
                                                        <strong>Reject</strong> <small class="text-muted">(Item not available for this date)</small>
                                                    </label>
                                                </div>
                                                
                                                <div class="ms-4 mt-2">
                                                    <label class="form-label small">Reason (optional):</label>
                                                    <textarea class="form-control form-control-sm" 
                                                              name="event_items[{{ $eventItem->id }}][notes]" 
                                                              rows="2" 
                                                              placeholder="e.g., Item not available on this date"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success w-100" 
                                    onclick="return confirm('Are you sure you want to approve this event? Items marked as rejected will not be approved.')">
                                <i class="fas fa-check me-2"></i>
                                Approve Event
                            </button>
                            
                            <button type="button" class="btn btn-danger w-100" 
                                    data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="fas fa-times me-2"></i>
                                Reject Entire Event
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reject Modal -->
            <div class="modal fade" id="rejectModal" tabindex="-1">
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
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle item action radio button changes
    const itemActions = document.querySelectorAll('.item-action');
    
    itemActions.forEach(radio => {
        radio.addEventListener('change', function() {
            const itemId = this.dataset.itemId;
            const quantityInput = document.querySelector(`.quantity-approved-input[data-item-id="${itemId}"]`);
            
            if (this.value === 'approve' && quantityInput) {
                quantityInput.disabled = false;
                quantityInput.required = true;
            } else if (this.value === 'reject' && quantityInput) {
                quantityInput.disabled = true;
                quantityInput.required = false;
            }
        });
    });
    
    // Initialize disabled state for rejected items
    itemActions.forEach(radio => {
        if (radio.value === 'reject' && radio.checked) {
            const itemId = radio.dataset.itemId;
            const quantityInput = document.querySelector(`.quantity-approved-input[data-item-id="${itemId}"]`);
            if (quantityInput) {
                quantityInput.disabled = true;
                quantityInput.required = false;
            }
        }
    });
});
</script>
@endsection
