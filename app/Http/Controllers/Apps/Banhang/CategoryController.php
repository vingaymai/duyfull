<?php

namespace App\Http\Controllers\Apps\Banhang; // Đảm bảo namespace này khớp với đường dẫn thư mục của bạn

use App\Http\Controllers\Controller;
use App\Models\Banhang\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CategoriesExport;
use App\Imports\CategoriesImport;

class CategoryController extends Controller
{
    /**
     * Hiển thị danh sách các danh mục.
     * Trả về tất cả danh mục (có eager load quan hệ cha) để frontend xử lý phân cấp, tìm kiếm, phân trang.
     */
    public function index(Request $request)
    {
        // Luôn trả về tất cả danh mục với quan hệ cha được eager load
        // Việc tìm kiếm và phân trang sẽ được xử lý ở phía frontend
        return response()->json(Category::with('parent')->get());
    }

    /**
     * Lưu một danh mục mới vào cơ sở dữ liệu.
     */
    public function store(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'slug' => [
                    'required',
                    'string',
                    'max:255',
                    // Đảm bảo slug là duy nhất trong bảng 'categories'
                    Rule::unique('categories', 'slug'),
                ],
                // parent_id có thể null, hoặc phải tồn tại trong bảng 'categories'
                'parent_id' => 'nullable|exists:categories,id',
                'position' => 'nullable|integer|min:0',
                'active' => 'boolean',
            ]);

            // Tạo danh mục mới
            $category = Category::create([
                'name' => $validatedData['name'],
                'slug' => $validatedData['slug'],
                'parent_id' => $validatedData['parent_id'],
                'position' => $validatedData['position'] ?? 0, // Gán giá trị mặc định nếu không được cung cấp
                'active' => $validatedData['active'] ?? true, // Gán giá trị mặc định nếu không được cung cấp
            ]);

            return response()->json([
                'message' => 'Danh mục đã được thêm mới thành công.',
                'category' => $category
            ], 201); // Trả về trạng thái 201 Created
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Xử lý lỗi xác thực
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            // Log lỗi để gỡ lỗi nội bộ
            \Log::error('Lỗi khi thêm danh mục: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi lưu danh mục. Vui lòng thử lại.'
            ], 500); // 500 Internal Server Error
        }
    }

    /**
     * Cập nhật danh mục đã chỉ định.
     */
    public function update(Request $request, Category $category)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'slug' => [
                    'required',
                    'string',
                    'max:255',
                    // Đảm bảo slug là duy nhất, nhưng bỏ qua slug của chính danh mục đang chỉnh sửa
                    Rule::unique('categories', 'slug')->ignore($category->id),
                ],
                'parent_id' => [
                    'nullable',
                    'exists:categories,id',
                    // Đảm bảo danh mục không thể là cha của chính nó
                    function ($attribute, $value, $fail) use ($category) {
                        if ($value === $category->id) {
                            $fail('Danh mục không thể là cha của chính nó.');
                        }
                        // Thêm logic để ngăn chặn quan hệ tuần hoàn (ví dụ: A -> B -> A)
                        // Đây là một tác vụ phức tạp hơn, cần duyệt cây đệ quy nếu bạn muốn kiểm tra sâu.
                        // Để đơn giản, không triển khai ở đây.
                    },
                ],
                'position' => 'nullable|integer|min:0',
                'active' => 'boolean',
            ]);

            // Cập nhật danh mục
            $category->update([
                'name' => $validatedData['name'],
                'slug' => $validatedData['slug'],
                'parent_id' => $validatedData['parent_id'],
                'position' => $validatedData['position'] ?? $category->position, // Giữ nguyên vị trí nếu không gửi
                'active' => $validatedData['active'] ?? $category->active, // Giữ nguyên trạng thái nếu không gửi
            ]);

            return response()->json([
                'message' => 'Danh mục đã được cập nhật thành công.',
                'category' => $category
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Lỗi khi cập nhật danh mục: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật danh mục. Vui lòng thử lại.'
            ], 500);
        }
    }

    /**
     * Xóa danh mục đã chỉ định.
     */
    public function destroy(Category $category)
    {
        try {
            // Trước khi xóa danh mục cha, hãy cập nhật parent_id của các danh mục con thành null
            // Điều này giúp tránh lỗi ràng buộc khóa ngoại và không làm mất dữ liệu danh mục con
            Category::where('parent_id', $category->id)->update(['parent_id' => null]);

            $category->delete();

            return response()->json(['message' => 'Danh mục đã được xóa thành công.'], 200);
        } catch (\Exception $e) {
            \Log::error('Lỗi khi xóa danh mục: ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi khi xóa danh mục.'], 500);
        }
    }

    /**
     * Lấy danh sách các danh mục có thể làm cha.
     */
    public function parents()
    {
        // Bạn có thể tùy chỉnh logic này: ví dụ, chỉ lấy danh mục cấp 1, hoặc chỉ danh mục đang hoạt động.
        // Hiện tại, tôi sẽ lấy tất cả danh mục đang hoạt động.
        return response()->json(Category::where('active', true)->get());
    }

    /**
     * Export danh mục ra file Excel.
     */
    public function export()
    {
        try {
            $filename = 'danh_muc_san_pham_' . now()->format('Ymd_His') . '.xlsx';
            return Excel::download(new CategoriesExport, $filename);
        } catch (\Exception $e) {
            \Log::error('Lỗi khi xuất danh mục ra Excel: ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi khi xuất dữ liệu ra Excel. Vui lòng thử lại.'], 500);
        }
    }

    /**
     * Các phương thức khác (chưa triển khai chi tiết trong ví dụ này)
     */
    public function deleteMultiple(Request $request)
    {
        return response()->json(['message' => 'Chức năng xóa nhiều chưa được triển khai.'], 501);
    }

    public function toggleActive(Request $request)
    {
        return response()->json(['message' => 'Chức năng chuyển đổi trạng thái chưa được triển khai.'], 501);
    }

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
            $import = new CategoriesImport();
            Excel::import($import, $request->file('file'));

            $validationErrors = [];
            if (isset($import->errors) && count($import->errors) > 0) {
                foreach ($import->errors as $failure) {
                    $validationErrors[] = [
                        'row' => $failure->row(),
                        'attribute' => $failure->attribute(),
                        'errors' => $failure->errors(),
                        'values' => $failure->values(),
                    ];
                }
            }

            $message = 'Danh mục đã được nhập thành công.';
            if (count($validationErrors) > 0) {
                $message .= ' Có ' . count($validationErrors) . ' lỗi validation trong file.';
            }

            return response()->json([
                'message' => $message,
                'validation_errors' => $validationErrors
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
            return response()->json([
                'message' => 'Có lỗi xảy ra khi nhập file Excel.',
                'validation_errors' => $errors
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Lỗi khi nhập Excel: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi nhập dữ liệu Excel. Vui lòng kiểm tra log.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}
