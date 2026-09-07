<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $appSetting->site_name ?? config('app.name', 'Dataflow') }} — Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom Styles -->
    <link href="{{ asset('backend/css/style.css') }}?v={{ time() }}" rel="stylesheet">

    @stack('styles')

    <link rel="icon" type="image/x-icon" href="{{ optional($appSetting)->favicon_path ? asset('storage/' . $appSetting->favicon_path) : asset('frontend/images/fav.png') }}">
</head>

<body>
    <div id="app">
        <!-- Mobile Sidebar Overlay -->
        <div class="df-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

        <div class="df-wrapper">
            <!-- Sidebar -->
            @include('layouts.sidebar')

            <!-- Main Content -->
            <div class="df-main">
                <!-- Header -->
                @include('layouts.navigation')

                <!-- Page Content -->
                <div class="df-content">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

    <!-- Sidebar JS -->
    <script>
        // Toggle sidebar submenu items
        document.querySelectorAll('.df-nav-item.has-submenu > .df-nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.closest('.df-nav-item');
                // Close other open items
                document.querySelectorAll('.df-nav-item.has-submenu.open').forEach(item => {
                    if (item !== parent) item.classList.remove('open');
                });
                parent.classList.toggle('open');
            });
        });

        // Mobile sidebar toggle
        function openSidebar() {
            document.querySelector('.df-sidebar').classList.add('show');
            document.getElementById('sidebarOverlay').classList.add('show');
        }

        function closeSidebar() {
            document.querySelector('.df-sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        }

        // Auto-open the current section based on URL
        document.addEventListener('DOMContentLoaded', function () {
            const currentUrl = window.location.href;
            document.querySelectorAll('.df-submenu .df-nav-link').forEach(link => {
                if (link.href && currentUrl.includes(link.getAttribute('href'))) {
                    link.classList.add('active');
                    const parent = link.closest('.df-nav-item.has-submenu');
                    if (parent) parent.classList.add('open');
                }
            });
            document.querySelectorAll('.df-nav-item:not(.has-submenu) > .df-nav-link').forEach(link => {
                if (link.href && currentUrl.includes(link.getAttribute('href')) && link.getAttribute('href') !== '/') {
                    link.classList.add('active');
                }
            });
        });
    </script>

    @stack('scripts')
</body>

</html>