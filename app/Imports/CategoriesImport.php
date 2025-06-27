<?php

namespace App\Imports;

use App\Models\Category;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure; // Import Failure for handling validation errors

class CategoriesImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * Thuộc tính để lưu trữ lỗi validation từ onValidationFailure.
     * @var Failure[]
     */
    public $errors = [];

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Kiểm tra xem name và slug có tồn tại không trước khi tạo hoặc cập nhật
        // Sử dụng isset() thay vì empty() để tránh lỗi nếu giá trị là '0' hoặc false
        if (!isset($row['name']) || !isset($row['slug'])) {
            return null; // Bỏ qua hàng nếu dữ liệu tối thiểu không có
        }

        // Tìm danh mục cha dựa trên slug hoặc name (nếu có trong file Excel)
        $parentId = null;
        if (isset($row['parent_slug']) && !empty($row['parent_slug'])) {
            $parentCategory = Category::where('slug', $row['parent_slug'])->first();
            if ($parentCategory) {
                $parentId = $parentCategory->id;
            }
        } elseif (isset($row['parent_name']) && !empty($row['parent_name'])) {
            $parentCategory = Category::where('name', $row['parent_name'])->first();
            if ($parentCategory) {
                $parentId = $parentCategory->id;
            }
        }

        // Tìm kiếm danh mục dựa trên slug để cập nhật hoặc tạo mới
        $category = Category::firstOrNew(['slug' => $row['slug']]);

        $category->name = $row['name'];
        // Sửa lỗi: Sử dụng isset() để kiểm tra key trong mảng $row
        $category->slug = isset($row['slug']) && !empty($row['slug']) ? $row['slug'] : \Illuminate\Support\Str::slug($row['name']);
        
        $category->parent_id = $parentId;
        $category->position = isset($row['position']) ? (int) $row['position'] : 0; // Đảm bảo position là số nguyên
        $category->active = isset($row['active']) ? (bool) $row['active'] : true; // Chuyển đổi sang boolean

        return $category;
    }

    /**
     * Định nghĩa hàng tiêu đề trong file Excel.
     * @return int
     */
    public function headingRow(): int
    {
        return 1; // Hàng đầu tiên trong Excel là tiêu đề
    }

    /**
     * Định nghĩa các quy tắc xác thực cho mỗi hàng.
     * Các trường trong quy tắc này phải khớp với tiêu đề cột trong file Excel (đã được làm sạch bởi WithHeadingRow).
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                // Để slug là duy nhất, hãy bỏ comment dòng này nếu bạn muốn kiểm tra trùng lặp
                // khi import danh mục mới. Nếu import là để cập nhật thì không cần unique.
                // Rule::unique('categories', 'slug'),
            ],
            'parent_slug' => ['nullable', 'string', 'max:255'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
            // 'active' cần phải là boolean, Excel thường trả về 0 hoặc 1.
            'active' => ['nullable', 'in:0,1,true,false'], // Chấp nhận 0, 1, true, false
        ];
    }

    /**
     * Tùy chỉnh thông báo lỗi xác thực.
     * @return array
     */
    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên danh mục là bắt buộc.',
            'slug.required' => 'Slug là bắt buộc.',
            // 'slug.unique' => 'Slug đã tồn tại.',
            'position.integer' => 'Vị trí phải là số nguyên.',
            'position.min' => 'Vị trí không được nhỏ hơn :min.',
            'active.in' => 'Trạng thái hoạt động phải là 0, 1, true, hoặc false.',
        ];
    }

    /**
     * Xử lý lỗi xác thực hàng.
     *
     * @param Failure[] $failures
     */
    public function onValidationFailure(Failure ...$failures)
    {
        $this->errors = array_merge($this->errors, $failures); // Gộp các lỗi mới vào danh sách
    }
}
