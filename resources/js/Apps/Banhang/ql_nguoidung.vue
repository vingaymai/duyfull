<template>
  <div class="user-management">
    <!-- Main card for user listing -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Quản lý Người dùng</h5>
        <div>
          <button @click="openAddUserModal" class="btn btn-primary me-2">
            <i class="fas fa-plus"></i> Thêm người dùng
          </button>
          <button @click="openRoleManagementModal" class="btn btn-secondary">
            <i class="fas fa-user-tag"></i> Quản lý Vai trò
          </button>
        </div>
      </div>

      <div class="card-body">
        <!-- Search bar -->
        <div class="row mb-3 align-items-center">
          <div class="col-md-6">
            <div class="input-group">
              <input
                type="text"
                class="form-control"
                placeholder="Tìm kiếm người dùng theo tên, email..."
                v-model="searchQuery"
                @input="debounceSearch"
              >
              <button class="btn btn-outline-secondary" type="button" @click="fetchUsers(1)">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- User list table -->
        <div class="table-responsive table-scroll-container">
          <table class="table table-hover table-striped mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Tên</th>
                <th>Email</th>
                <th>Vai trò</th>
                <th>Chi nhánh</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="users.length === 0">
                <td colspan="7" class="text-center">Không có dữ liệu người dùng.</td>
              </tr>
              <tr v-for="user in users" :key="user.id">
                <td>{{ user.id }}</td>
                <td>{{ user.name }}</td>
                <td>{{ user.email }}</td>
                <td>
                  <!-- Display user's roles fetched from backend -->
                  <span v-for="role in user.roles" :key="role.id" class="badge bg-info me-1">
                    {{ role.name }}
                  </span>
                </td>
                <td>
                  <!-- Display user's branches fetched from backend -->
                  <span v-for="branch in user.branches" :key="branch.id" class="badge bg-success me-1">
                    {{ branch.name }}
                  </span>
                </td>
                <td>
                  <span :class="['badge', user.active ? 'bg-success' : 'bg-danger']">
                    {{ user.active ? 'Hoạt động' : 'Khóa' }}
                  </span>
                </td>
                <td>
                  <button @click="openEditUserModal(user)" class="btn btn-sm btn-warning me-2">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button @click="confirmDeleteUser(user)" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination controls -->
        <div class="d-flex justify-content-center mt-3" v-if="pagination.last_page > 1">
          <ul class="pagination">
            <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
              <a class="page-link" href="#" @click.prevent="fetchUsers(1)">Đầu</a>
            </li>
            <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
              <a class="page-link" href="#" @click.prevent="fetchUsers(pagination.current_page - 1)">Trước</a>
            </li>
            <li class="page-item" v-for="page in pagination.last_page" :key="page"
                :class="{ active: page === pagination.current_page }">
              <a class="page-link" href="#" @click.prevent="fetchUsers(page)">{{ page }}</a>
            </li>
            <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
              <a class="page-link" href="#" @click.prevent="fetchUsers(pagination.current_page + 1)">Sau</a>
            </li>
            <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
              <a class="page-link" href="#" @click.prevent="fetchUsers(pagination.last_page)">Cuối</a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true" ref="userModal">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="userModalLabel">{{ isEditMode ? 'Chỉnh sửa Người dùng' : 'Thêm Người dùng' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form @submit.prevent="saveUser">
            <div class="modal-body scrollable-modal-body">
              <div class="mb-3">
                <label for="name" class="form-label">Tên</label>
                <input type="text" class="form-control" id="name" v-model="userForm.name" required>
              </div>
              <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" v-model="userForm.email" required>
              </div>
              <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" class="form-control" id="password" v-model="userForm.password" :required="!isEditMode">
                <small class="form-text text-muted" v-if="isEditMode">Để trống nếu không muốn thay đổi mật khẩu.</small>
              </div>
              <div class="mb-3">
                <label for="password_confirmation" class="form-label">Xác nhận mật khẩu</label>
                <input type="password" class="form-control" id="password_confirmation" v-model="userForm.password_confirmation" :required="!isEditMode">
              </div>
              <div class="mb-3">
                <label for="roles" class="form-label">Vai trò</label>
                <!-- Multiselect for roles: userForm.role_ids will hold an array of selected role IDs -->
                <select class="form-select" id="roles" v-model="userForm.role_ids" multiple>
                  <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                </select>
                <small class="form-text text-muted">Giữ Ctrl/Cmd để chọn nhiều vai trò.</small>
              </div>
              <div class="mb-3">
                <label for="branches" class="form-label">Chi nhánh</label>
                <!-- Multiselect for branches/stores: userForm.store_ids will hold an array of selected branch IDs -->
                <select class="form-select" id="branches" v-model="userForm.store_ids" multiple>
                  <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </select>
                <small class="form-text text-muted">Giữ Ctrl/Cmd để chọn nhiều chi nhánh.</small>
              </div>
              <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="active" v-model="userForm.active">
                <label class="form-check-label" for="active">Hoạt động</label>
              </div>
              <!-- Display form validation errors -->
              <div v-if="formErrors" class="alert alert-danger">
                <ul>
                  <li v-for="(error, field) in formErrors" :key="field">
                    {{ Array.isArray(error) ? error.join(', ') : error }}
                  </li>
                </ul>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
              <button type="submit" class="btn btn-primary">
                <span v-if="loading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                {{ loading ? 'Đang lưu...' : 'Lưu' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true" ref="deleteConfirmationModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteConfirmationModalLabel">Xác nhận xóa</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Bạn có chắc chắn muốn xóa người dùng <strong>{{ userToDelete ? userToDelete.name : '' }}</strong> không?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="button" class="btn btn-danger" @click="deleteUser">
              <span v-if="loading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              {{ loading ? 'Đang xóa...' : 'Xóa' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
      <div id="liveToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body" id="toast-body">
            <!-- Message will be injected here -->
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>

    <!-- Role Management Modal: To manage permissions for each role -->
    <div class="modal fade" id="roleManagementModal" tabindex="-1" aria-labelledby="roleManagementModalLabel" aria-hidden="true" ref="roleManagementModal">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="roleManagementModalLabel">Quản lý Vai trò và Quyền hạn Ứng dụng</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body scrollable-modal-body">
            <div class="mb-3">
              <label for="selectRole" class="form-label">Chọn Vai trò để chỉnh sửa quyền</label>
              <select class="form-select" id="selectRole" v-model="selectedRoleIdForEdit" @change="loadRolePermissions">
                <option value="">-- Chọn vai trò --</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
              </select>
            </div>

            <div v-if="selectedRoleIdForEdit">
              <h6>Quyền truy cập ứng dụng cho vai trò: <strong>{{ selectedRoleName }}</strong></h6>
              <!-- Checkboxes for application access permissions. Permissions are filtered to only show 'access_app_' types. -->
              <div class="form-check" v-for="permission in allAppPermissions" :key="permission.id">
                <input class="form-check-input" type="checkbox" :value="permission.name" :id="'perm-' + permission.id"
                       v-model="currentRolePermissions">
                <label class="form-check-label" :for="'perm-' + permission.id">
                  {{ getPermissionDisplayName(permission.name) }}
                </label>
              </div>
              <!-- Display role form validation errors -->
              <div v-if="roleFormErrors" class="alert alert-danger mt-3">
                <ul>
                  <li v-for="(error, field) in roleFormErrors" :key="field">
                    {{ Array.isArray(error) ? error.join(', ') : error }}
                  </li>
                </ul>
              </div>
            </div>
            <div v-else class="alert alert-info">Vui lòng chọn một vai trò để quản lý quyền.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            <button type="button" class="btn btn-primary" @click="saveRolePermissions" :disabled="!selectedRoleIdForEdit || loading">
              <span v-if="loading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              {{ loading ? 'Đang lưu...' : 'Lưu Quyền' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { Modal, Toast } from 'bootstrap'; // Ensure Bootstrap JS is imported and available

export default {
  data() {
    return {
      // Main data state for the component
      users: [], // Array to hold user data fetched from the API
      roles: [], // Array to hold all available roles (for dropdowns)
      branches: [], // Array to hold all available branches/stores (for dropdowns)
      allAppPermissions: [], // Array to hold all 'access_app_' specific permissions for role management

      // User form data for add/edit modal
      userForm: {
        id: null,
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        active: true, // User active status
        role_ids: [],   // Array of IDs of roles assigned to the user
        store_ids: []   // Array of IDs of branches/stores assigned to the user
      },
      isEditMode: false, // Flag to differentiate between add and edit mode
      userToDelete: null, // Holds user object to be deleted
      loading: false, // General loading indicator for API calls
      formErrors: null, // Holds validation errors from backend for user form

      // Role management modal data
      selectedRoleIdForEdit: null, // ID of the role currently selected for editing permissions
      selectedRoleName: '', // Name of the selected role, for display
      currentRolePermissions: [], // Array of permission names currently assigned to the selected role
      roleFormErrors: null, // Holds validation errors from backend for role form

      // Pagination and Search data
      searchQuery: '', // Search input model
      pagination: { // Pagination metadata
        current_page: 1,
        last_page: 1,
        from: 1,
        to: 1,
        total: 0
      },
      debounceTimeout: null, // Timeout for search debounce

      // Bootstrap Modals and Toasts instances
      userModal: null,
      deleteConfirmationModal: null,
      roleManagementModal: null,
      liveToast: null
    };
  },
  mounted() {
    // Initialize Bootstrap modals and toasts after the component is mounted
    this.userModal = new Modal(this.$refs.userModal);
    this.deleteConfirmationModal = new Modal(this.$refs.deleteConfirmationModal);
    this.roleManagementModal = new Modal(this.$refs.roleManagementModal);
    this.liveToast = new Toast(document.getElementById('liveToast'));

    // Attach event listeners to modals to remove extra backdrops (Bootstrap bug workaround)
    this.$refs.userModal.addEventListener('hidden.bs.modal', this.removeExtraBackdrops);
    this.$refs.deleteConfirmationModal.addEventListener('hidden.bs.modal', this.removeExtraBackdrops);
    this.$refs.roleManagementModal.addEventListener('hidden.bs.modal', this.removeExtraBackdrops);

    // Initial data fetching when the component loads
    this.fetchUsers();
    this.fetchRoles(); // Load all roles for assignment dropdowns
    this.fetchBranches(); // Load all branches/stores for assignment dropdowns
    this.fetchAppPermissions(); // Load all app permissions for role management
  },
  methods: {
    // --- API Fetching Methods ---
    async fetchUsers(page = 1) {
      this.loading = true;
      this.formErrors = null; // Clear previous form errors
      try {
        // Fetch users from the backend. The backend should eager load 'roles' and 'branches' for display.
        const response = await axios.get(`/api/users?page=${page}&search=${this.searchQuery}`);
        this.users = response.data.data;
        this.pagination = { // Update pagination data
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          from: response.data.from,
          to: response.data.to,
          total: response.data.total
        };
      } catch (error) {
        console.error("Lỗi khi tải danh sách người dùng:", error);
        this.showToast('danger', 'Lỗi khi tải danh sách người dùng.');
      } finally {
        this.loading = false;
      }
    },

    async fetchRoles() {
      try {
        // Fetch all available roles from a backend API endpoint.
        const response = await axios.get('/api/roles');
        this.roles = response.data; // Assuming response.data is an array of role objects
      } catch (error) {
        console.error("Lỗi khi tải danh sách vai trò:", error);
        this.showToast('danger', 'Lỗi khi tải danh sách vai trò.');
      }
    },

    async fetchBranches() {
      try {
        // Fetch all available branches/stores from a backend API endpoint.
        const response = await axios.get('/api/branches');
        this.branches = response.data; // Assuming response.data is an array of branch objects
      } catch (error) {
        console.error("Lỗi khi tải danh sách chi nhánh:", error);
        this.showToast('danger', 'Lỗi khi tải danh sách chi nhánh.');
      }
    },

    async fetchAppPermissions() {
      try {
        // Fetch all permissions from the backend and filter for specific 'access_app_' permissions.
        // This assumes your backend API for permissions provides a 'name' field for filtering.
        const response = await axios.get('/api/permissions');
        this.allAppPermissions = response.data.filter(p => p.name.startsWith('access_app_'));
      } catch (error) {
        console.error("Lỗi khi tải danh sách quyền ứng dụng:", error);
        this.showToast('danger', 'Lỗi khi tải danh sách quyền ứng dụng.');
      }
    },

    // --- User Modal Methods ---
    resetUserForm() {
      // Resets the user form to its initial empty state
      this.userForm = {
        id: null,
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        active: true,
        role_ids: [],
        store_ids: []
      };
      this.formErrors = null; // Clear any validation errors
    },

    openAddUserModal() {
      this.isEditMode = false; // Set mode to add new user
      this.resetUserForm(); // Clear form fields
      this.userModal.show(); // Show the modal
    },

    async openEditUserModal(user) {
      this.isEditMode = true; // Set mode to edit user
      this.resetUserForm(); // Clear form fields first
      this.loading = true; // Show loading indicator
      try {
        // Fetch detailed user information. This API call should eager load 'roles' and 'branches'
        // so that the multiselects can be pre-populated correctly.
        const response = await axios.get(`/api/users/${user.id}`);
        const fetchedUser = response.data;

        // Populate userForm with fetched data
        this.userForm.id = fetchedUser.id;
        this.userForm.name = fetchedUser.name;
        this.userForm.email = fetchedUser.email;
        this.userForm.active = fetchedUser.active;
        // Map the fetched roles/branches to an array of their IDs for the v-model of select multiple
        this.userForm.role_ids = fetchedUser.roles.map(role => role.id);
        this.userForm.store_ids = fetchedUser.branches.map(branch => branch.id);

        this.userModal.show(); // Show the modal
      } catch (error) {
        console.error("Lỗi khi tải thông tin người dùng để chỉnh sửa:", error);
        this.showToast('danger', 'Lỗi khi tải thông tin người dùng. Vui lòng kiểm tra kết nối mạng và nhật ký lỗi backend.');
      } finally {
        this.loading = false; // Hide loading indicator
      }
    },

    async saveUser() {
      this.loading = true;
      this.formErrors = null; // Clear previous errors
      try {
        let response;
        if (this.isEditMode) {
          // Send PUT request for updating user, including selected role_ids and store_ids
          response = await axios.put(`/api/users/${this.userForm.id}`, this.userForm);
        } else {
          // Send POST request for creating new user, including selected role_ids and store_ids
          response = await axios.post('/api/users', this.userForm);
        }
        this.showToast('success', response.data.message); // Show success toast
        this.userModal.hide(); // Hide the modal
        this.fetchUsers(this.pagination.current_page); // Refresh user list to reflect changes
      } catch (error) {
        console.error("Lỗi khi lưu người dùng:", error);
        if (error.response && error.response.status === 422) {
          // If validation errors, display them
          this.formErrors = error.response.data.errors;
          this.showToast('danger', 'Đã xảy ra lỗi xác thực.');
        } else {
          // Generic error message
          this.showToast('danger', 'Đã xảy ra lỗi khi lưu người dùng.');
        }
      } finally {
        this.loading = false;
      }
    },

    // --- Delete User Methods ---
    confirmDeleteUser(user) {
      this.userToDelete = user; // Store user to be deleted
      this.deleteConfirmationModal.show(); // Show confirmation modal
    },

    async deleteUser() {
      this.loading = true;
      try {
        await axios.delete(`/api/users/${this.userToDelete.id}`);
        this.showToast('success', 'Người dùng đã được xóa thành công!');
        this.deleteConfirmationModal.hide(); // Hide confirmation modal
        this.fetchUsers(this.pagination.current_page); // Refresh user list
      } catch (error) {
        console.error("Lỗi khi xóa người dùng:", error);
        this.showToast('danger', 'Lỗi khi xóa người dùng.');
      } finally {
        this.loading = false;
      }
    },

    // --- Role Management Modal Methods ---
    openRoleManagementModal() {
      // Reset state for role management modal
      this.selectedRoleIdForEdit = null;
      this.currentRolePermissions = [];
      this.selectedRoleName = '';
      this.roleFormErrors = null;
      this.roleManagementModal.show();
      // allAppPermissions are already fetched in mounted()
    },

    async loadRolePermissions() {
      if (!this.selectedRoleIdForEdit) {
        this.currentRolePermissions = [];
        this.selectedRoleName = '';
        return;
      }
      this.loading = true;
      this.roleFormErrors = null;
      try {
        // Find the selected role's name from the already loaded roles list
        const selectedRole = this.roles.find(r => r.id === this.selectedRoleIdForEdit);
        this.selectedRoleName = selectedRole ? selectedRole.name : '';

        // Fetch the detailed role information, which should include its assigned permissions.
        // This assumes /api/roles/{id} endpoint returns the role with its permissions eager loaded.
        const response = await axios.get(`/api/roles/${this.selectedRoleIdForEdit}`);
        const roleWithPermissions = response.data;
        // Map the fetched permissions to an array of their names for the v-model of checkboxes
        this.currentRolePermissions = roleWithPermissions.permissions.map(p => p.name);

      } catch (error) {
        console.error("Lỗi khi tải quyền của vai trò:", error);
        this.showToast('danger', 'Lỗi khi tải quyền của vai trò. Vui lòng kiểm tra API backend cho vai trò.');
        this.currentRolePermissions = [];
        this.selectedRoleName = '';
      } finally {
        this.loading = false;
      }
    },

    async saveRolePermissions() {
      if (!this.selectedRoleIdForEdit) {
        this.showToast('danger', 'Vui lòng chọn một vai trò để lưu quyền.');
        return;
      }
      this.loading = true;
      this.roleFormErrors = null;
      try {
        // Send the array of permission names to the backend to sync with the selected role.
        // This assumes an endpoint like /api/roles/{id}/sync-permissions that handles Spatie's syncPermissions.
        const payload = { permissions: this.currentRolePermissions };
        const response = await axios.put(`/api/roles/${this.selectedRoleIdForEdit}/sync-permissions`, payload);
        this.showToast('success', response.data.message);
        // You might want to re-fetch roles or permissions if changes affect other parts of the UI.
      } catch (error) {
        console.error("Lỗi khi lưu quyền của vai trò:", error);
        if (error.response && error.response.status === 422) {
          this.roleFormErrors = error.response.data.errors;
          this.showToast('danger', 'Đã xảy ra lỗi xác thực khi lưu quyền vai trò.');
        } else {
          this.showToast('danger', 'Đã xảy ra lỗi khi lưu quyền của vai trò.');
        }
      } finally {
        this.loading = false;
      }
    },

    getPermissionDisplayName(permissionName) {
      // Helper function to provide more readable names for specific permissions, especially 'access_app_'
      if (permissionName.startsWith('access_app_')) {
        const appIdentifier = permissionName.replace('access_app_', '');
        switch (appIdentifier) {
          case 'sales': return 'Truy cập ứng dụng Bán hàng';
          case 'ql_sanpham': return 'Truy cập ứng dụng Quản lý Sản phẩm';
          case 'ql_danhmuc_sanpham': return 'Truy cập ứng dụng Quản lý Danh mục Sản phẩm';
          case 'ql_chinhanh': return 'Truy cập ứng dụng Quản lý Chi nhánh';
          case 'ql_khachhang': return 'Truy cập ứng dụng Quản lý Khách hàng';
          case 'ql_nguoidung': return 'Truy cập ứng dụng Quản lý Người dùng';
          case 'ql_donhang': return 'Truy cập ứng dụng Quản lý Đơn hàng';
          case 'baocaothongke': return 'Truy cập ứng dụng Báo cáo/Thống kê';
          default: return `Truy cập ứng dụng: ${appIdentifier}`;
        }
      }
      return permissionName; // Return the original name if no specific mapping exists
    },

    // --- Utility Methods ---
    debounceSearch() {
      // Debounces the search input to prevent excessive API calls
      clearTimeout(this.debounceTimeout);
      this.debounceTimeout = setTimeout(() => {
        this.fetchUsers(1); // Trigger search after a delay
      }, 500);
    },

    showToast(type, message) {
      // Displays a Bootstrap toast notification
      const toastElement = document.getElementById('liveToast');
      const toastBody = document.getElementById('toast-body');

      if (toastElement && toastBody) {
        // Reset and set background color based on toast type (success, danger, etc.)
        toastElement.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info');
        toastElement.classList.add(`bg-${type}`);
        toastBody.textContent = message;
        this.liveToast.show();
      } else {
        console.warn("Không tìm thấy các phần tử Bootstrap Toast trong DOM.");
      }
    },

    removeExtraBackdrops() {
      // Workaround for a Bootstrap bug that leaves multiple modal backdrops
      const backdrops = document.querySelectorAll('.modal-backdrop');
      if (backdrops.length > 1) {
        for (let i = 0; i < backdrops.length - 1; i++) {
          backdrops[i].remove();
          console.warn('Đã xóa modal-backdrop thừa.');
        }
      }
    }
  }
}
</script>

<style scoped>
.user-management {
  padding: 20px;
}

.table-responsive {
  max-height: calc(100vh - 300px);
  overflow-y: auto;
  margin-bottom: 0;
}

.table-responsive table thead {
  position: sticky;
  top: 0;
  background-color: #f8f9fa;
  z-index: 10;
}

.table th, .table td {
  vertical-align: middle;
}

.modal-header {
  padding: 12px 20px;
}

/* Styles for scrollable modal body */
.scrollable-modal-body {
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}

.badge {
  font-size: 0.85em;
  padding: 0.4em 0.6em;
  white-space: nowrap; /* Prevent text inside badges from wrapping */
}
</style>
