<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IETI School Activities Scheduling and Inventory Management System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    
    <style>
        :root {
            --ieti-yellow: #FFD700;
            --ieti-green: #a3b18a;
            --ieti-black: #000000;
            --ieti-white: #FFFFFF;
        }

        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f8f9fa;
            background-image: url("{{ asset('IETI-background.png') }}");
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
    
        }

        .navbar-brand {
            font-weight: 600;   
            color: var(--ieti-white) !important;
        }

        .navbar {
            background: var(--ieti-yellow) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        
        .navbar-brand img {
            border-radius: 50%;
            border: 3px solid var(--ieti-green);
            width: 40px;
            height: 40px;
            object-fit: cover;
        }

        .sidebar-brand {
            padding: 18px 10px 10px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .sidebar-brand img {
            border-radius: 50%;
            border: 0px solid var(--ieti-green);
            width: 110px;
            height: 110px;
            object-fit: cover;
            background: transparent;
        }

        .navbar-nav .nav-link {
            color: var(--ieti-black) !important;
            font-weight: 500;
        }

        .navbar-nav .nav-link:hover {
            color: var(--ieti-green) !important;
        }

        .sidebar {
            background: var(--ieti-green);
            min-height: calc(100vh - 56px);
            height: auto;
            position: sticky;
            top: 56px;
            overflow-y: auto;
            box-shadow: 2px 0 4px rgba(0,0,0,0.1);
            z-index: 1020;
            transition: width 0.3s ease, transform 0.3s ease;
            width: 100%;
        }
        
        .sidebar.collapsed {
            width: 0;
            overflow: hidden;
            transform: translateX(-100%);
        }
        
        .sidebar-wrapper {
            flex: 0 0 260px;
            max-width: 260px;
            width: 260px;
            transition: all 0.3s ease;
            overflow: hidden;
            display: flex;
        }
        
        .sidebar-wrapper.collapsed {
            width: 0 !important;
            padding: 0 !important;
            min-width: 0 !important;
            max-width: 0 !important;
            flex: 0 0 0 !important;
        }
        
        .main-content-wrapper {
            flex: 1 1 auto;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            transition: all 0.3s ease;
        }
        
        #app .container-fluid {
            padding-left: 0;
            padding-right: 0;
        }
        
        .app-shell {
            display: flex;
            width: 100%;
            min-height: calc(100vh - 56px);
            align-items: stretch;
        }
        
        .sidebar nav {
            padding-top: 0;
        }
        
        @media (max-width: 991.98px) {
            .app-shell {
                flex-direction: column;
            }
            
            .sidebar-wrapper {
                width: 100%;
                max-width: 100%;
                flex: 0 0 auto;
            }
            
            .sidebar {
                position: relative;
                top: 0;
                min-height: auto;
                height: auto;
            }
        }
        
        .main-content-wrapper.expanded {
            flex: 1 1 100% !important;
            max-width: 100% !important;
        }
        
        .sidebar-toggle-btn {
            background: var(--ieti-green);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        
        .sidebar-toggle-btn:hover {
            background: #1e7b1e;
            transform: scale(1.05);
        }
        
        .main-content-wrapper {
            transition: width 0.3s ease;
        }

        .sidebar .nav-link {
            color: var(--ieti-white) !important;
            padding: 12px 20px;
            border-radius: 5px;
            margin: 2px 10px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--ieti-yellow) !important;
        }

        .sidebar .nav-link.active {
            background-color: var(--ieti-yellow);
            color: var(--ieti-black) !important;
            font-weight: 600;
        }

        .main-content {
            padding: 20px;
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
        }

        .card-header {
            background: var(--ieti-green);
            color: var(--ieti-white);
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--ieti-green);
            border: none;
            border-radius: 5px;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: #1e7b1e;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--ieti-yellow);
            border: none;
            color: var(--ieti-black);
            font-weight: 500;
        }

        .btn-warning:hover {
            background: #FFA500;
            color: var(--ieti-black);
        }

        /* Solid secondary buttons for better readability (e.g. Back to Events) */
        .btn-secondary {
            background-color: #495057;
            border: none;
            color: #fff;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background-color: #343a40;
            color: #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .badge-success {
            background-color: var(--ieti-green);
        }

        .badge-warning {
            background-color: #FFA500;
            color: var(--ieti-black);
        }

        .badge-danger {
            background-color: #dc3545;
        }

        .event-approved {
            background-color: #a3b18a !important;
            color: var(--ieti-white) !important;
        }

        .event-pending {
            background-color: #FFA500 !important;
            color: var(--ieti-black) !important;
        }

        .event-rejected {
            background-color: #dc3545 !important;
            color: var(--ieti-white) !important;
        }

        .fc-event {
            border-radius: 5px !important;
            border: none !important;
        }

        .fc-toolbar-title {
            color: var(--ieti-green) !important;
            font-weight: 600 !important;
        }

        .fc-button-primary {
            background-color: var(--ieti-green) !important;
            border-color: var(--ieti-green) !important;
        }

        .fc-button-primary:hover {
            background-color: #1e7b1e !important;
            border-color: #1e7b1e !important;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead th {
            background-color: var(--ieti-green);
            color: var(--ieti-white);
            border: none;
            font-weight: 600;
        }

        .form-control:focus {
            border-color: var(--ieti-green);
            box-shadow: 0 0 0 0.2rem rgba(34, 139, 34, 0.25);
        }

        .form-select:focus {
            border-color: var(--ieti-green);
            box-shadow: 0 0 0 0.2rem rgba(34, 139, 34, 0.25);
        }

        /* Page title headings - make white for better readability */
        h2.text-primary {
            color: var(--ieti-white) !important;
        }

        /* Button hover shadow animations */
        .btn:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            box-shadow: 0 4px 12px rgba(163, 177, 138, 0.4);
        }

        .btn-outline-primary:hover {
            box-shadow: 0 4px 12px rgba(163, 177, 138, 0.4);
            transform: translateY(-2px);
        }

        .btn-outline-success:hover {
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
            transform: translateY(-2px);
        }

        .btn-outline-danger:hover {
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
            transform: translateY(-2px);
        }

        .btn-outline-warning:hover {
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
            transform: translateY(-2px);
        }

        .btn-success:hover {
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .btn-danger:hover {
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        .btn-warning:hover {
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
        }

        .sidebar-toggle-btn:hover {
            box-shadow: 0 4px 12px rgba(163, 177, 138, 0.4);
        }

        /* Dedicated style for all "Edit Category" actions */
        .btn-edit-category {
            background-color: var(--ieti-yellow);
            border: 1px solid var(--ieti-yellow);
            color: var(--ieti-black);
            font-weight: 500;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .btn-edit-category:hover,
        .btn-edit-category:focus,
        .btn-edit-category:active {
            background-color: var(--ieti-yellow);
            border-color: var(--ieti-yellow);
            color: var(--ieti-black);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.18);
            transform: translateY(-1px);
        }

        /* Compact pagination controls (e.g., Event History) */
        .ietip-history-pagination nav {
            width: auto !important;
            max-width: 100%;
            justify-content: center !important;
        }

        .ietip-history-pagination .pagination {
            margin: 0;
            display: inline-flex !important;
            flex-wrap: wrap !important;
            gap: 0.25rem 0.35rem !important;
            width: auto !important;
            justify-content: center !important;
        }

        .ietip-history-pagination .page-item {
            flex: 0 0 auto !important;
            width: auto !important;
        }

        .ietip-history-pagination .page-link {
            flex: 0 0 auto !important;
            width: auto !important;
            height: auto !important;
            padding: 0.18rem 0.55rem !important;
            font-size: 0.875rem !important;
            line-height: 1 !important;
            min-height: 0 !important;
            min-width: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            box-sizing: border-box;
        }

        .ietip-history-pagination .page-link:focus {
            box-shadow: 0 0 0 0.2rem rgba(163, 177, 138, 0.25);
        }

        @media (max-width: 576px) {
            .ietip-history-pagination .page-link {
                padding: 0.15rem 0.48rem !important;
                font-size: 0.8125rem !important;
                min-width: 1.9rem;
            }
        }
    </style>
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
            <button class="sidebar-toggle-btn" id="sidebarToggle" type="button" title="Toggle Sidebar">
                <i class="fas fa-bars" id="sidebarToggleIcon"></i>
            </button>
            <a class="navbar-brand d-flex align-items-center" href="{{ url('/home') }}">
                   IETI School Activities Scheduling and Inventory Management System
            </a>

                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto">
                        @auth
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i>
                                {{ Auth::user()->name }}
                                <span class="badge bg-secondary ms-1">{{ ucfirst(str_replace('_', ' ', Auth::user()->role)) }}</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('profile.index') }}">
                                    <i class="fas fa-user me-1"></i> My Profile
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                        @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-0">
            <div class="app-shell">
                <div class="sidebar-wrapper" id="sidebarWrapper">
                    <div class="sidebar" id="sidebar">
                            <div class="sidebar-brand">
                                <img src="{{ asset('Logo.png') }}" alt="IETI Logo">
                            </div>
                        <nav class="nav flex-column">
                            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                                <i class="fas fa-home me-2"></i> Dashboard
                            </a>
                            <a class="nav-link {{ request()->routeIs('events.*') ? 'active' : '' }}" href="{{ route('events.index') }}">
                                <i class="fas fa-calendar me-2"></i> Events
                            </a>
                            @auth
                            @if(Auth::user()->isAdmin())
                            <a class="nav-link {{ request()->routeIs('events.history') ? 'active' : '' }}" href="{{ route('events.history') }}">
                                <i class="fas fa-history me-2"></i> Event History
                            </a>
                            @endif
                            @if(Auth::user()->canManageInventory())
                            <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                                <i class="fas fa-boxes me-2"></i> Inventory
                            </a>
                            @endif
                            @if(Auth::user()->isAdmin())
                            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                            @endif
                            @endauth
                        </nav>
                    </div>
                </div>
                <div class="main-content-wrapper" id="mainContentWrapper">
                    <main class="main-content">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert" style="margin-top: 15px; margin-bottom: 20px;">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Success!</strong> {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <script>
                                // Auto-dismiss success messages after 5 seconds
                                setTimeout(function() {
                                    var alert = document.querySelector('.alert-success');
                                    if (alert) {
                                        var bsAlert = new bootstrap.Alert(alert);
                                        bsAlert.close();
                                    }
                                }, 5000);
                            </script>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert" style="margin-top: 15px; margin-bottom: 20px;">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Error!</strong> {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @yield('content')
                    </main>
                </div>
            </div>
        </div>
    </div>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
            const sidebar = document.getElementById('sidebar');
            const sidebarWrapper = document.getElementById('sidebarWrapper');
            const mainContentWrapper = document.getElementById('mainContentWrapper');
            
            // Check localStorage for saved sidebar state
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            // Initialize sidebar state
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                sidebarWrapper.classList.add('collapsed');
                mainContentWrapper.classList.add('expanded');
                sidebarToggleIcon.classList.remove('fa-bars');
                sidebarToggleIcon.classList.add('fa-chevron-right');
            }
            
            // Toggle sidebar
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                sidebarWrapper.classList.toggle('collapsed');
                
                if (sidebar.classList.contains('collapsed')) {
                    mainContentWrapper.classList.add('expanded');
                    sidebarToggleIcon.classList.remove('fa-bars');
                    sidebarToggleIcon.classList.add('fa-chevron-right');
                    localStorage.setItem('sidebarCollapsed', 'true');
                } else {
                    mainContentWrapper.classList.remove('expanded');
                    sidebarToggleIcon.classList.remove('fa-chevron-right');
                    sidebarToggleIcon.classList.add('fa-bars');
                    localStorage.setItem('sidebarCollapsed', 'false');
                }
            });
        });
    </script>
    
    @yield('scripts')
</body>
</html>
