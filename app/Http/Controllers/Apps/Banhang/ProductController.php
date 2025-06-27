<?php

namespace App\Http\Controllers\Apps\Banhang;

use App\Http\Controllers\Controller;
use App\Models\Banhang\Product;
use App\Models\Banhang\Category;
use App\Models\Banhang\ProductOption;
use App\Models\Banhang\ProductStock;
use App\Models\Banhang\Branch;
use App\Models\Banhang\Modifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     * Fetches all products with necessary relationships for frontend processing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Start building the query with relationships
            $query = Product::with(['category', 'productOptions', 'productStocks.branch', 'modifiers']);

            // Add category filter if category_id is provided in the request
            if ($request->has('category_id') && $request->input('category_id') !== null) {
                $categoryId = $request->input('category_id');
                if (is_numeric($categoryId)) {
                    $query->where('category_id', (int)$categoryId);
                }
            }

            // Add branch filter if branch_id is provided in the request
            // This will filter products to show only those associated with the selected branch.
            if ($request->has('branch_id') && $request->input('branch_id') !== null) {
                $branchId = $request->input('branch_id');
                if (is_numeric($branchId)) {
                    $query->whereHas('productStocks', function ($q) use ($branchId) {
                        $q->where('branch_id', (int)$branchId);
                    });
                }
            }


            $products = $query->get();
            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error fetching all products in ProductController: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải danh sách sản phẩm.'], 500);
        }
    }

    /**
     * Store a newly created product in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            // Manually decode JSON strings from FormData for nested arrays before validation
            // This is crucial because `multipart/form-data` sends these as strings.
        
            $validatedData = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:255|unique:products,sku',
                'barcode' => 'nullable|string|max:255|unique:products,barcode',
                'description' => 'nullable|string',
                'image_url' => 'nullable|url|max:2048',
                'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'base_price' => 'required|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'sold_by_weight' => 'boolean',
                'unit' => 'required|string|max:50',
                'track_stock' => 'boolean',
                'active' => 'boolean',
                'options' => 'array',
                'options.*.name' => 'required_with:options|string|max:255',
                // Bỏ json_encode ở đây, vì model ProductOption sẽ tự động cast 'values' thành array
                // và Laravel sẽ tự encode/decode khi lưu/lấy từ DB
                'options.*.values' => 'required_with:options|array',
                'options.*.position' => 'nullable|integer|min:0',
                'stocks' => 'array',
                'stocks.*.branch_id' => 'required_with:stocks|exists:branches,id',
                'stocks.*.stock' => 'required_with:stocks|integer|min:0',
                'stocks.*.low_stock_threshold' => 'nullable|integer|min:0',
                'stocks.*.available' => 'boolean',
                'stocks.*.price_override' => 'nullable|numeric|min:0',
                'modifiers' => 'array',
                'modifiers.*.name' => 'required_with:modifiers|string|max:255',
                'modifiers.*.price' => 'required_with:modifiers|numeric|min:0',
            ]);

            $imageUrl = $validatedData['image_url'] ?? null;

            if ($request->hasFile('image_file')) {
                $imagePath = $request->file('image_file')->store('public/products');
                $imageUrl = Storage::url($imagePath);
            }

            $product = Product::create(array_merge($validatedData, ['image_url' => $imageUrl]));

            if (isset($validatedData['options'])) {
                foreach ($validatedData['options'] as $optionData) {
                    $product->productOptions()->create([
                        'name' => $optionData['name'],
                        'values' => $optionData['values'], // Không json_encode ở đây!
                        'position' => $optionData['position'] ?? 0,
                    ]);
                }
            }

            if (isset($validatedData['stocks'])) {
                foreach ($validatedData['stocks'] as $stockData) {
                    $product->productStocks()->create([
                        'branch_id' => $stockData['branch_id'],
                        'stock' => $stockData['stock'],
                        'low_stock_threshold' => $stockData['low_stock_threshold'] ?? 5,
                        'available' => $stockData['available'] ?? true,
                        'price_override' => $stockData['price_override'],
                    ]);
                }
            }

            if (isset($validatedData['modifiers'])) {
                foreach ($validatedData['modifiers'] as $modifierData) {
                    $product->modifiers()->create([
                        'name' => $modifierData['name'],
                        'price' => $modifierData['price'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Sản phẩm đã được thêm mới thành công.',
                'product' => $product->load(['category', 'productOptions', 'productStocks.branch', 'modifiers'])
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing product: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi lưu sản phẩm. Vui lòng thử lại.'
            ], 500);
        }
    }

    /**
     * Display the specified product.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Product $product)
    {
        try {
            return response()->json($product->load(['category', 'productOptions', 'productStocks.branch', 'modifiers']));
        } catch (\Exception $e) {
            Log::error('Error showing product ' . $product->id . ': ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải chi tiết sản phẩm.'], 500);
        }
    }

    /**
     * Update the specified product in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Product $product)
    {
        try {
            DB::beginTransaction();

            // Manually decode JSON strings from FormData for nested arrays before validation
            $request->merge([
                'options' => json_decode($request->input('options', '[]'), true),
                'stocks' => json_decode($request->input('stocks', '[]'), true),
                'modifiers' => json_decode($request->input('modifiers', '[]'), true),
            ]);

            $validatedData = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product->id)],
                'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products', 'barcode')->ignore($product->id)],
                'description' => 'nullable|string',
                'image_url' => 'nullable|url|max:2048',
                'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'base_price' => 'required|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'sold_by_weight' => 'required|boolean',
                'unit' => 'required|string|max:50',
                'track_stock' => 'required|boolean',
                'active' => 'required|boolean',
                'options' => 'array',
                'options.*.id' => 'nullable|exists:product_options,id',
                'options.*.name' => 'required_with:options|string|max:255',
                // Bỏ json_encode ở đây, vì model ProductOption sẽ tự động cast 'values' thành array
                'options.*.values' => 'required_with:options|array',
                'options.*.position' => 'nullable|integer|min:0',
                'stocks' => 'array',
                'stocks.*.id' => 'nullable|exists:product_stocks,id',
                'stocks.*.branch_id' => 'required_with:stocks|exists:branches,id',
                'stocks.*.stock' => 'required_with:stocks|integer|min:0',
                'stocks.*.low_stock_threshold' => 'nullable|integer|min:0',
                'stocks.*.available' => 'boolean',
                'stocks.*.price_override' => 'nullable|numeric|min:0',
                'modifiers' => 'array',
                'modifiers.*.id' => 'nullable|exists:modifiers,id',
                'modifiers.*.name' => 'required_with:modifiers|string|max:255',
                'modifiers.*.price' => 'required_with:modifiers|numeric|min:0',
            ]);

            $imageUrl = $validatedData['image_url'] ?? $product->image_url;

            if ($request->hasFile('image_file')) {
                // Delete old image if it was stored locally
                if ($product->image_url && Str::startsWith($product->image_url, '/storage/')) {
                    Storage::delete(Str::after($product->image_url, '/storage/'));
                }
                $imagePath = $request->file('image_file')->store('public/products');
                $imageUrl = Storage::url($imagePath);
            } elseif (array_key_exists('image_url', $validatedData) && $validatedData['image_url'] === null) {
                 // If image_url is explicitly set to null (frontend cleared it), delete existing file
                 if ($product->image_url && Str::startsWith($product->image_url, '/storage/')) {
                    Storage::delete(Str::after($product->image_url, '/storage/'));
                }
                $imageUrl = null;
            }

            $product->update(array_merge($validatedData, ['image_url' => $imageUrl]));

            // Sync Product Options: Delete old, add new, update existing
            $existingOptionIds = collect($validatedData['options'] ?? [])->filter(fn($o) => isset($o['id']))->pluck('id')->toArray();
            $product->productOptions()->whereNotIn('id', $existingOptionIds)->delete();

            if (isset($validatedData['options'])) {
                foreach ($validatedData['options'] as $optionData) {
                    if (isset($optionData['id'])) {
                        $product->productOptions()->where('id', $optionData['id'])->update([
                            'name' => $optionData['name'],
                            'values' => $optionData['values'], // Không json_encode ở đây!
                            'position' => $optionData['position'] ?? 0,
                        ]);
                    } else {
                        $product->productOptions()->create([
                            'name' => $optionData['name'],
                            'values' => $optionData['values'], // Không json_encode ở đây!
                            'position' => $optionData['position'] ?? 0,
                        ]);
                    }
                }
            }

            // Sync Product Stocks: Delete old, add new, update existing
            $existingStockIds = collect($validatedData['stocks'] ?? [])->filter(fn($s) => isset($s['id']))->pluck('id')->toArray();
            $product->productStocks()->whereNotIn('id', $existingStockIds)->delete();

            if (isset($validatedData['stocks'])) {
                foreach ($validatedData['stocks'] as $stockData) {
                    if (isset($stockData['id'])) {
                        $product->productStocks()->where('id', $stockData['id'])->update([
                            'branch_id' => $stockData['branch_id'],
                            'stock' => $stockData['stock'],
                            'low_stock_threshold' => $stockData['low_stock_threshold'] ?? 5,
                            'available' => $stockData['available'] ?? true,
                            'price_override' => $stockData['price_override'],
                        ]);
                    } else {
                        $product->productStocks()->create([
                            'branch_id' => $stockData['branch_id'],
                            'stock' => $stockData['stock'],
                            'low_stock_threshold' => $stockData['low_stock_threshold'] ?? 5,
                            'available' => $stockData['available'] ?? true,
                            'price_override' => $stockData['price_override'],
                        ]);
                    }
                }
            }

            // Sync Modifiers (Similar logic if modifiers can be created/updated/deleted here)
            $existingModifierIds = collect($validatedData['modifiers'] ?? [])->filter(fn($m) => isset($m['id']))->pluck('id')->toArray();
            $product->modifiers()->whereNotIn('id', $existingModifierIds)->delete();

            if (isset($validatedData['modifiers'])) {
                foreach ($validatedData['modifiers'] as $modifierData) {
                    if (isset($modifierData['id'])) {
                        $product->modifiers()->where('id', $modifierData['id'])->update([
                            'name' => $modifierData['name'],
                            'price' => $modifierData['price'],
                        ]);
                    } else {
                        $product->modifiers()->create([
                            'name' => $modifierData['name'],
                            'price' => $modifierData['price'],
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Sản phẩm đã được cập nhật thành công.',
                'product' => $product->load(['category', 'productOptions', 'productStocks.branch', 'modifiers'])
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating product: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật sản phẩm. Vui lòng thử lại.'
            ], 500);
        }
    }

    /**
     * Remove the specified product from storage.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Product $product)
    {
        try {
            DB::beginTransaction();

            $product->productStocks()->delete();
            $product->modifiers()->delete();

            if ($product->image_url && Str::startsWith($product->image_url, '/storage/')) {
                Storage::delete(Str::after($product->image_url, '/storage/'));
            }

            $product->delete();

            DB::commit();

            return response()->json(['message' => 'Sản phẩm đã được xóa thành công.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi khi xóa sản phẩm.'], 500);
        }
    }

    /**
     * Get all branches for selection in product stock.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBranches()
    {
        try {
            $branches = Branch::where('active', true)->get();
            return response()->json($branches);
        } catch (\Exception $e) {
            Log::error('Error fetching branches in ProductController: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải chi nhánh.'], 500);
        }
    }

    /**
     * Get all categories for selection in product form.
     * This is a utility endpoint, similar to categories/parents.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategories()
    {
        try {
            $categories = Category::where('active', true)->get();
            return response()->json($categories);
        } catch (\Exception $e) {
            Log::error('Error fetching categories in ProductController: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải danh mục.'], 500);
        }
    }

    /**
     * Export products to an Excel file.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export()
    {
        try {
            $filename = 'san_pham_' . now()->format('Ymd_His') . '.xlsx';
            return Excel::download(new ProductsExport, $filename);
        } catch (\Exception $e) {
            Log::error('Lỗi khi xuất sản phẩm ra Excel: ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi khi xuất dữ liệu sản phẩm ra Excel. Vui lòng thử lại.'], 500);
        }
    }

    /**
     * Import products from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240', // Yêu cầu file Excel, max 10MB
        ], [
            'file.required' => 'Vui lòng chọn một file Excel để nhập.',
            'file.mimes' => 'File phải có định dạng .xlsx hoặc .xls.',
            'file.max' => 'Kích thước file không được vượt quá 10MB.',
        ]);

        try {
            $import = new ProductsImport();
            Excel::import($import, $request->file('file'));

            $validationErrors = [];
            if (isset($import->errors) && count($import->errors) > 0) {
                foreach ($import->errors as $failure) {
                    $validationErrors[] = [
                        'row' => $failure->row(),
                        'attribute' => $failure->attribute(), // Tên cột bị lỗi
                        'errors' => $failure->errors(), // Mảng các thông báo lỗi
                        'values' => $failure->values(), // Giá trị của hàng bị lỗi
                    ];
                }
            }

            $message = 'Sản phẩm đã được nhập thành công.';
            if (count($validationErrors) > 0) {
                $message .= ' Có ' . count($validationErrors) . ' lỗi validation trong file.';
            }

            return response()->json([
                'message' => $message,
                'validation_errors' => $validationErrors // Trả về lỗi validation từng hàng
            ], 200);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }
            // Trả về lỗi validation nếu có lỗi trong quá trình import (từ WithValidation)
            return response()->json([
                'message' => 'Có lỗi xảy ra khi nhập file Excel.',
                'validation_errors' => $errors
            ], 422); // 422 Unprocessable Entity

        } catch (\Exception $e) {
            Log::error('Lỗi khi nhập Excel: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi nhập dữ liệu Excel. Vui lòng kiểm tra log.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}
