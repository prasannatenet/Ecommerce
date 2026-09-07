<header class="df-header">
    <!-- Mobile Toggle -->
    <button class="df-mobile-toggle" onclick="openSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <!-- Search -->
    <!-- <div class="df-header-search">
        <div class="df-search-wrapper">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="df-search-input" placeholder="Search...">
        </div>
    </div> -->

    <!-- Header Actions -->
    <div class="df-header-actions">
        <!-- <button class="df-header-btn" title="Dark Mode">
            <i class="bi bi-moon"></i>
        </button> -->

        <button class="df-header-btn" title="Notifications">
            <i class="bi bi-bell"></i>
            <span class="notification-dot"></span>
        </button>

        <!-- <button class="df-header-btn" title="Messages">
            <i class="bi bi-chat-dots"></i>
        </button> -->

        <button class="df-header-btn" title="Fullscreen" onclick="toggleFullscreen()">
            <i class="bi bi-arrows-fullscreen"></i>
        </button>

        @auth
            <!-- User Dropdown -->
            <div class="dropdown">
                <a class="df-user-dropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="df-user-avatar">{{ substr(Auth::user()->name, 0, 1) }}</span>
                    <div class="df-user-info d-none d-md-block">
                        <div class="df-user-name">{{ Auth::user()->name }}</div>
                        <div class="df-user-role">Administrator</div>
                    </div>
                </a>

                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="{{ url('/profile') }}">
                        <i class="bi bi-person-circle"></i> Profile
                    </a>
                    <a class="dropdown-item" href="{{ route('admin.settings.edit') }}">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="bi bi-box-arrow-right"></i> {{ __('Logout') }}
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        @endauth

        @guest
            <a href="{{ route('login') }}" class="df-btn df-btn-primary df-btn-sm">Login</a>
        @endguest
    </div>
</header>

<script>
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}
</script>