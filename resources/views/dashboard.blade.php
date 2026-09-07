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
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Sales Overview</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary active">Week</button>
                            <button class="btn btn-outline-secondary">Month</button>
                            <button class="btn btn-outline-secondary">Year</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">Category Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders & Top Products -->
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Recent Orders</h5>
                        <a href="{{ url('/admin/orders') }}" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Order ID</th>
                                    <th class="border-0">Customer</th>
                                    <th class="border-0">Product</th>
                                    <th class="border-0">Amount</th>
                                    <th class="border-0">Status</th>
                                    <th class="border-0">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-medium">#ORD-001</td>
                                    <td>John Doe</td>
                                    <td>iPhone 15 Pro</td>
                                    <td class="fw-bold">$999.00</td>
                                    <td><span class="badge bg-success-subtle text-success">Completed</span></td>
                                    <td>Nov 5, 2025</td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">#ORD-002</td>
                                    <td>Jane Smith</td>
                                    <td>MacBook Pro</td>
                                    <td class="fw-bold">$2,499.00</td>
                                    <td><span class="badge bg-warning-subtle text-warning">Pending</span></td>
                                    <td>Nov 5, 2025</td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">#ORD-003</td>
                                    <td>Mike Johnson</td>
                                    <td>AirPods Pro</td>
                                    <td class="fw-bold">$249.00</td>
                                    <td><span class="badge bg-primary-subtle text-primary">Processing</span></td>
                                    <td>Nov 4, 2025</td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">#ORD-004</td>
                                    <td>Sarah Williams</td>
                                    <td>iPad Air</td>
                                    <td class="fw-bold">$599.00</td>
                                    <td><span class="badge bg-success-subtle text-success">Completed</span></td>
                                    <td>Nov 4, 2025</td>
                                </tr>
                                <tr>
                                    <td class="fw-medium">#ORD-005</td>
                                    <td>Tom Brown</td>
                                    <td>Apple Watch</td>
                                    <td class="fw-bold">$399.00</td>
                                    <td><span class="badge bg-danger-subtle text-danger">Cancelled</span></td>
                                    <td>Nov 3, 2025</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">Top Products</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <div class="product-img-sm bg-light rounded me-3">
                            <i class="bi bi-phone text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">iPhone 15 Pro</h6>
                            <small class="text-muted">Electronics</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$999</div>
                            <small class="text-success">348 sold</small>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <div class="product-img-sm bg-light rounded me-3">
                            <i class="bi bi-laptop text-success"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">MacBook Pro</h6>
                            <small class="text-muted">Computers</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$2,499</div>
                            <small class="text-success">234 sold</small>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <div class="product-img-sm bg-light rounded me-3">
                            <i class="bi bi-headphones text-warning"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">AirPods Pro</h6>
                            <small class="text-muted">Audio</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$249</div>
                            <small class="text-success">567 sold</small>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <div class="product-img-sm bg-light rounded me-3">
                            <i class="bi bi-tablet text-info"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">iPad Air</h6>
                            <small class="text-muted">Tablets</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$599</div>
                            <small class="text-success">412 sold</small>
                        </div>
                    </div>

                    <div class="d-flex align-items-center">
                        <div class="product-img-sm bg-light rounded me-3">
                            <i class="bi bi-smartwatch text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">Apple Watch</h6>
                            <small class="text-muted">Wearables</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$399</div>
                            <small class="text-success">289 sold</small>
                        </div>
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

    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4,
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
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return 'Sales: $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
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
