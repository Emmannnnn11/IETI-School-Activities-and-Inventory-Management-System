@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-user me-2"></i>
                    My Profile
                </h2>
                <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i>
                    Edit Profile
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Profile Information
                    </h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

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

                    @if($user->employee_id)
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Employee ID</h6>
                            <p class="h6">{{ $user->employee_id }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Role (Display)</h6>
                            <p class="h6">{{ $user->role_label }}</p>
                        </div>
                    </div>
                    @endif

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>
                            Edit Profile
                        </a>
                        <a href="{{ route('profile.password') }}" class="btn btn-warning text-dark">
                            <i class="fas fa-key me-2"></i>
                            Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

