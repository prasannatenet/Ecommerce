<?php 

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $lowStockThreshold = 5;

        // Backward-compatible revenue query for databases that have not run
        // the payment fields migration yet.
        $revenueQuery = Order::query();

        if (Schema::hasColumn('orders', 'payment_status')) {
            $revenueQuery->where('payment_status', 'paid');
        }

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
            'totalRevenue' => (float) $revenueQuery->sum('total'),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'lowStockProducts' => $lowStockProducts,
            'lowStockVariations' => $lowStockVariations,
            'lowStockThreshold' => $lowStockThreshold,
            'categoryChartLabels' => $categoryDistribution->pluck('name')->values(),
            'categoryChartData' => $categoryDistribution->pluck('products_count')->values(),
        ]);
    }
}
