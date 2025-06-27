<?php

namespace App\Http\Controllers\Apps\Banhang;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Import DB facade
use Carbon\Carbon; // For date manipulation

class ReportController extends Controller
{
    /**
     * Get a summary of sales (total sales, number of orders)
     * You can add date filters (start_date, end_date) in the request.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSalesSummary(Request $request)
    {
        // Debug: Log incoming dates
        // \Log::info('getSalesSummary: start_date=' . $request->input('start_date') . ', end_date=' . $request->input('end_date'));

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : Carbon::now()->endOfDay(); // Ensure endOfDay for full day

        // Debug: Dump calculated date range
        // dd("Sales Date Range:", $startDate->toDateTimeString(), $endDate->toDateTimeString());

        // Assuming you have an 'orders' table and 'order_items' table
        // Adjust table/column names to match your actual database schema
        $ordersQuery = DB::table('orders')
                          ->whereBetween('created_at', [$startDate, $endDate]);

        // Debug: Dump raw orders before sum/count
        // dd("Raw Orders Data:", $ordersQuery->get());

        $totalSales = $ordersQuery->sum('total_amount'); // Assume 'total_amount' column in orders table
        $totalOrders = $ordersQuery->count();

        // Calculate daily sales
        $dailySales = DB::table('orders')
                        ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as daily_sales'))
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->groupBy('date')
                        ->orderBy('date', 'asc') // Order by date for chronological chart
                        ->get();

        return response()->json([
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'daily_sales' => $dailySales, // Đã thêm dữ liệu doanh thu hàng ngày
        ]);
    }

    /**
     * Get a summary of profit.
     * Requires knowing product costs and sales prices.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfitSummary(Request $request)
    {
        // Debug: Log incoming dates
        // \Log::info('getProfitSummary: start_date=' . $request->input('start_date') . ', end_date=' . $request->input('end_date'));

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : Carbon::now()->endOfDay(); // Ensure endOfDay

        // Debug: Dump calculated date range
        // dd("Profit Date Range:", $startDate->toDateTimeString(), $endDate->toDateTimeString());

        $profitQuery = DB::table('order_items')
                      ->join('orders', 'order_items.order_id', '=', 'orders.id')
                      ->join('products', 'order_items.product_id', '=', 'products.id')
                      // Ensure products.cost_price is treated as 0 if NULL to prevent NULL profit
                      ->select(DB::raw('SUM(order_items.quantity * (order_items.unit_price - COALESCE(products.cost_price, 0))) as total_profit'))
                      ->whereBetween('orders.created_at', [$startDate, $endDate]);

        // Debug: Dump raw profit calculation items before sum
        // dd("Raw Profit Items Data:", $profitQuery->get());

        $profit = $profitQuery->first();

        return response()->json([
            'total_profit' => $profit->total_profit ?? 0,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);
    }

    /**
     * Get a list of best-selling products.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBestSellingProducts(Request $request)
    {
        $limit = $request->input('limit', 10); // Top N products
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : Carbon::now()->endOfDay();

        // Assuming 'order_items' table has 'product_id' and 'quantity'
        $bestSellingProducts = DB::table('order_items')
                                 ->join('products', 'order_items.product_id', '=', 'products.id')
                                 ->join('orders', 'order_items.order_id', '=', 'orders.id')
                                 ->select('products.name', 'products.sku', DB::raw('SUM(order_items.quantity) as total_quantity_sold'))
                                 ->whereBetween('orders.created_at', [$startDate, $endDate])
                                 ->groupBy('products.id', 'products.name', 'products.sku')
                                 ->orderByDesc('total_quantity_sold')
                                 ->limit($limit)
                                 ->get();

        return response()->json($bestSellingProducts);
    }

    /**
     * Get a report of current stock levels.
     * Now correctly fetching stock from 'product_stocks' table.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStockReport(Request $request)
    {
        // Join products with product_stocks to get actual stock levels per product
        // Note: This query currently does not filter by branch_id.
        // If you need branch-specific stock, you'll need to add branch_id to the request
        // and filter the product_stocks table by that branch_id.
        $productsWithStock = DB::table('products')
                             ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
                             ->select(
                                 'products.id',
                                 'products.name',
                                 'products.sku',
                                 'products.base_price', // ĐÃ SỬA: Sử dụng 'base_price' thay vì 'price'
                                 DB::raw('SUM(product_stocks.stock) as total_stock_quantity'), // Sum stock across branches if multiple entries
                                 DB::raw('MIN(product_stocks.low_stock_threshold) as effective_low_stock_threshold') // Take min threshold
                             )
                             ->groupBy('products.id', 'products.name', 'products.sku', 'products.base_price') // ĐÃ SỬA: Group by 'base_price'
                             ->orderBy('products.name')
                             ->get();

        // Calculate summary based on fetched data
        $totalProducts = $productsWithStock->count();
        $inStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        foreach ($productsWithStock as $product) {
            $product->total_stock_quantity = (int) $product->total_stock_quantity; // Cast to integer
            $product->effective_low_stock_threshold = (int) $product->effective_low_stock_threshold; // Cast to integer

            if ($product->total_stock_quantity <= 0) { // Consider 0 or less as out of stock
                $outOfStock++;
            } elseif ($product->total_stock_quantity <= $product->effective_low_stock_threshold) {
                $lowStock++;
            } else {
                $inStock++;
            }
        }

        $stockSummary = [
            'total_products' => $totalProducts,
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'products' => $productsWithStock, // Return products with their aggregated stock info
        ];

        return response()->json($stockSummary);
    }

    /**
     * Get a general overview for a dashboard (combines multiple reports).
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOverview(Request $request)
    {
        // This is an example, you would call other methods or queries here
        $sales = $this->getSalesSummary($request)->getData(true);
        $profit = $this->getProfitSummary($request)->getData(true);
        $bestSelling = $this->getBestSellingProducts($request)->getData(true);
        $stock = $this->getStockReport($request)->getData(true);

        return response()->json([
            'sales_summary' => $sales,
            'profit_summary' => $profit,
            'best_selling_products' => $bestSelling,
            'stock_report' => $stock,
            // Add more data points as needed for your overview dashboard
        ]);
    }
}
