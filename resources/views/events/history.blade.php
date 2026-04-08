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

    @php
        $isDesc = ($filters['direction'] ?? 'desc') === 'desc';
        $nextTitleDirection = (($filters['sort'] ?? 'event_date') === 'title' && !$isDesc) ? 'desc' : 'asc';
        $nextDateDirection = (($filters['sort'] ?? 'event_date') === 'event_date' && !$isDesc) ? 'desc' : 'asc';
        $hasHistoryRecords = $history->total() > 0;
        $selectedStatus = $filters['status'] ?? '';
    @endphp

    <div class="card shadow-sm border mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('events.history') }}" class="row g-3 align-items-end">
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'event_date' }}">
                <input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'desc' }}">

                <div class="col-12 col-md-6 col-lg-3">
                    <label for="search" class="form-label small text-muted">Search</label>
                    <input type="text" name="search" id="search" class="form-control form-control-sm"
                        placeholder="Search event name"
                        value="{{ old('search', $filters['search'] ?? '') }}">
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label for="event_date" class="form-label small text-muted">Date</label>
                    <input type="date" name="event_date" id="event_date" class="form-control form-control-sm"
                        value="{{ old('event_date', $filters['event_date'] ?? '') }}">
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label for="status" class="form-label small text-muted">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option value="completed" {{ $selectedStatus === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="rejected" {{ $selectedStatus === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label for="role" class="form-label small text-muted">Role</label>
                    <select name="role" id="role" class="form-select form-select-sm">
                        <option value="">All roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" {{ ($filters['role'] ?? '') === $role ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $role)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-search me-1"></i> Apply
                    </button>
                    <a
                        href="{{ $hasHistoryRecords ? route('events.history.export', request()->only(['search', 'event_date', 'status', 'role', 'sort', 'direction'])) : '#' }}"
                        class="btn btn-success btn-sm flex-fill {{ $hasHistoryRecords ? '' : 'disabled' }}"
                        @if(!$hasHistoryRecords) aria-disabled="true" tabindex="-1" title="No records to export" @endif
                    >
                        <i class="fas fa-file-export me-1"></i> Export
                    </a>
                    <a href="{{ route('events.history') }}" class="btn btn-outline-secondary btn-sm flex-fill">Clear</a>
                </div>
            </form>
        </div>
    </div>

    @if($history->count() > 0)
        <div class="card shadow-sm border">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>
                                    <a href="{{ route('events.history', array_merge(request()->query(), ['sort' => 'title', 'direction' => $nextTitleDirection])) }}" class="text-decoration-none text-dark">
                                        Event Name
                                        @if(($filters['sort'] ?? 'event_date') === 'title')
                                            <i class="fas fa-sort-{{ $isDesc ? 'down' : 'up' }} ms-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('events.history', array_merge(request()->query(), ['sort' => 'event_date', 'direction' => $nextDateDirection])) }}" class="text-decoration-none text-dark">
                                        Date
                                        @if(($filters['sort'] ?? 'event_date') === 'event_date')
                                            <i class="fas fa-sort-{{ $isDesc ? 'down' : 'up' }} ms-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>Venue</th>
                                <th>Status</th>
                                <th class="text-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($history as $entry)
                                @php
                                    $data = is_array($entry->event_data ?? null) ? $entry->event_data : [];
                                    $statusLabel = ucfirst((string) ($entry->status ?? ''));
                                    $statusClass = ($entry->status ?? '') === 'completed'
                                        ? 'bg-success'
                                        : (($entry->status ?? '') === 'rejected' ? 'bg-danger' : 'bg-secondary');
                                    $canViewEvent = !empty($entry->event_id) && in_array((int) $entry->event_id, $viewableEventIds ?? [], true);
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $entry->title }}</td>
                                    <td>{{ optional($entry->event_date)->format('M d, Y') ?? '—' }}</td>
                                    <td>{{ $data['location'] ?? '—' }}</td>
                                    <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            @if($canViewEvent)
                                                <a href="{{ route('events.show', $entry->event_id) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Event no longer exists">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex justify-content-center">
            {{ $history->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No history records yet</h5>
                <p class="text-muted mb-0">Completed or rejected events will appear here once available.</p>
            </div>
        </div>
    @endif
</div>
@endsection
