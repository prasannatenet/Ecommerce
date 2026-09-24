<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index()
    {
        $lowStockThreshold = 5;

        $lowStockProducts = Product::query()
            ->where('manage_stock', true)
            ->where('stock', '<=', $lowStockThreshold)
            ->orderBy('stock')
            ->limit(10)
            ->get();

        $lowStockVariations = ProductVariation::query()
            ->with('product:id,name')
            ->where('is_active', true)
            ->where('stock', '<=', $lowStockThreshold)
            ->orderBy('stock')
            ->limit(10)
            ->get();

        $categoryDistribution = Category::query()
            ->withCount('products')
            ->orderByDesc('products_count')
            ->get(['id', 'name']);

        return view('dashboard', [
            'totalProducts' => Product::count(),
            'totalOrders' => Order::count(),
            'totalRevenue' => (float) $this->paidOrdersQuery()->sum('total'),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'lowStockProducts' => $lowStockProducts,
            'lowStockVariations' => $lowStockVariations,
            'lowStockThreshold' => $lowStockThreshold,
            'categoryChartLabels' => $categoryDistribution->pluck('name')->values(),
            'categoryChartData' => $categoryDistribution->pluck('products_count')->values(),
            'topProducts' => $this->topProducts(),
            'recentOrders' => Order::query()
                ->with(['user:id,name,email', 'items:id,order_id,product_name,quantity'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
            'salesOverview' => $this->salesSeries('week'),
        ]);
    }

    public function sales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['week', 'month', 'year'])],
        ]);

        $period = $validated['period'] ?? 'week';

        return response()
            ->json($this->salesSeries($period))
            ->header('Cache-Control', 'no-store, private');
    }

    private function paidOrdersQuery(): Builder
    {
        return Order::query()->where('payment_status', 'paid');
    }

    private function topProducts(): array
    {
        $productRows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->select('order_items.product_id')
            ->selectRaw('MIN(order_items.product_name) as product_name')
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->selectRaw('SUM(order_items.line_total) as sales')
            ->get();

        $deletedProductRows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereNull('order_items.product_id')
            ->groupBy('order_items.product_name')
            ->selectRaw('NULL as product_id')
            ->selectRaw('order_items.product_name')
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->selectRaw('SUM(order_items.line_total) as sales')
            ->get();

        $rows = $productRows
            ->concat($deletedProductRows)
            ->sort(function (OrderItem $left, OrderItem $right): int {
                return [(int) $right->units_sold, (float) $right->sales]
                    <=> [(int) $left->units_sold, (float) $left->sales];
            })
            ->take(5)
            ->values();

        $products = Product::query()
            ->with(['images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id')])
            ->whereIn('id', $rows->pluck('product_id')->filter())
            ->get()
            ->keyBy('id');

        return $rows->map(function (OrderItem $row) use ($products): array {
            $product = $row->product_id ? $products->get($row->product_id) : null;

            return [
                'product' => $product,
                'name' => $product?->name ?: $row->product_name,
                'image' => $product?->images?->first()?->path,
                'units_sold' => (int) $row->units_sold,
                'sales' => (float) $row->sales,
            ];
        })->values()->all();
    }

    private function salesSeries(string $period): array
    {
        $period = in_array($period, ['week', 'month', 'year'], true) ? $period : 'week';
        $now = now();
        $start = match ($period) {
            'week' => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            'year' => $now->copy()->startOfYear(),
        };
        $end = match ($period) {
            'week' => $now->copy()->endOfWeek(),
            'month' => $now->copy()->endOfMonth(),
            'year' => $now->copy()->endOfYear(),
        };

        $granularity = $period === 'year' ? 'month' : 'day';
        $dateExpression = $this->salesDateExpression($granularity);
        $rows = $this->paidOrdersQuery()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw("{$dateExpression} as period_key")
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->groupByRaw($dateExpression)
            ->orderBy('period_key')
            ->get();

        $revenueByPeriod = $rows->mapWithKeys(
            fn ($row) => [(string) $row->period_key => (float) $row->revenue]
        );

        $labels = [];
        $data = [];
        if ($period === 'week') {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $key = $date->format('Y-m-d');
                $labels[] = $date->format('D');
                $data[] = $revenueByPeriod[$key] ?? 0.0;
            }
        } elseif ($period === 'month') {
            for ($day = 1; $day <= $start->daysInMonth; $day++) {
                $date = $start->copy()->addDays($day - 1);
                $key = $date->format('Y-m-d');
                $labels[] = $date->format('d M');
                $data[] = $revenueByPeriod[$key] ?? 0.0;
            }
        } else {
            for ($month = 1; $month <= 12; $month++) {
                $date = $start->copy()->addMonths($month - 1);
                $key = $date->format('Y-m');
                $labels[] = $date->format('M');
                $data[] = $revenueByPeriod[$key] ?? 0.0;
            }
        }

        return [
            'period' => $period,
            'labels' => $labels,
            'revenue' => $data,
            'total' => round(array_sum($data), 2),
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    private function salesDateExpression(string $granularity): string
    {
        $driver = DB::connection()->getDriverName();

        if ($granularity === 'month') {
            return match ($driver) {
                'sqlite' => "strftime('%Y-%m', paid_at)",
                'pgsql' => "to_char(paid_at, 'YYYY-MM')",
                default => "DATE_FORMAT(paid_at, '%Y-%m')",
            };
        }

        return match ($driver) {
            'sqlite' => 'date(paid_at)',
            'pgsql' => 'paid_at::date',
            default => 'DATE(paid_at)',
        };
    }
}
