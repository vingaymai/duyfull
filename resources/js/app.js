// app.js - Mount Hello.vue nếu đã login, ngược lại mount Login.vue

import './bootstrap';
import { createApp } from 'vue';
import axios from 'axios';
import Hello from './Windows/Hello.vue';
import Login from './Windows/Login.vue';

// Cấu hình Axios mặc định
axios.defaults.withCredentials = true;
axios.defaults.baseURL = 'http://duy.test/api'; // Đảm bảo URL này chính xác

function mountApp(component, props = {}) {
    // Nếu ứng dụng Vue đã tồn tại, hãy unmount nó trước khi mount lại
    const existingApp = document.getElementById('app')?.vueAppInstance;
    if (existingApp) {
      existingApp.unmount();
      console.log('Unmounted existing Vue app instance.');
    }

    const app = createApp(component, props);
    app.mount('#app');
    // Lưu instance vào DOM element để có thể unmount sau này
    document.getElementById('app').vueAppInstance = app;
    console.log(`Mounted ${component.name || 'component'}.`);
}

// Kiểm tra xem đã đăng nhập chưa trước khi render app
console.log('Starting authentication check...');
axios.get('/sanctum/csrf-cookie')
  .then(() => {
    console.log('CSRF cookie obtained. Attempting to fetch user data from /api/me...');
    return axios.get('/api/me');
  })
  .then(response => {
    // Ghi log toàn bộ phản hồi từ /api/me để kiểm tra
    console.log('Successfully received response from /api/me:', response);
    console.log('Response data:', response.data);

    const { user, permissions } = response.data;

    // Kiểm tra thông tin người dùng
    if (user && user.id) { // Đảm bảo user tồn tại và có thuộc tính id
      console.log(`Valid user found: ID = ${user.id}. Mounting Hello.vue.`);
      mountApp(Hello, {
        user,
        userPermissions: permissions,
      });
    } else {
      console.log('No valid user object or user.id found in /api/me response. Mounting Login.vue.');
      mountApp(Login);
    }
  })
  .catch(error => {
    // Ghi log lỗi chi tiết nếu request /api/me thất bại
    console.error('Error during authentication check via /api/me:', error);
    if (error.response) {
      // Lỗi từ server (status code không phải 2xx)
      console.error('Error Response Data:', error.response.data);
      console.error('Error Response Status:', error.response.status);
      console.error('Error Response Headers:', error.response.headers);
    } else if (error.request) {
      // Request đã được gửi nhưng không nhận được phản hồi (ví dụ: mất mạng)
      console.error('Error Request:', error.request);
    } else {
      // Lỗi khác khi thiết lập request
      console.error('Error Message:', error.message);
    }
    console.log('Mounting Login.vue due to authentication error.');
    mountApp(Login);
  });
