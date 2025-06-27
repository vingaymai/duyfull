<?php

namespace App\Http\Controllers\Apps\Banhang;

use App\Http\Controllers\Controller;
use App\Models\Banhang\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log; // For logging errors
use Illuminate\Support\Facades\DB; // For database transactions

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Customer::query();

            // Apply search filter if provided
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                      ->orWhere('phone', 'like', '%' . $search . '%')
                      ->orWhere('address', 'like', '%' . $search . '%');
            }

            $customers = $query->get();
            return response()->json($customers);
        } catch (\Exception $e) {
            Log::error('Error fetching customers: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải danh sách khách hàng.'], 500);
        }
    }

    /**
     * Store a newly created customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255|unique:customers,email',
                'phone' => 'nullable|string|max:20|unique:customers,phone',
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'date_of_birth' => 'nullable|date',
                'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
                'active' => 'boolean',
            ]);

            $customer = Customer::create($validatedData);

            DB::commit();

            return response()->json([
                'message' => 'Khách hàng đã được thêm mới thành công.',
                'customer' => $customer
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation Error storing customer: ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing customer: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi lưu khách hàng. Vui lòng thử lại.'
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Customer $customer)
    {
        try {
            return response()->json($customer);
        } catch (\Exception $e) {
            Log::error('Error showing customer ' . $customer->id . ': ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải chi tiết khách hàng.'], 500);
        }
    }

    /**
     * Update the specified customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Customer $customer)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
                'phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($customer->id)],
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'date_of_birth' => 'nullable|date',
                'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
                'active' => 'boolean',
            ]);

            $customer->update($validatedData);

            DB::commit();

            return response()->json([
                'message' => 'Khách hàng đã được cập nhật thành công.',
                'customer' => $customer
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation Error updating customer: ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating customer: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật khách hàng. Vui lòng thử lại.'
            ], 500);
        }
    }

    /**
     * Remove the specified customer from storage.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Customer $customer)
    {
        try {
            DB::beginTransaction();
            $customer->delete();
            DB::commit();
            return response()->json(['message' => 'Khách hàng đã được xóa thành công.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting customer: ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi khi xóa khách hàng.'], 500);
        }
    }
}
