<template>
  <div class="login-container">
    <div class="login-card">
      <h2 class="login-title">Đăng nhập</h2>
      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="email" class="form-label">Email:</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-input"
            v-model="form.email"
            required
            autocomplete="email"
            autofocus
          >
          <p v-if="errors.email" class="error-message">{{ errors.email[0] }}</p>
        </div>
        <div class="form-group">
          <label for="password" class="form-label">Mật khẩu:</label>
          <input
            type="password"
            id="password"
            name="password"
            class="form-input"
            v-model="form.password"
            required
            autocomplete="current-password"
          >
          <p v-if="errors.password" class="error-message">{{ errors.password[0] }}</p>
        </div>
        <div class="form-group remember-me-group">
          <input type="checkbox" name="remember" id="remember" v-model="form.remember" class="remember-checkbox">
          <label for="remember" class="remember-label">Ghi nhớ đăng nhập</label>
        </div>
        <div class="form-group">
          <button type="submit" class="btn-primary" :disabled="loading">
            <span v-if="loading">Đang đăng nhập...</span>
            <span v-else>Đăng nhập</span>
          </button>
        </div>
        <p v-if="generalError" class="error-message text-center">{{ generalError }}</p>
      </form>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Login',
  data() {
    return {
      form: {
        email: '',
        password: '',
        remember: false,
      },
      errors: {},
      generalError: '',
      loading: false,
    };
  },
  methods: {
    async handleLogin() {
      this.loading = true;
      this.errors = {};
      this.generalError = '';

      try {
        // Bước 1: Lấy CSRF token trước khi gửi login
        await axios.get('/sanctum/csrf-cookie');

        // Bước 2: Gửi yêu cầu đăng nhập tới đúng route /login (đã đặt trong web.php)
        await axios.post('/login', this.form);

        // Bước 3: Reload lại app → app.js sẽ gọi /api/me và mount Hello.vue nếu đã login
        window.location.reload();
      } catch (error) {
        this.loading = false;

        if (error.response) {
          if (error.response.status === 422) {
            this.errors = error.response.data.errors;
            this.generalError = 'Vui lòng kiểm tra lại thông tin đăng nhập.';
          } else if (error.response.status === 401) {
            this.generalError = 'Thông tin đăng nhập không chính xác. Vui lòng thử lại.';
          } else {
            this.generalError = 'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại sau.';
            console.error('Lỗi API:', error.response.data);
          }
        } else {
          this.generalError = 'Không thể kết nối đến server. Vui lòng kiểm tra kết nối mạng.';
          console.error('Lỗi mạng hoặc không có phản hồi:', error);
        }
      }
    },
  },
};
</script>

<style scoped>
/* Toàn bộ container, đảm bảo căn giữa màn hình */
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  background: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1920&q=80') no-repeat center center fixed;
  background-size: cover;
  font-family: 'Inter', sans-serif; /* Đảm bảo font Inter được sử dụng */
}

/* Card đăng nhập */
.login-card {
  background-color: #ffffff;
  padding: 2.5rem;
  border-radius: 0.75rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  width: 100%;
  max-width: 400px; /* Chiều rộng tối đa cho card */
}

.login-title {
  font-size: 1.875rem; /* text-3xl */
  font-weight: 700; /* font-bold */
  color: #1a202c; /* text-gray-800 */
  margin-bottom: 2rem; /* mb-8 */
  text-align: center;
}

.form-group {
  margin-bottom: 1.25rem; /* mb-5 */
}

.form-label {
  display: block;
  color: #4a5568; /* text-gray-700 */
  font-size: 0.875rem; /* text-sm */
  font-weight: 600; /* font-semibold */
  margin-bottom: 0.5rem; /* mb-2 */
}

.form-input {
  display: block;
  width: 100%;
  padding: 0.75rem 1rem; /* p-3 */
  border: 1px solid #e2e8f0; /* border-gray-300 */
  border-radius: 0.375rem; /* rounded-md */
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-input:focus {
  outline: none;
  border-color: #3b82f6; /* focus:border-blue-500 */
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); /* focus:ring-blue-500 */
}

.remember-me-group {
  display: flex;
  align-items: center;
  margin-bottom: 1.5rem; /* mb-6 */
  justify-content: space-between; /* flex justify-between */
}

.remember-checkbox {
  height: 1rem; /* h-4 */
  width: 1rem; /* w-4 */
  color: #2563eb; /* text-blue-600 */
  border-radius: 0.25rem; /* rounded */
  margin-right: 0.5rem;
}

.remember-label {
  color: #4a5568; /* text-gray-700 */
  font-size: 0.875rem; /* text-sm */
}

.btn-primary {
  width: 100%;
  background-color: #2563eb; /* bg-blue-600 */
  color: white;
  padding: 0.75rem 1rem; /* py-2 px-4 */
  border-radius: 0.375rem; /* rounded-md */
  font-weight: 600; /* font-semibold */
  transition: background-color 0.2s ease-in-out;
  cursor: pointer;
  border: none;
}

.btn-primary:hover {
  background-color: #1d4ed8; /* hover:bg-blue-700 */
}

.btn-primary:disabled {
  background-color: #93c5fd; /* bg-blue-300 */
  cursor: not-allowed;
}

.error-message {
  color: #ef4444; /* text-red-500 */
  font-size: 0.875rem; /* text-sm */
  margin-top: 0.5rem; /* mt-2 */
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .login-card {
    margin: 1rem;
    padding: 2rem;
  }
  .login-title {
    font-size: 1.5rem;
  }
}
</style>

