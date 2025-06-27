<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Ứng dụng Quản lý</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1920&q=80') no-repeat center center fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            background-color: #ffffff;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-width: 420px;
            width: 100%;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            line-height: 1.5;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        .submit-button {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: #2563eb;
            color: white;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .submit-button:hover {
            background-color: #1d4ed8;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">Đăng nhập</h2>
        <form action="/login" method="POST">
            @csrf
            <div class="mb-5">
                <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Email:</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input" required autofocus>
                @error('email')
                    <p class="error-message">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-5">
                <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Mật khẩu:</label>
                <input type="password" id="password" name="password" class="form-input" required>
                @error('password')
                    <p class="error-message">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-6 flex justify-between items-center">
                <div class="remember-me">
                    <input type="checkbox" name="remember" id="remember" class="h-4 w-4 text-blue-600 rounded">
                    <label for="remember">Ghi nhớ đăng nhập</label>
                </div>
                <!-- <a href="#" class="text-sm text-blue-600 hover:text-blue-800">Quên mật khẩu?</a> -->
            </div>
            <div>
                <button type="submit" class="submit-button">Đăng nhập</button>
            </div>
        </form>
    </div>
</body>
</html>
