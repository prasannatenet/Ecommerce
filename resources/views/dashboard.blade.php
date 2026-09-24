@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold">Dashboard</h1>
            <p class="text-muted mb-0">Welcome back, {{ Auth::user()->name }}!</p>
        </div>
        <div>
            <span class="text-muted">{{ now()->format('l, F j, Y') }}</span>
        </div>
    </div>

    @php
        $lowStockCount = $lowStockProducts->count() + $lowStockVariations->count();
    @endphp

    @if($lowStockCount > 0)
        <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Low Stock Alert</strong>
                    <div class="small mt-1">{{ $lowStockCount }} items are at or below stock threshold ({{ $lowStockThreshold }}).</div>
                </div>
                <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-outline-dark">Manage Inventory</a>
            </div>
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-2 fw-medium">Total Products</p>
                            <h2 class="mb-0 fw-bold">{{ number_format($totalProducts) }}</h2>
                            <small class="text-muted">Catalog count</small>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-2 fw-medium">Total Orders</p>
                            <h2 class="mb-0 fw-bold">{{ number_format($totalOrders) }}</h2>
                            <small class="text-muted">All-time orders</small>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-2 fw-medium">Total Revenue</p>
                            <h2 class="mb-0 fw-bold">₹{{ number_format($totalRevenue, 2) }}</h2>
                            <small class="text-muted">Paid orders only</small>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-2 fw-medium">Pending Orders</p>
                            <h2 class="mb-0 fw-bold">{{ number_format($pendingOrders) }}</h2>
                            <small class="text-muted">Require action</small>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Low Stock Products</h5>
                    <span class="badge bg-warning-subtle text-warning">{{ $lowStockProducts->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Product</th>
                                    <th class="border-0">SKU</th>
                                    <th class="border-0">Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lowStockProducts as $p)
                                    <tr>
                                        <td>{{ $p->name }}</td>
                                        <td>{{ $p->sku ?? '-' }}</td>
                                        <td><span class="badge bg-danger-subtle text-danger">{{ $p->stock }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">No low-stock simple products</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Low Stock Variations</h5>
                    <span class="badge bg-warning-subtle text-warning">{{ $lowStockVariations->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Variation SKU</th>
                                    <th class="border-0">Parent Product</th>
                                    <th class="border-0">Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lowStockVariations as $v)
                                    <tr>
                                        <td>{{ $v->sku ?? '-' }}</td>
                                        <td>{{ optional($v->product)->name ?? '-' }}</td>
                                        <td><span class="badge bg-danger-subtle text-danger">{{ $v->stock }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">No low-stock variations</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div>
                            <h5 class="mb-0 fw-bold">Sales Overview</h5>
                            <small class="text-muted" id="salesChartSummary"></small>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Sales period">
                            <button type="button" class="btn btn-outline-secondary active" data-sales-period="week" aria-pressed="true">Week</button>
                            <button type="button" class="btn btn-outline-secondary" data-sales-period="month" aria-pressed="false">Month</button>
                            <button type="button" class="btn btn-outline-secondary" data-sales-period="year" aria-pressed="false">Year</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="salesChartMessage" class="alert alert-info d-none mb-3" role="status" aria-live="polite"></div>
                    <div class="position-relative" style="height: 300px;">
                        <canvas id="salesChart" aria-label="Paid sales overview chart" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">Category Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="position-relative" style="height: 300px;">
                        <canvas id="categoryChart" aria-label="Product category distribution chart" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders & Top Products -->
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Recent Orders</h5>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Order</th>
                                    <th class="border-0">Customer</th>
                                    <th class="border-0">Products</th>
                                    <th class="border-0">Amount</th>
                                    <th class="border-0">Payment</th>
                                    <th class="border-0">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                    <tr>
                                        <td class="fw-medium">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="text-decoration-none">#{{ $order->id }}</a>
                                            <div class="small text-muted">{{ ucfirst($order->status) }}</div>
                                        </td>
                                        <td>
                                            {{ $order->user?->name ?: 'Guest customer' }}
                                            @if($order->user?->email)
                                                <div class="small text-muted">{{ $order->user->email }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($order->items->isNotEmpty())
                                                {{ $order->items->first()->product_name }}
                                                @if($order->items->count() > 1)
                                                    <div class="small text-muted">+{{ $order->items->count() - 1 }} more</div>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="fw-bold text-nowrap">₹{{ number_format($order->total, 2) }}</td>
                                        <td><span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($order->payment_status ?? 'pending') }}</span></td>
                                        <td class="text-nowrap">{{ $order->created_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No orders have been placed yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Top Products</h5>
                        <span class="badge bg-primary-subtle text-primary">Paid orders</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Product</th>
                                    <th class="border-0 text-end">Units sold</th>
                                    <th class="border-0 text-end">Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topProducts as $topProduct)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="product-img-sm bg-light rounded me-2 flex-shrink-0">
                                                    @if($topProduct['image'])
                                                        <img src="{{ asset('storage/' . $topProduct['image']) }}" alt="{{ $topProduct['name'] }}" class="w-100 h-100 rounded" style="object-fit: cover;">
                                                    @else
                                                        <i class="bi bi-box-seam text-primary"></i>
                                                    @endif
                                                </div>
                                                <div>
                                                    @if($topProduct['product'])
                                                        <a href="{{ route('admin.products.show', $topProduct['product']) }}" class="fw-medium text-decoration-none">{{ $topProduct['name'] }}</a>
                                                    @else
                                                        <span class="fw-medium">{{ $topProduct['name'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end text-nowrap">{{ number_format($topProduct['units_sold']) }}</td>
                                        <td class="text-end fw-medium text-nowrap">₹{{ number_format($topProduct['sales'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">No paid product sales yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .product-img-sm {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .table tbody tr {
        transition: background-color 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: rgba(99, 102, 241, 0.05);
    }

    .badge {
        padding: 0.35rem 0.65rem;
        font-weight: 500;
        font-size: 0.75rem;
    }

    .card {
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
    }

    .btn-group-sm > .btn {
        font-size: 0.8rem;
    }
</style>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>

<script>
    const categoryLabels = @json($categoryChartLabels);
    const categoryData = @json($categoryChartData);
    const initialSales = @json($salesOverview);
    const salesEndpoint = @json(route('admin.dashboard.sales'));
    const currencyFormatter = new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2
    });

    let salesChart = null;
    const salesCanvas = document.getElementById('salesChart');
    const salesMessage = document.getElementById('salesChartMessage');
    const salesSummary = document.getElementById('salesChartSummary');
    const salesButtons = Array.from(document.querySelectorAll('[data-sales-period]'));

    function showSalesMessage(message, type = 'info') {
        if (!salesMessage) return;
        salesMessage.textContent = message;
        salesMessage.className = `alert alert-${type} mb-3`;
    }

    function hideSalesMessage() {
        if (salesMessage) salesMessage.classList.add('d-none');
    }

    function updateSalesSummary(data) {
        if (!salesSummary) return;
        salesSummary.textContent = `${currencyFormatter.format(data.total)} · ${data.start} – ${data.end}`;
    }

    function renderSales(data) {
        if (!salesCanvas) return;

        if (salesChart) {
            salesChart.data.labels = data.labels;
            salesChart.data.datasets[0].data = data.revenue;
            salesChart.update();
        } else {
            salesChart = new Chart(salesCanvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Paid sales',
                        data: data.revenue,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.35,
                        fill: true,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: context => `Paid sales: ${currencyFormatter.format(context.parsed.y)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => currencyFormatter.format(value) },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        updateSalesSummary(data);
        if (Number(data.total) === 0) {
            showSalesMessage('There are no paid sales in this period yet.');
        } else {
            hideSalesMessage();
        }
    }

    async function loadSales(period) {
        salesButtons.forEach(button => {
            button.disabled = true;
        });
        showSalesMessage('Loading sales data…');

        try {
            const response = await fetch(`${salesEndpoint}?period=${encodeURIComponent(period)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Unable to load sales data.');

            renderSales(payload);
            salesButtons.forEach(button => {
                const isActive = button.dataset.salesPeriod === period;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', String(isActive));
            });
        } catch (error) {
            showSalesMessage(error.message || 'Unable to load sales data. Please try again.', 'danger');
        } finally {
            salesButtons.forEach(button => {
                button.disabled = false;
            });
        }
    }

    if (salesCanvas) {
        renderSales(initialSales);
        salesButtons.forEach(button => {
            button.addEventListener('click', () => loadSales(button.dataset.salesPeriod));
        });
    }

    // Category Chart
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        const palette = [
            '#6366f1',
            '#10b981',
            '#f59e0b',
            '#06b6d4',
            '#ef4444',
            '#8b5cf6',
            '#14b8a6',
            '#f97316',
            '#84cc16',
            '#ec4899'
        ];

        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryData,
                    backgroundColor: categoryLabels.map((_, index) => palette[index % palette.length]),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' products';
                            }
                        }
                    }
                }
            }
        });
    }
</script>
@endsection
