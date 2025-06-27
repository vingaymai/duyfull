<?php

namespace App\Http\Controllers\Apps\Banhang;

use App\Http\Controllers\Controller;
use App\Models\Banhang\Product;
use App\Models\Banhang\Category; // Make sure Category is imported if used
use App\Models\Banhang\ProductStock; // Make sure ProductStock is imported
use Illuminate\Http\Request;

class SalesProductController extends Controller
{
    /**
     * Display a listing of products suitable for sales/POS interface.
     * Fetches products with their stock information for a given branch.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with(['category', 'productOptions', 'productStocks']); // Ensure productStocks is eagerly loaded

            // Add category filter if category_id is provided
            if ($request->has('category_id') && $request->input('category_id') !== null) {
                $categoryId = $request->input('category_id');
                if (is_numeric($categoryId)) {
                    $query->where('category_id', $categoryId);
                }
            }

            // Add search query filter
            if ($request->has('search') && $request->input('search') !== null) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('sku', 'like', '%' . $searchTerm . '%');
                });
            }

            // Filter by active products only, if requested
            if ($request->has('active_only') && $request->input('active_only')) {
                $query->where('active', true);
            }

            // IMPORTANT: Get the branch_id from the request (sent by frontend)
            $branchId = $request->input('branch_id'); // This should be sent from Sales.vue

            if (!$branchId) {
                // If no branch_id is provided, you might want to default, or return an error
                // For a POS system, branch_id is crucial for correct stock.
                return response()->json(['message' => 'Branch ID is required to fetch product stock.'], 400);
            }

            $products = $query->get();

            // Map products to include only the stock for the selected branch
            $products = $products->map(function ($product) use ($branchId) {
                $stock = 0; // Default stock if not found or not tracked

                // Only track stock if product->track_stock is true
                if ($product->track_stock) {
                    // Find the product stock entry for the specific branch
                    // Note: 'productStocks' is the relationship defined in Product model
                    $branchStock = $product->productStocks->firstWhere('branch_id', $branchId);

                    if ($branchStock) {
                        $stock = $branchStock->stock; // *** CHANGED FROM ->stock_quantity TO ->stock ***
                    }
                } else {
                    // If product does not track stock, consider it "unlimited" or a very high number
                    $stock = 999999;
                }

                // Add a 'stock' attribute to the product object for frontend use
                $product->stock = $stock;

                // Optionally, remove the full productStocks relationship from the response
                // to reduce payload size, as we've extracted the relevant stock.
                unset($product->productStocks);

                return $product;
            });

            return response()->json($products);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error fetching sales products: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['message' => 'An error occurred while fetching products.'], 500);
        }
    }
}
