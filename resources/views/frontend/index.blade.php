<div class="search-form">
    <form id="siteSearchForm" action="{{ route('frontend.search') }}" method="GET">
        <div class="search-form-box">
            <div class="site-search-box" id="siteSearchBox">
                <div class="site-search-icon"><i class="fas fa-search"></i></div>
                <div class="site-search-input" style="flex:1;">
                    <input type="search" id="siteSearchInput" name="search" placeholder="Search products, brands &amp; more..." autocomplete="off" value="{{ request('search') }}" required style="padding-right:80px;">
                    <button type="button" class="clear-search-btn" id="clearSearchBtn" style="display:none;position:absolute;right:6px;top:0;bottom:0;background:transparent;border:none;cursor:pointer;padding:4px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <button type="submit" class="site-search-btn" style="position:relative;z-index:2;"><i class="fas fa-search"></i></button>
                <button type="button" class="recent-toggle-btn" id="recentToggleBtn" style="display:none;position:absolute;right:14px;top:0;bottom:0;background:transparent;border:none;cursor:pointer;padding:0;font-size:13px;color:#888;" title="Recent searches">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span class="recent-count badge bg-secondary ms-1" id="recentCountBadge" style="display:none;">0</span>
                </button>
                <div class="recent-searches" id="recentSearches" style="display:none;position:absolute;right:16px;top:100%;left:auto;margin-top:4px;width:100%;min-width:280px;max-width:360px;background:#fff;border:1px solid #e9ecef;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.12);padding:6px;z-index:999;"></div>
            </div>
        </div>
    </form>
    <div class="site-search-results" id="siteSearchResults"></div>
</div>