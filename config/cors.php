<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'user'], // Đảm bảo tất cả các API routes và Sanctum routes được liệt kê
    'allowed_methods' => ['*'], // Cho phép tất cả các phương thức HTTP
    'allowed_origins' => [
        'http://localhost:5173', // URL đầy đủ của frontend khi chạy dev server
        'http://duy.test:5173',  // Nếu bạn dùng duy.test với port cho frontend
        'http://duy.test',       // Domain của Laravel backend
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'], // Cho phép tất cả các header
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Cực kỳ quan trọng để cho phép gửi và nhận cookies
];
