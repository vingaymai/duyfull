<?php

namespace App\Http\Controllers\Apps\Banhang;

use App\Http\Controllers\Controller;
use App\Models\Banhang\Branch; // Make sure the Branch model exists
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    /**
     * Display a listing of the branches.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Fetch all branches, ordered by name by default
            $query = Branch::orderBy('name');

            // Optional: Add search functionality if needed (e.g., by name or address)
            if ($request->has('search') && $request->input('search') !== null) {
                $searchTerm = '%' . $request->input('search') . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                      ->orWhere('address', 'like', $searchTerm)
                      ->orWhere('phone', 'like', $searchTerm);
                });
            }

            $branches = $query->get(); // Get all results without pagination for now for simplicity on frontend
            return response()->json($branches);
        } catch (\Exception $e) {
            Log::error('Error fetching branches: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải danh sách chi nhánh.'], 500);
        }
    }

    /**
     * Store a newly created branch in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:branches,name',
                'address' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'manager_name' => 'nullable|string|max:255',
                'active' => 'boolean',
            ]);

            $branch = Branch::create($validatedData);

            return response()->json([
                'message' => 'Chi nhánh đã được thêm mới thành công.',
                'branch' => $branch
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error storing branch: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi lưu chi nhánh. Vui lòng thử lại.'
            ], 500);
        }
    }

    /**
     * Display the specified branch.
     *
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Branch $branch)
    {
        try {
            return response()->json($branch);
        } catch (\Exception $e) {
            Log::error('Error showing branch ' . $branch->id . ': ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi khi tải chi tiết chi nhánh.'], 500);
        }
    }

    /**
     * Update the specified branch in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Branch $branch)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->ignore($branch->id)],
                'address' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'manager_name' => 'nullable|string|max:255',
                'active' => 'boolean',
            ]);

            $branch->update($validatedData);

            return response()->json([
                'message' => 'Chi nhánh đã được cập nhật thành công.',
                'branch' => $branch
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating branch: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật chi nhánh. Vui lòng thử lại.'
            ], 500);
        }
    }

    /**
     * Remove the specified branch from storage.
     *
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Branch $branch)
    {
        try {
            // Optional: Add logic to prevent deletion if branch has associated products, orders, etc.
            // For example: if ($branch->products()->count() > 0) { return response()->json([...]); }

            $branch->delete();

            return response()->json(['message' => 'Chi nhánh đã được xóa thành công.'], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting branch: ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi khi xóa chi nhánh.'], 500);
        }
    }
}
