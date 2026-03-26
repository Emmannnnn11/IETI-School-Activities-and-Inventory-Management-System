@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-boxes me-2"></i>
                    Inventory Management
                </h2>
                <div class="d-flex gap-2">
                    @php
                        $canRefresh = in_array(Auth::user()->role, ['admin', 'Head Maintenance']);
                    @endphp
                    @if($canRefresh)
                    <a href="{{ route('inventory.index', ['refresh' => 1]) }}" class="btn btn-secondary">
                        <i class="fas fa-sync-alt me-2"></i>
                        Refresh Borrowed Items
                    </a>
                    @endif
                    @php
                        $canCreate = empty(Auth::user()->getAllowedInventoryCategories());
                    @endphp
                    @if($canCreate)
                    <a href="{{ route('inventory.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        Add New Item
                    </a>
                    @endif

                    <a
                        href="{{ $hasBorrowingRecords ? route('inventory.export') : '#' }}"
                        class="btn btn-success {{ $hasBorrowingRecords ? '' : 'disabled' }}"
                        @if(!$hasBorrowingRecords) aria-disabled="true" tabindex="-1" title="No records to export" @endif
                    >
                        <i class="fas fa-file-export me-2"></i>
                        Export
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($inventoryItems->count() > 0)
        @foreach($inventoryItems as $category => $items)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-folder me-2"></i>
                            {{ ucfirst($category) }} <small class="text-muted">({{ $items->count() }} items)</small>
                        </h5>
                        @php
                            $canEditCategory = in_array(Auth::user()->role, ['admin', 'Head Maintenance']);
                        @endphp
                        @if($canEditCategory)
                        <a href="{{ route('inventory.category.edit', ['category' => urlencode($category)]) }}" 
                           class="btn btn-sm btn-edit-category">
                            <i class="fas fa-edit me-1"></i> Edit Category
                        </a>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($items as $item)
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">{{ $item->name }}</h6>
                                            <span class="badge {{ $item->status_badge_class }}">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </div>
                                        
                                        @if($item->description)
                                        <p class="card-text text-muted small mb-2">{{ Str::limit($item->description, 100) }}</p>
                                        @endif

                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-box me-1"></i>
                                                Available: <strong>{{ $item->quantity_available }}</strong> / {{ $item->quantity_total }}
                                            </small>
                                        </div>

                                        @if($item->location)
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                {{ $item->location }}
                                            </small>
                                        </div>
                                        @endif

                                        @if($item->eventItems->count() > 0)
                                        <div class="mb-2">
                                            <small class="text-info">
                                                <i class="fas fa-calendar me-1"></i>
                                                Used in {{ $item->eventItems->count() }} event(s)
                                            </small>
                                        </div>
                                        @endif

                                        <div class="mt-3">
                                            <a href="{{ route('inventory.show', $item) }}" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="{{ route('inventory.edit', $item) }}" class="btn btn-sm btn-outline-warning me-1">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            @php
                                                $canDelete = in_array(Auth::user()->role, ['admin', 'Head Maintenance']);
                                            @endphp
                                            @if($canDelete)
                                            <form action="{{ route('inventory.destroy', $item) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Delete this inventory item? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    @else
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Inventory Items</h5>
                        <p class="text-muted">Start by adding your first inventory item.</p>
                        <a href="{{ route('inventory.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            Add New Item
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

