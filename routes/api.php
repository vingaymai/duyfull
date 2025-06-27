<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Đảm bảo TẤT CẢ các Controllers được import một lần duy nhất ở đây
use App\Http\Controllers\Apps\Banhang\CategoryController;
use App\Http\Controllers\Apps\Banhang\ProductController;
use App\Http\Controllers\Apps\Banhang\BranchController;
use App\Http\Controllers\Apps\Banhang\CustomerController;
use App\Http\Controllers\Apps\Banhang\OrderController;
use App\Http\Controllers\Apps\Banhang\SalesProductController;
use App\Http\Controllers\Apps\Banhang\UserController;
use App\Http\Controllers\Apps\Banhang\RoleController;
use App\Http\Controllers\Apps\Banhang\PermissionController;
use App\Http\Controllers\Apps\Banhang\ReportController;
use App\Http\Controllers\Apps\Banhang\BranchManagerController; 
use App\Http\Controllers\Apps\Banhang\AuthController; 

// Lấy CSRF cookie cho Sanctum
Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response('OK');
});

Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.logout');

// API để lấy thông tin người dùng đã xác thực (Cần middleware auth:sanctum)
//Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum')->name('api.user');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



// Định nghĩa route cho /api/me
// Đảm bảo AuthController và phương thức 'me' tồn tại và trả về JSON.
Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);


// TẤT CẢ CÁC API ROUTES YÊU CẦU XÁC THỰC SẼ NẰM TRONG KHỐI NÀY
Route::middleware('auth:sanctum')->group(function () {

// Route để lấy thông tin người dùng đã xác thực
Route::get('/user', function (Request $request) {
    return $request->user();
});

// API Quản lý Người dùng
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{user}', [UserController::class, 'update']);
    Route::delete('/{user}', [UserController::class, 'destroy']);
    Route::get('/{user}', [UserController::class, 'show']);
    
});


// Role Management Routes
Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']); // Lấy tất cả vai trò với user count và permissions
    Route::get('/all', [RoleController::class, 'all']); // Lấy tất cả vai trò chỉ với id, name, description
    Route::get('/{role}', [RoleController::class, 'show']); // <-- THÊM/BỎ COMMENT DÒNG NÀY!
    Route::post('/', [RoleController::class, 'store']);
    Route::put('/{role}', [RoleController::class, 'update']);
    Route::put('/{role}/sync-permissions', [RoleController::class, 'syncPermissions']);
    Route::delete('/{role}', [RoleController::class, 'destroy']);
});

// Permissions Routes (for fetching all permissions)
Route::get('/permissions', [RoleController::class, 'allPermissions']);

// API Quản lý Quyền hạn
Route::prefix('permissions')->group(function () {
    Route::get('/', [PermissionController::class, 'index']);
});

// Nhóm route cho quản lý danh mục sản phẩm
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::put('/{category}', [CategoryController::class, 'update']);
    Route::delete('/{category}', [CategoryController::class, 'destroy']);
    Route::post('/delete-multiple', [CategoryController::class, 'deleteMultiple']);
    Route::post('/toggle-active', [CategoryController::class, 'toggleActive']);
    Route::post('/import', [CategoryController::class, 'import']);
    Route::get('/export', [CategoryController::class, 'export']);
    Route::get('/parents', [CategoryController::class, 'parents']);
});

// Nhóm route cho quản lý sản phẩm
Route::prefix('products')->group(function () {
    Route::get('/branches', [ProductController::class, 'getBranches']);
    Route::get('/categories-list', [ProductController::class, 'getCategories']);
    Route::get('/export', [ProductController::class, 'export']);
    Route::post('/import', [ProductController::class, 'import']);

    Route::get('/', [ProductController::class, 'index']);
    Route::post('/', [ProductController::class, 'store']);
    Route::get('/{product}', [ProductController::class, 'show']);
    Route::put('/{product}', [ProductController::class, 'update']);
    Route::delete('/{product}', [ProductController::class, 'destroy']);
});

// Branch Routes (chi nhánh)
Route::prefix('branches')->group(function () {
    Route::get('/', [BranchController::class, 'index']);
    Route::post('/', [BranchController::class, 'store']);
    Route::get('/{branch}', [BranchController::class, 'show']);
    Route::put('/{branch}', [BranchController::class, 'update']);
    Route::delete('/{branch}', [BranchController::class, 'destroy']);
});
// --- Các API Routes cho quản lý người dùng và chi nhánh ---

// Gán người dùng vào chi nhánh (thêm vào user_branches)
// PHƯƠNG THỨC: POST
Route::post('/users/{user}/branches/{branch}/assign', [BranchManagerController::class, 'assignManager']);

// Gỡ bỏ người dùng khỏi chi nhánh (xóa khỏi user_branches)
// PHƯƠNG THỨC: DELETE
Route::delete('/users/{user}/branches/{branch}/remove', [BranchManagerController::class, 'removeManager']);

// Đồng bộ các chi nhánh cho một người dùng (thay thế toàn bộ)
// PHƯƠNG THỨC: POST (Yêu cầu body JSON với "branch_ids")
Route::post('/users/{user}/branches/sync', [BranchManagerController::class, 'syncManagerBranches']);

// Lấy danh sách các chi nhánh mà một người dùng đang quản lý
// PHƯƠNG THỨC: GET
Route::get('/users/{user}/branches', [BranchManagerController::class, 'getUserBranches']);

// Lấy danh sách người dùng đang quản lý một chi nhánh cụ thể
// PHƯƠNG THỨC: GET
Route::get('/branches/{branch}/users', [BranchManagerController::class, 'getBranchUsers']);


// Nhóm route cho quản lý khách hàng
Route::prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::get('/{customer}', [CustomerController::class, 'show']);
    Route::put('/{customer}', [CustomerController::class, 'update']);
    Route::delete('/{customer}', [CustomerController::class, 'destroy']);
});

// Nhóm route cho quản lý đơn hàng
Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::put('/{order}', [OrderController::class, 'update']);
    Route::delete('/{order}', [OrderController::class, 'destroy']);
    // Route cho tính năng TRẢ HÀNG
    Route::post('/{order}/return', [OrderController::class, 'returnItems']);
});

// Routes đặc biệt cho ứng dụng bán hàng (POS)
// Giữ riêng route này nếu bạn cần endpoint sales-products nằm dưới /api/banhang
Route::prefix('banhang')->group(function () {
   Route::get('sales-products', [SalesProductController::class, 'index']);
});

// Các ROUTES cho Báo cáo và Thống kê
Route::prefix('reports')->group(function () {
    Route::get('/sales-summary', [ReportController::class, 'getSalesSummary']);
    Route::get('/profit-summary', [ReportController::class, 'getProfitSummary']);
    Route::get('/best-selling-products', [ReportController::class, 'getBestSellingProducts']);
    Route::get('/stock-report', [ReportController::class, 'getStockReport']);
    Route::get('/overview', [ReportController::class, 'getOverview']);
});

// Nếu có bất kỳ API routes nào KHÔNG CẦN XÁC THỰC (ví dụ: route đăng ký công khai),
// hãy đặt chúng NGOÀI khối middleware 'auth:sanctum' này.
// Ví dụ: Route::post('/register', [AuthController::class, 'register']);


}); // Kết thúc nhóm middleware 'auth:sanctum'

