<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Apps\Banhang\AuthController;


// Route fallback để xử lý các route SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

//Route::post('/login', [AuthController::class, 'login']);
//Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');