<?php

namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CategoriesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * Lấy dữ liệu collection để xuất.
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Lấy tất cả danh mục cùng với thông tin danh mục cha
        // Điều này là cần thiết để lấy parent_name và parent_slug
        return Category::with('parent')->get();
    }

    /**
     * Định nghĩa các tiêu đề cột cho file Excel.
     * Các tiêu đề này sẽ khớp với các trường trong file nhập Excel.
     * @return array
     */
    public function headings(): array
    {
        return [
            'name',          // Tên danh mục
            'slug',          // Slug
            'parent_slug',   // Slug của danh mục cha
            'parent_name',   // Tên của danh mục cha
            'position',      // Vị trí
            'active',        // Trạng thái kích hoạt (1 hoặc 0)
        ];
    }

    /**
     * Ánh xạ dữ liệu từ mỗi đối tượng Category vào một hàng của Excel.
     * @param Category $category
     * @return array
     */
    public function map($category): array
    {
        return [
            $category->name,
            $category->slug,
            // Lấy slug của danh mục cha nếu tồn tại, ngược lại là null
            $category->parent ? $category->parent->slug : null,
            // Lấy tên của danh mục cha nếu tồn tại, ngược lại là null
            $category->parent ? $category->parent->name : null,
            $category->position,
            // Chuyển đổi boolean thành 1 hoặc 0
            (int) $category->active,
        ];
    }
}
