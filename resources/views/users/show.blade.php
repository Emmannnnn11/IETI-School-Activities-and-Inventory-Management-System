@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-user me-2"></i>
                    User Details
                </h2>
                <div>
                    <a href="{{ route('users.index') }}" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>
                        Back to Users
                    </a>
                    @can('update', $user)
                    <a href="{{ route('users.edit', $user) }}" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>
                        Edit User
                    </a>
                    @endcan
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
                        User Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <h6 class="text-muted">Name</h6>
                            <p class="h6">{{ $user->name }}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Email</h6>
                            <p class="h6">{{ $user->email }}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Role</h6>
                            <span class="badge bg-primary">{{ $user->role_label }}</span>
                        </div>
                    </div>

                    @if($user->employee_id || $user->role)
                    <hr>
                    <div class="row mb-3">
                        @if($user->employee_id)
                        <div class="col-md-6">
                            <h6 class="text-muted">Employee ID</h6>
                            <p class="h6">{{ $user->employee_id }}</p>
                        </div>
                        @endif
                        @if($user->role)
                        <div class="col-md-6">
                            <h6 class="text-muted">Role (Display)</h6>
                            <p class="h6">{{ $user->role_label }}</p>
                        </div>
                        @endif
                    </div>
                    @endif

                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Created At</h6>
                            <p class="h6">{{ $user->created_at->format('F d, Y g:i A') }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Last Updated</h6>
                            <p class="h6">{{ $user->updated_at->format('F d, Y g:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

