<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Str; // Thêm thư viện Str để xử lý chuỗi nếu cần

class ProductsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * Lấy dữ liệu collection để xuất.
    * Eager load các mối quan hệ cần thiết (danh mục, tùy chọn, tồn kho chi nhánh, bổ sung)
    * để có đủ dữ liệu cho việc ánh xạ (mapping).
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Product::with(['category', 'productOptions', 'productStocks.branch', 'modifiers'])->get();
    }

    /**
     * Định nghĩa các tiêu đề cột cho file Excel.
     * Các tiêu đề này được thiết kế để khớp với các trường dự kiến trong file nhập Excel
     * để dễ dàng nhập lại sau khi xuất.
     * @return array
     */
    public function headings(): array
    {
        $headings = [
            'name',
            'sku',
            'barcode',
            'category_slug',
            'category_name', // Cột này chỉ để tham khảo khi xuất, không dùng để import trực tiếp (import dùng category_slug)
            'description',
            'image_url',
            'base_price',
            'cost_price',
            'sold_by_weight',
            'unit',
            'track_stock',
            'active',
        ];

        // Thêm tiêu đề cho tối đa 3 tùy chọn sản phẩm (option_1, option_2, option_3)
        // Mỗi tùy chọn có tên, giá trị và vị trí
        for ($i = 1; $i <= 3; $i++) {
            $headings[] = "option_{$i}_name";
            $headings[] = "option_{$i}_values";
            $headings[] = "option_{$i}_position";
        }

        // Thêm tiêu đề cho tối đa 3 thông tin tồn kho theo chi nhánh
        // Mỗi tồn kho có tên chi nhánh, số lượng, ngưỡng thấp, trạng thái có sẵn và giá ghi đè
        for ($i = 1; $i <= 3; $i++) {
            $headings[] = "stock_{$i}_branch_name";
            $headings[] = "stock_{$i}_quantity";
            $headings[] = "stock_{$i}_low_threshold";
            $headings[] = "stock_{$i}_available";
            $headings[] = "stock_{$i}_price_override";
        }

        // Thêm tiêu đề cho tối đa 3 bổ sung (modifiers)
        // Mỗi bổ sung có tên và giá
        for ($i = 1; $i <= 3; $i++) {
            $headings[] = "modifier_{$i}_name";
            $headings[] = "modifier_{$i}_price";
        }

        return $headings;
    }

    /**
     * Ánh xạ dữ liệu từ mỗi đối tượng Product vào một hàng của Excel.
     * Phương thức này chịu trách nhiệm biến đổi dữ liệu từ các object của Laravel
     * thành một mảng phẳng phù hợp với các cột trong Excel.
     * @param mixed $product Đối tượng Product hiện tại
     * @return array Mảng dữ liệu cho một hàng trong Excel
     */
    public function map($product): array
    {
        $rowData = [
            $product->name,
            $product->sku,
            $product->barcode ?: null, // Sử dụng null thay vì '-' để nhất quán với null trong DB
            $product->category ? $product->category->slug : null,
            $product->category ? $product->category->name : null,
            $product->description,
            $product->image_url,
            $product->base_price,
            $product->cost_price,
            (int) $product->sold_by_weight, // Chuyển đổi boolean sang 0 hoặc 1
            $product->unit,
            (int) $product->track_stock,   // Chuyển đổi boolean sang 0 hoặc 1
            (int) $product->active,        // Chuyển đổi boolean sang 0 hoặc 1
        ];

        // Ánh xạ các tùy chọn sản phẩm (Product Options)
        // Sắp xếp các tùy chọn theo vị trí để đảm bảo thứ tự nhất quán
        $productOptions = $product->productOptions->sortBy('position');
        for ($i = 0; $i < 3; $i++) { // Lặp qua tối đa 3 tùy chọn
            $option = $productOptions->get($i);
            if ($option) {
                $rowData[] = $option->name;
                // Giá trị tùy chọn được lưu dưới dạng array (do casting trong model),
                // cần json_encode lại để chuyển thành chuỗi JSON trong Excel.
                $rowData[] = json_encode($option->values); 
                $rowData[] = $option->position;
            } else {
                // Nếu không có tùy chọn, điền null vào các cột tương ứng
                $rowData[] = null; // option_X_name
                $rowData[] = null; // option_X_values
                $rowData[] = null; // option_X_position
            }
        }

        // Ánh xạ các tồn kho chi nhánh (Product Stocks)
        // Sắp xếp tồn kho theo tên chi nhánh để có thứ tự nhất quán
        $productStocks = $product->productStocks->sortBy('branch.name');
        for ($i = 0; $i < 3; $i++) { // Lặp qua tối đa 3 tồn kho
            $stock = $productStocks->get($i);
            if ($stock && $stock->branch) { // Đảm bảo mối quan hệ chi nhánh tồn tại
                $rowData[] = $stock->branch->name;
                $rowData[] = $stock->stock;
                $rowData[] = $stock->low_stock_threshold;
                $rowData[] = (int) $stock->available; // Chuyển đổi boolean sang 0 hoặc 1
                $rowData[] = $stock->price_override;
            } else {
                // Nếu không có thông tin tồn kho cho chi nhánh, điền null
                $rowData[] = null; // stock_X_branch_name
                $rowData[] = null; // stock_X_quantity
                $rowData[] = null; // stock_X_low_threshold
                $rowData[] = null; // stock_X_available
                $rowData[] = null; // stock_X_price_override
            }
        }

        // Ánh xạ các bổ sung (Modifiers)
        $productModifiers = $product->modifiers;
        for ($i = 0; $i < 3; $i++) { // Lặp qua tối đa 3 bổ sung
            $modifier = $productModifiers->get($i);
            if ($modifier) {
                $rowData[] = $modifier->name;
                $rowData[] = $modifier->price;
            } else {
                // Nếu không có bổ sung, điền null
                $rowData[] = null; // modifier_X_name
                $rowData[] = null; // modifier_X_price
            }
        }

        return $rowData;
    }
}
