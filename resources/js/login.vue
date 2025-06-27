<template>
  <div class="container mt-5">
    <h2>Đăng nhập</h2>
    <form @submit.prevent="handleLogin">
      <div class="mb-3">
        <label>Email</label>
        <input v-model="email" type="email" class="form-control" />
      </div>
      <div class="mb-3">
        <label>Mật khẩu</label>
        <input v-model="password" type="password" class="form-control" />
      </div>
      <button class="btn btn-primary">Đăng nhập</button>
    </form>
    <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      email: '',
      password: '',
      error: '',
    };
  },
  methods: {
    async handleLogin() {
      this.error = '';
      try {
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/login', {
          email: this.email,
          password: this.password,
        });
        const res = await axios.get('/api/user');
        console.log('Thông tin người dùng:', res.data);
        alert('Đăng nhập thành công!');
      } catch (err) {
        this.error = err.response?.data?.message || 'Lỗi đăng nhập!';
        console.error(err);
      }
    },
  },
};
</script>
