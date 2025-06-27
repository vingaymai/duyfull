<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Branch;
use App\Models\ProductOption;
use App\Models\ProductStock;
use App\Models\Modifier;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Str;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation
{
    use Importable;

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
        // Debugging: Log the raw row data if needed
        // \Log::info('Processing row for import: ' . json_encode($row));

        // Basic check for required fields (name, sku) before processing
        // If these are missing, we cannot identify or create a product.
        if (!isset($row['name']) || empty($row['name']) || !isset($row['sku']) || empty($row['sku'])) {
            // This row is invalid, the validation rules should catch this more formally.
            // For model method, return null to skip processing.
            return null;
        }

        // --- 1. Resolve Category ID ---
        $categoryId = null;
        if (isset($row['category_slug']) && !empty($row['category_slug'])) {
            $category = Category::where('slug', $row['category_slug'])->first();
            if ($category) {
                $categoryId = $category->id;
            } else {
                // Optionally log a warning if category not found by slug
                // This would typically be caught by validation if category_id is 'required' or 'exists'
            }
        }
        // If slug is not found or provided, try category_name as a fallback (less precise)
        elseif (isset($row['category_name']) && !empty($row['category_name'])) {
            $category = Category::where('name', $row['category_name'])->first();
            if ($category) {
                $categoryId = $category->id;
            }
        }

        // --- 2. Create or Update Product ---
        // Find existing product by SKU or create a new one.
        // If SKU exists, update. If not, create.
        $product = Product::firstOrNew(['sku' => $row['sku']]);

        // Assign product attributes. Use 'isset' for nullable fields to ensure
        // null is explicitly set if the Excel cell is empty/null, otherwise default.
        $product->name = $row['name'];
        // Ép kiểu barcode thành chuỗi ở đây, vì validation đã cho phép cả số.
        $product->barcode = isset($row['barcode']) ? (string) $row['barcode'] : null;
        $product->description = $row['description'] ?? null;
        $product->image_url = $row['image_url'] ?? null;
        $product->base_price = $row['base_price'] ?? 0;
        $product->cost_price = $row['cost_price'] ?? null;
        $product->sold_by_weight = isset($row['sold_by_weight']) ? (bool) $row['sold_by_weight'] : false;
        $product->unit = $row['unit'] ?? 'cái';
        $product->track_stock = isset($row['track_stock']) ? (bool) $row['track_stock'] : true;
        $product->active = isset($row['active']) ? (bool) $row['active'] : true;
        $product->category_id = $categoryId; // Assign resolved category ID

        // Save the product model. This is crucial before creating/updating relationships
        // because relationships require the parent product to have an ID.
        $product->save();

        // --- 3. Handle Product Options ---
        // For re-importing, we assume the Excel file is the source of truth for options.
        // So, delete existing options and re-create them from the Excel row.
        $product->productOptions()->delete(); // Delete all existing options for this product

        for ($i = 1; $i <= 3; $i++) {
            $optionName = $row["option_{$i}_name"] ?? null;
            $optionValues = $row["option_{$i}_values"] ?? null;
            $optionPosition = $row["option_{$i}_position"] ?? ($i - 1); // Default position

            if (!empty($optionName) && !empty($optionValues)) {
                // Attempt to decode JSON, otherwise treat as comma-separated string
                // If the Excel export provides a JSON string like '["value1", "value2"]', json_decode handles it.
                // If it provides a simple string like 'value1,value2', explode handles it.
                $valuesArray = is_string($optionValues) ? (json_decode($optionValues, true) ?? array_map('trim', explode(',', $optionValues))) : $optionValues;
                
                // Ensure valuesArray is an actual array
                if (!is_array($valuesArray)) {
                    $valuesArray = [$valuesArray];
                }

                $product->productOptions()->create([
                    'name' => $optionName,
                    'values' => $valuesArray, // KHÔNG json_encode ở đây! Model ProductOption sẽ tự cast sang array và lưu JSON.
                    'position' => $optionPosition,
                ]);
            }
        }

        // --- 4. Handle Product Stocks ---
        // Similar to options, delete existing stocks and re-create from Excel data.
        $product->productStocks()->delete(); // Delete all existing stocks for this product

        if ($product->track_stock) { // Only process stocks if tracking stock is enabled
            for ($i = 1; $i <= 3; $i++) {
                $branchName = $row["stock_{$i}_branch_name"] ?? null;
                $quantity = $row["stock_{$i}_quantity"] ?? 0;
                $lowThreshold = $row["stock_{$i}_low_threshold"] ?? 5;
                $available = isset($row["stock_{$i}_available"]) ? (bool)$row["stock_{$i}_available"] : true;
                $priceOverride = $row["stock_{$i}_price_override"] ?? null;

                if (!empty($branchName)) {
                    $branch = Branch::where('name', $branchName)->first();
                    if ($branch) {
                        $product->productStocks()->create([
                            'branch_id' => $branch->id,
                            'stock' => (int) $quantity,
                            'low_stock_threshold' => (int) $lowThreshold,
                            'available' => $available,
                            'price_override' => $priceOverride,
                        ]);
                    } else {
                        // Log a warning or add to validation errors if branch not found
                        \Log::warning("Branch with name '{$branchName}' not found for product '{$product->name}'. Stock entry skipped.");
                    }
                }
            }
        }

        // --- 5. Handle Modifiers ---
        // Similar to options, delete existing modifiers and re-create.
        $product->modifiers()->delete(); // Delete all existing modifiers for this product

        for ($i = 1; $i <= 3; $i++) {
            $modifierName = $row["modifier_{$i}_name"] ?? null;
            $modifierPrice = $row["modifier_{$i}_price"] ?? null;

            // Only create modifier if name is not empty and price is numeric
            if (!empty($modifierName) && is_numeric($modifierPrice)) {
                $product->modifiers()->create([
                    'name' => $modifierName,
                    'price' => (float) $modifierPrice,
                ]);
            }
        }

        return $product;
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
            'sku' => [
                'required',
                'string',
                'max:255',
                // Rule::unique('products', 'sku'), // Bỏ comment nếu muốn SKU duy nhất khi import
            ],
            // Cho phép barcode là bất kỳ kiểu nào, sẽ được ép kiểu thành string trong model()
            'barcode' => ['nullable', 'max:255'], 
            'category_slug' => ['nullable', 'string', 'max:255'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sold_by_weight' => ['nullable', 'boolean'],
            'unit' => ['required', 'string', 'max:50'],
            'track_stock' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],

            // Rules for Product Options
            'option_1_name' => ['nullable', 'string', 'max:255'],
            'option_1_values' => ['nullable', 'string'], // Values are expected as a JSON string or comma-separated
            'option_1_position' => ['nullable', 'integer', 'min:0'],

            'option_2_name' => ['nullable', 'string', 'max:255'],
            'option_2_values' => ['nullable', 'string'],
            'option_2_position' => ['nullable', 'integer', 'min:0'],

            'option_3_name' => ['nullable', 'string', 'max:255'],
            'option_3_values' => ['nullable', 'string'],
            'option_3_position' => ['nullable', 'integer', 'min:0'],

            // Rules for Product Stocks
            'stock_1_branch_name' => ['nullable', 'string', 'max:255'],
            'stock_1_quantity' => ['nullable', 'integer', 'min:0'],
            'stock_1_low_threshold' => ['nullable', 'integer', 'min:0'],
            'stock_1_available' => ['nullable', 'boolean'],
            'stock_1_price_override' => ['nullable', 'numeric', 'min:0'],

            'stock_2_branch_name' => ['nullable', 'string', 'max:255'],
            'stock_2_quantity' => ['nullable', 'integer', 'min:0'],
            'stock_2_low_threshold' => ['nullable', 'integer', 'min:0'],
            'stock_2_available' => ['nullable', 'boolean'],
            'stock_2_price_override' => ['nullable', 'numeric', 'min:0'],

            'stock_3_branch_name' => ['nullable', 'string', 'max:255'],
            'stock_3_quantity' => ['nullable', 'integer', 'min:0'],
            'stock_3_low_threshold' => ['nullable', 'integer', 'min:0'],
            'stock_3_available' => ['nullable', 'boolean'],
            'stock_3_price_override' => ['nullable', 'numeric', 'min:0'],

            // Rules for Modifiers
            'modifier_1_name' => ['nullable', 'string', 'max:255'],
            'modifier_1_price' => ['nullable', 'numeric', 'min:0'],

            'modifier_2_name' => ['nullable', 'string', 'max:255'],
            'modifier_2_price' => ['nullable', 'numeric', 'min:0'],

            'modifier_3_name' => ['nullable', 'string', 'max:255'],
            'modifier_3_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Tùy chỉnh thông báo lỗi xác thực.
     * @return array
     */
    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên sản phẩm là bắt buộc.',
            'sku.required' => 'SKU là bắt buộc.',
            'sku.unique' => 'SKU đã tồn tại.',
            'barcode.max' => 'Mã vạch không được vượt quá :max ký tự.',
            'category_slug.string' => 'Slug danh mục phải là chuỗi.',
            'category_name.string' => 'Tên danh mục phải là chuỗi.',
            'description.string' => 'Mô tả phải là chuỗi.',
            'image_url.url' => 'URL ảnh không hợp lệ.',
            'base_price.required' => 'Giá gốc là bắt buộc.',
            'base_price.numeric' => 'Giá gốc phải là số.',
            'base_price.min' => 'Giá gốc không được nhỏ hơn :min.',
            'cost_price.numeric' => 'Giá vốn phải là số.',
            'sold_by_weight.boolean' => 'Giá trị "Bán bởi trọng lượng" phải là boolean (0 hoặc 1).',
            'unit.required' => 'Đơn vị là bắt buộc.',
            'unit.string' => 'Đơn vị phải là chuỗi.',
            'track_stock.boolean' => 'Giá trị "Theo dõi tồn kho" phải là boolean (0 hoặc 1).',
            'active.boolean' => 'Trạng thái hoạt động phải là boolean (0 hoặc 1).',

            // Option errors
            'option_*.name.string' => 'Tên tùy chọn phải là chuỗi.',
            'option_*.name.max' => 'Tên tùy chọn không được vượt quá :max ký tự.',
            'option_*.values.string' => 'Giá trị tùy chọn phải là chuỗi (JSON hoặc phân tách bằng dấu phẩy).',
            'option_*.position.integer' => 'Vị trí tùy chọn phải là số nguyên.',
            'option_*.position.min' => 'Vị trí tùy chọn không được nhỏ hơn :min.',

            // Stock errors
            'stock_*.branch_name.string' => 'Tên chi nhánh phải là chuỗi.',
            'stock_*.branch_name.max' => 'Tên chi nhánh không được vượt quá :max ký tự.',
            'stock_*.quantity.integer' => 'Số lượng tồn kho phải là số nguyên.',
            'stock_*.quantity.min' => 'Số lượng tồn kho không được nhỏ hơn :min.',
            'stock_*.low_threshold.integer' => 'Ngưỡng thấp phải là số nguyên.',
            'stock_*.low_threshold.min' => 'Ngưỡng thấp không được nhỏ hơn :min.',
            'stock_*.available.boolean' => 'Trạng thái có sẵn phải là boolean (0 hoặc 1).',
            'stock_*.price_override.numeric' => 'Giá ghi đè phải là số.',
            'stock_*.price_override.min' => 'Giá ghi đè không được nhỏ hơn :min.',

            // Modifier errors
            'modifier_*.name.string' => 'Tên bổ sung phải là chuỗi.',
            'modifier_*.name.max' => 'Tên bổ sung không được vượt quá :max ký tự.',
            'modifier_*.price.numeric' => 'Giá bổ sung phải là số.',
            'modifier_*.price.min' => 'Giá bổ sung không được nhỏ hơn :min.',
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
