<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    public function dashboard()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Today's statistics
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = Order::whereDate('created_at', $today)
                           ->where('payment_status', 'paid')
                           ->sum('total');

        // This month's statistics
        $monthOrders = Order::where('created_at', '>=', $thisMonth)->count();
        $monthRevenue = Order::where('created_at', '>=', $thisMonth)
                            ->where('payment_status', 'paid')
                            ->sum('total');

        // Last month's statistics for comparison
        $lastMonthOrders = Order::whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $lastMonthRevenue = Order::whereBetween('created_at', [$lastMonth, $thisMonth])
                                ->where('payment_status', 'paid')
                                ->sum('total');

        // Calculate growth
        $orderGrowth = $lastMonthOrders > 0 ? (($monthOrders - $lastMonthOrders) / $lastMonthOrders) * 100 : 0;
        $revenueGrowth = $lastMonthRevenue > 0 ? (($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

        // Order status distribution
        $orderStatusDistribution = Order::select('status', DB::raw('count(*) as count'))
                                       ->where('created_at', '>=', $thisMonth)
                                       ->groupBy('status')
                                       ->get();

        // Top selling products
        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
                                ->whereHas('order', function($query) use ($thisMonth) {
                                    $query->where('created_at', '>=', $thisMonth)
                                          ->where('payment_status', 'paid');
                                })
                                ->with('product')
                                ->groupBy('product_id')
                                ->orderBy('total_sold', 'desc')
                                ->limit(5)
                                ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'orders' => $todayOrders,
                    'revenue' => $todayRevenue
                ],
                'this_month' => [
                    'orders' => $monthOrders,
                    'revenue' => $monthRevenue,
                    'order_growth' => round($orderGrowth, 2),
                    'revenue_growth' => round($revenueGrowth, 2)
                ],
                'order_status_distribution' => $orderStatusDistribution,
                'top_products' => $topProducts
            ]
        ]);
    }

    public function salesReport(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'group_by' => 'in:day,week,month'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();
        $groupBy = $request->group_by ?? 'day';

        // Sales data grouped by date
        $salesData = Order::select(
                DB::raw($this->getDateFormat($groupBy) . ' as period'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('AVG(total) as average_order_value')
            )
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('payment_status', 'paid')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Product sales breakdown
        $productSales = OrderItem::select(
                'products.name',
                'products.price',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.total) as total_revenue')
            )
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$dateFrom, $dateTo])
            ->where('orders.payment_status', 'paid')
            ->groupBy('products.id', 'products.name', 'products.price')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // Summary
        $summary = [
            'total_orders' => Order::whereBetween('created_at', [$dateFrom, $dateTo])
                                  ->where('payment_status', 'paid')
                                  ->count(),
            'total_revenue' => Order::whereBetween('created_at', [$dateFrom, $dateTo])
                                   ->where('payment_status', 'paid')
                                   ->sum('total'),
            'average_order_value' => Order::whereBetween('created_at', [$dateFrom, $dateTo])
                                         ->where('payment_status', 'paid')
                                         ->avg('total'),
            'total_items_sold' => OrderItem::whereHas('order', function($query) use ($dateFrom, $dateTo) {
                                            $query->whereBetween('created_at', [$dateFrom, $dateTo])
                                                  ->where('payment_status', 'paid');
                                        })->sum('quantity')
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $dateFrom->toDateString(),
                    'to' => $dateTo->toDateString(),
                    'group_by' => $groupBy
                ],
                'summary' => $summary,
                'sales_data' => $salesData,
                'product_sales' => $productSales
            ]
        ]);
    }

    public function exportSalesReport(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'format' => 'in:pdf,csv'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();
        $format = $request->format ?? 'pdf';

        // Get sales data (similar to salesReport method)
        $reportData = $this->salesReport($request)->getData()->data;

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('sales-report', compact('reportData'));
            return $pdf->download('sales-report-' . $dateFrom->format('Y-m-d') . '-to-' . $dateTo->format('Y-m-d') . '.pdf');
        } else {
            // CSV export logic would go here
            return response()->json([
                'success' => false,
                'message' => 'CSV export not implemented yet'
            ], 501);
        }
    }

    private function getDateFormat($groupBy)
    {
        switch ($groupBy) {
            case 'day':
                return "DATE(created_at)";
            case 'week':
                return "DATE_FORMAT(created_at, '%Y-%u')";
            case 'month':
                return "DATE_FORMAT(created_at, '%Y-%m')";
            default:
                return "DATE(created_at)";
        }
    }
}
