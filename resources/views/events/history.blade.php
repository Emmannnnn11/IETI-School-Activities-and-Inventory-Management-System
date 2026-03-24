@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary">
            <i class="fas fa-history me-2"></i>
            Event History
        </h2>
        <a href="{{ route('events.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>
            Back to Events
        </a>
    </div>

    <div class="mb-3">
        <h5 class="text-muted mb-0">
            <i class="fas fa-archive me-2"></i>
            Archived Actions
        </h5>
    </div>

    <div class="card shadow-sm border mb-4">
        <div class="card-body">
            <form method="get" action="{{ route('events.history') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-lg-3">
                    <label for="search" class="form-label small text-muted">
                        <i class="fas fa-search me-1"></i> Event name
                    </label>
                    <input type="text" name="search" id="search" class="form-control form-control-sm"
                        placeholder="Search by event name..."
                        value="{{ old('search', $filters['search'] ?? '') }}">
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label for="event_date" class="form-label small text-muted">
                        <i class="fas fa-calendar-day me-1"></i> Date
                    </label>
                    <input type="date" name="event_date" id="event_date" class="form-control form-control-sm"
                        value="{{ old('event_date', $filters['event_date'] ?? '') }}">
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label for="status" class="form-label small text-muted">
                        <i class="fas fa-flag me-1"></i> Status
                    </label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option value="approved" {{ ($filters['status'] ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="rejected" {{ ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label for="department" class="form-label small text-muted">
                        <i class="fas fa-building me-1"></i> Department
                    </label>
                    <select name="department" id="department" class="form-select form-select-sm">
                        <option value="">All departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}" {{ ($filters['department'] ?? '') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    @php $hasHistoryRecords = $history->total() > 0; @endphp
                    <a
                        href="{{ $hasHistoryRecords ? route('events.history.export', request()->only(['search', 'event_date', 'status', 'department'])) : '#' }}"
                        class="btn btn-success btn-sm w-100 {{ $hasHistoryRecords ? '' : 'disabled' }}"
                        @if(!$hasHistoryRecords) aria-disabled="true" tabindex="-1" title="No records to export" @endif
                    >
                        <i class="fas fa-file-export me-1"></i> Export
                    </a>
                </div>
                @if(($filters['search'] ?? '') !== '' || ($filters['event_date'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['department'] ?? '') !== '')
                <div class="col-12 col-lg-1">
                    <a href="{{ route('events.history') }}" class="btn btn-secondary btn-sm w-100">Clear</a>
                </div>
                @endif
            </form>
            @if(!$hasHistoryRecords)
                <small class="text-muted d-block mt-2">
                    No filtered records available to export.
                </small>
            @endif
        </div>
    </div>

    @if($history->count() > 0)
        <div class="row g-3">
            @foreach($history as $entry)
            @php
                $data = is_array($entry->event_data ?? null) ? $entry->event_data : [];
                $location = $data['location'] ?? null;
                $startTime = $data['start_time'] ?? null;
                $endTime = $data['end_time'] ?? null;
                $timeStr = null;
                if ($startTime || $endTime) {
                    try {
                        $start = $startTime instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::parse($startTime)->format('g:i A') : (\Illuminate\Support\Carbon::parse($startTime)->format('g:i A') ?? '');
                    } catch (\Exception $e) { $start = ''; }
                    try {
                        $end = $endTime instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::parse($endTime)->format('g:i A') : (\Illuminate\Support\Carbon::parse($endTime)->format('g:i A') ?? '');
                    } catch (\Exception $e) { $end = ''; }
                    $timeStr = trim($start . ($end ? ' – ' . $end : ''));
                }
                $creatorName = null;
                if (isset($entry->creator) && $entry->creator) {
                    $creatorName = $entry->creator->name;
                } elseif (!empty($data['created_by'])) {
                    $u = \App\Models\User::find($data['created_by']);
                    $creatorName = $u ? $u->name : 'User #' . $data['created_by'];
                }
                $performerName = null;
                if (isset($entry->approver) && $entry->approver) {
                    $performerName = $entry->approver->name;
                } elseif (isset($entry->performedBy) && $entry->performedBy) {
                    $performerName = $entry->performedBy->name;
                } elseif (!empty($data['approved_by'])) {
                    $u = \App\Models\User::find($data['approved_by']);
                    $performerName = $u ? $u->name : 'User #' . $data['approved_by'];
                }
                if (!$performerName) {
                    $performerName = 'System';
                }
                $entryId = $entry->id ?? ('entry_' . $entry->event_id . '_' . ($entry->performed_at ? $entry->performed_at->timestamp : 0));
            @endphp
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0 text-dark">
                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                {{ $entry->title }}
                            </h6>
                            <span class="badge
                                @if($entry->status === 'approved') bg-success
                                @elseif($entry->status === 'pending') bg-warning text-dark
                                @else bg-danger
                                @endif">
                                {{ ucfirst($entry->status) }}
                            </span>
                        </div>
                        <small class="text-muted d-block mb-2">Ref #{{ $entry->event_id ?? 'N/A' }}</small>

                        <ul class="list-unstyled small mb-0">
                            <li class="mb-1">
                                <i class="fas fa-calendar-day text-muted me-2" style="width: 1.25rem;"></i>
                                <strong>Date:</strong> {{ optional($entry->event_date)->format('M d, Y') ?? '—' }}
                            </li>
                            @if($timeStr)
                            <li class="mb-1">
                                <i class="fas fa-clock text-muted me-2" style="width: 1.25rem;"></i>
                                <strong>Time:</strong> {{ $timeStr }}
                            </li>
                            @endif
                            @if($location)
                            <li class="mb-1">
                                <i class="fas fa-map-marker-alt text-muted me-2" style="width: 1.25rem;"></i>
                                <strong>Location:</strong> {{ $location }}
                            </li>
                            @endif
                            <li class="mb-1">
                                <i class="fas fa-user text-muted me-2" style="width: 1.25rem;"></i>
                                <strong>Created by:</strong> {{ $creatorName ?? '—' }}
                            </li>
                        </ul>

                        <div class="mt-3 pt-2 border-top">
                            <button class="btn btn-sm btn-secondary w-100" type="button"
                                data-bs-toggle="collapse" data-bs-target="#historyLog{{ $entryId }}"
                                aria-expanded="false" aria-controls="historyLog{{ $entryId }}">
                                <i class="fas fa-chevron-down me-1"></i> Show History
                            </button>
                            <div class="collapse mt-2" id="historyLog{{ $entryId }}">
                                <div class="small bg-light rounded p-2">
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        @if($entry->action === 'approved')
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                        @elseif($entry->action === 'rejected')
                                            <i class="fas fa-times-circle text-danger me-2"></i>
                                        @elseif($entry->action === 'deleted')
                                            <i class="fas fa-trash-alt text-secondary me-2"></i>
                                        @elseif($entry->action === 'submitted')
                                            <i class="fas fa-paper-plane text-info me-2"></i>
                                        @else
                                            <i class="fas fa-plus-circle text-primary me-2"></i>
                                        @endif
                                        <span class="text-capitalize">{{ str_replace('_', ' ', $entry->action) }}</span>
                                        <span class="text-muted">by {{ $performerName }}</span>
                                        <span class="text-muted">on {{ optional($entry->performed_at)->format('M d, Y \a\t g:i A') ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $history->links() }}
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No history records yet</h5>
                <p class="text-muted mb-0">Finished events (approved or rejected) will appear here once available.</p>
            </div>
        </div>
    @endif
</div>
@endsection
