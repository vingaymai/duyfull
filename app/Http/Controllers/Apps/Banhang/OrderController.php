<?php

namespace App\Http\Controllers\Apps\Banhang;

use App\Http\Controllers\Controller;
use App\Models\Banhang\Order;
use App\Models\Banhang\OrderItem;
use App\Models\Banhang\Product; // Ensure Product model is imported
use App\Models\Banhang\ProductStock; // Ensure ProductStock model is imported
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // <-- THÊM DÒNG NÀY để sử dụng Auth

class OrderController extends Controller
{
    /**
     * Get a list of orders with filters and eager loading.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Eager load customer and user (the creator of the order) relationships
            $query = Order::with(['customer', 'user']) 
                          ->orderBy('created_at', 'desc'); // Order by newest first

            // Apply search filter if provided
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where('status', 'like', '%' . $search . '%')
                      ->orWhere('payment_method', 'like', '%' . $search . '%')
                      ->orWhereHas('customer', function ($q) use ($search) {
                          $q->where('name', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                      })
                      ->orWhere('id', 'like', '%' . $search . '%'); // Search by order ID as well
            }

            // Apply status filter if provided
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            // Apply payment method filter if provided
            if ($request->has('payment_method') && !empty($request->payment_method)) {
                $query->where('payment_method', $request->payment_method);
            }

            // Apply date range filter if provided
            if ($request->has('start_date') && !empty($request->start_date)) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date') && !empty($request->end_date)) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            $orders = $query->get();
            return response()->json($orders);
        } catch (\Exception $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải danh sách đơn hàng.'], 500);
        }
    }
    
    /**
     * Store a newly created order in storage and deduct product stock.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // 1. Validate incoming request data
        $validatedData = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'branch_id' => 'required|integer|exists:branches,id', // Make sure branch_id is required
            'total_amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'status' => 'required|string|in:pending,completed,cancelled', // Assuming these statuses
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1', // Ensure 'items' array is present and not empty
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', function ($attribute, $value, $fail) use ($request) {
                // Custom validation for stock quantity before transaction starts
                // This provides an early check, but the transaction check is the definitive one.
                $index = explode('.', $attribute)[1]; // Get the index of the item
                $productId = $request->input("items.{$index}.product_id");
                $branchId = $request->input('branch_id');

                $product = Product::find($productId);
                if (!$product) {
                    return $fail("Sản phẩm với ID {$productId} không tồn tại.");
                }

                if ($product->track_stock) {
                    $productStock = ProductStock::where('product_id', $productId)
                                                ->where('branch_id', $branchId)
                                                ->first();
                    if (!$productStock || $productStock->stock < $value) {
                        $fail("Không đủ tồn kho cho sản phẩm '{$product->name}' (ID: {$productId}) tại chi nhánh này. Tồn kho hiện có: " . ($productStock ? $productStock->stock : 0));
                    }
                }
            }],
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.modifiers_options_notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $changeAmount = $validatedData['paid_amount'] - $validatedData['total_amount'];

            // Lấy ID của người dùng hiện tại
            $userId = Auth::id(); // Lấy ID của người dùng đã xác thực

            // 2. Create the Order
            $order = Order::create([
                'customer_id' => $validatedData['customer_id'],
                'branch_id' => $validatedData['branch_id'], // Store branch_id with the order
                'user_id' => $userId, // THÊM DÒNG NÀY để lưu user_id
                'total_amount' => $validatedData['total_amount'],
                'paid_amount' => $validatedData['paid_amount'],
                'change_amount' => $changeAmount,
                'payment_method' => $validatedData['payment_method'],
                'status' => $validatedData['status'],
                'notes' => $validatedData['notes'],
            ]);

            // 3. Process Order Items and Deduct Stock
            foreach ($validatedData['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']); // Ensure product exists

                // Store product_name_at_time_of_order for historical accuracy
                $productName = $product->name;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product_id'],
                    'product_name_at_time_of_order' => $productName, // Store product name at the time of order
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['quantity'] * $itemData['unit_price'],
                    'modifiers_options_notes' => $itemData['modifiers_options_notes'],
                    'returned_quantity' => 0, // Cung cấp giá trị mặc định cho returned_quantity
                ]);

                // Deduct stock if product tracks stock
                if ($product->track_stock) {
                    $productStock = ProductStock::where('product_id', $itemData['product_id'])
                                                ->where('branch_id', $validatedData['branch_id'])
                                                ->lockForUpdate() // Acquire a lock to prevent race conditions
                                                ->first();

                    if (!$productStock) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Lỗi tồn kho: Không tìm thấy tồn kho cho sản phẩm ' . $product->name . ' tại chi nhánh này.',
                            'product_id' => $itemData['product_id'],
                            'branch_id' => $validatedData['branch_id']
                        ], 400);
                    }
                    if ($productStock->stock < $itemData['quantity']) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Lỗi tồn kho: Sản phẩm ' . $product->name . ' không đủ số lượng. Tồn kho hiện có: ' . $productStock->stock . ', yêu cầu: ' . $itemData['quantity'],
                            'product_id' => $itemData['product_id']
                        ], 400);
                    }
                    $productStock->stock -= $itemData['quantity'];
                    $productStock->save();
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Đơn hàng đã được tạo thành công!',
                'order' => $order->load(['customer', 'orderItems.product', 'user']) // Load relationships for response, include 'user'
            ], 201); // 201 Created

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation Error creating order (OrderController::store): ' . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu đầu vào.',
                'validation_errors' => $e->errors() // Return validation errors
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi tạo đơn hàng (OrderController::store): ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi hoàn tất đơn hàng. Vui lòng kiểm tra log.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the specified order in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            $validatedData = $request->validate([
                'customer_id' => 'nullable|integer|exists:customers,id',
                // 'branch_id' => 'nullable|integer|exists:branches,id', // Bạn có thể thêm vào nếu muốn cập nhật branch_id
                // 'user_id' => 'nullable|integer|exists:users,id', // user_id thường không thay đổi, nhưng có thể thêm nếu cần
                'total_amount' => 'required|numeric|min:0',
                'paid_amount' => 'required|numeric|min:0',
                'payment_method' => 'required|string|max:50',
                // Cập nhật trạng thái cho phép cả refunded và partially_refunded
                'status' => 'required|string|in:pending,completed,cancelled,refunded,partially_refunded', 
                'notes' => 'nullable|string|max:1000',
            ]);

            // Calculate change_amount based on updated paid_amount and total_amount
            $validatedData['change_amount'] = $validatedData['paid_amount'] - $validatedData['total_amount'];

            $order->update($validatedData);

            return response()->json([
                'message' => 'Đơn hàng đã được cập nhật thành công.',
                'order' => $order->load(['customer', 'orderItems.product', 'user']) // Reload relationships, including 'user'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu cập nhật đơn hàng.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Không tìm thấy đơn hàng để cập nhật.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Lỗi khi cập nhật đơn hàng (OrderController::update): ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật đơn hàng. Vui lòng kiểm tra log.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display the specified order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $order = Order::with(['customer', 'orderItems.product', 'user'])->findOrFail($id); // Eager load 'user'
            return response()->json($order);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Order not found (OrderController::show): ' . $e->getMessage());
            return response()->json(['message' => 'Không tìm thấy đơn hàng.'], 404);
        } catch (\Exception $e) {
            \Log::error('Error fetching order (OrderController::show): ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi khi tải đơn hàng.'], 500);
        }
    }
    
    /**
     * Remove the specified order from storage and return product stock.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Giữ nguyên logic xóa toàn bộ đơn hàng và hoàn lại TẤT CẢ tồn kho
            // Phương thức này dùng cho việc HỦY HOÀN TOÀN đơn hàng
            // Đối với trả hàng, sử dụng phương thức `returnItems` mới
            
            DB::beginTransaction(); // Start database transaction

            $order = Order::with('orderItems')->findOrFail($id); // Find order and load its items

            // Return product quantity to stock for all items
            foreach ($order->orderItems as $item) {
                $productStock = ProductStock::where('product_id', $item->product_id)
                                            ->where('branch_id', $order->branch_id) // Assuming order has branch_id
                                            ->first();

                if ($productStock) {
                    // Hoàn lại số lượng đã bán (quantity)
                    $productStock->increment('stock', $item->quantity); 
                    Log::info("Hoàn lại tồn kho cho sản phẩm ID: {$item->product_id}, Số lượng: {$item->quantity}. Tồn kho mới: {$productStock->stock}");
                } else {
                    Log::warning("Không tìm thấy tồn kho cho sản phẩm ID: {$item->product_id} tại chi nhánh ID: {$order->branch_id} khi xóa đơn hàng ID: {$id}.");
                }
            }

            // Delete all related order items
            $order->orderItems()->delete();

            // Finally, delete the order
            $order->delete();

            DB::commit(); // End transaction successfully

            Log::info("Đơn hàng ID: {$id} đã được xóa thành công và tồn kho đã được hoàn lại.");

            return response()->json([
                'message' => 'Đơn hàng đã được xóa thành công.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack(); // Rollback transaction if order not found
            Log::error('Order deletion error: Order ID not found: ' . $id);
            return response()->json([
                'message' => 'Không tìm thấy đơn hàng để xóa.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction if other error occurs
            Log::error('Error deleting order (OrderController::destroy): ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa đơn hàng. Vui lòng kiểm tra log.',
                'error_detail' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process item returns for an order, updating returned quantities and stock.
     * This method handles partial or full returns without deleting the main order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id The order ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function returnItems(Request $request, $id)
    {
        $order = Order::with('orderItems.product.productStocks')->findOrFail($id);

        $validatedData = $request->validate([
            'returned_items' => 'required|array',
            'returned_items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'returned_items.*.quantity_to_return' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $anyItemReturned = false;
            foreach ($validatedData['returned_items'] as $returnItemData) {
                $orderItem = $order->orderItems->firstWhere('id', $returnItemData['order_item_id']);

                if (!$orderItem) {
                    DB::rollBack();
                    return response()->json(['message' => 'Mục đơn hàng không hợp lệ.'], 400);
                }

                $maxReturnableQuantity = $orderItem->quantity - $orderItem->returned_quantity;

                if ($returnItemData['quantity_to_return'] > $maxReturnableQuantity) {
                    DB::rollBack();
                    return response()->json(['message' => 'Số lượng trả hàng vượt quá số lượng còn lại có thể trả cho sản phẩm ' . ($orderItem->product->name ?? 'không rõ') . '.'], 400);
                }

                // Update returned_quantity for the order item
                $orderItem->returned_quantity += $returnItemData['quantity_to_return'];
                $orderItem->save();
                $anyItemReturned = true;

                // Return stock for the product at the relevant branch
                $product = $orderItem->product;
                if ($product && $product->track_stock) {
                    $productStock = ProductStock::where('product_id', $product->id)
                                                ->where('branch_id', $order->branch_id)
                                                ->first();
                    if ($productStock) {
                        $productStock->increment('stock', $returnItemData['quantity_to_return']);
                    } else {
                        Log::warning("Không tìm thấy tồn kho cho sản phẩm ID: {$product->id} tại chi nhánh ID: {$order->branch_id} khi hoàn hàng.");
                    }
                }
            }

            // Update order status if items were returned
            if ($anyItemReturned) {
                $allItemsReturned = true;
                foreach ($order->orderItems as $item) {
                    if ($item->quantity > $item->returned_quantity) {
                        $allItemsReturned = false;
                        break;
                    }
                }

                if ($allItemsReturned) {
                    $order->status = 'refunded'; // All items returned
                } else {
                    $order->status = 'partially_refunded'; // Some items returned
                }
                $order->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Đơn hàng đã được hoàn trả thành công.',
                'order' => $order->load(['customer', 'orderItems.product', 'user'])
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation Error returning order items (OrderController::returnItems): ' . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu đầu vào khi hoàn trả hàng.',
                'validation_errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing return (OrderController::returnItems): ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xử lý hoàn trả hàng. Vui lòng kiểm tra log.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}
