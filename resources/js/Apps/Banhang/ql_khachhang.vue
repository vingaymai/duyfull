<template>
  <div class="customer-management">
    <!-- Main card for customer listing -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Quản lý Khách hàng</h5>
        <div>
          <button @click="openAddModal" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm mới
          </button>
        </div>
      </div>

      <div class="card-body">
        <!-- Message for no permission -->
        <div v-if="!hasPermission" class="alert alert-warning text-center" role="alert">
          Bạn không có quyền truy cập chức năng này. Vui lòng liên hệ quản trị viên.
        </div>

        <!-- Search Bar -->
        <div class="row mb-3" v-if="hasPermission">
          <div class="col-md-6">
            <div class="input-group">
              <input
                type="text"
                class="form-control"
                placeholder="Tìm kiếm khách hàng theo tên, email, SĐT, địa chỉ..."
                v-model="searchQuery"
                @input="debounceSearch"
              >
              <button class="btn btn-outline-secondary" type="button" @click="fetchCustomers">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Customer list table -->
        <div class="table-responsive table-scroll-area" v-if="hasPermission">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th width="60">STT</th>
                <th>Tên khách hàng</th>
                <th>Email</th>
                <th>Điện thoại</th>
                <th>Địa chỉ</th>
                <th>Thành phố</th>
                <th>Quốc gia</th>
                <th>Ngày sinh</th>
                <th>Giới tính</th>
                <th>Tổng chi tiêu</th>
                <th>Tổng lượt ghé thăm</th>
                <th>Trạng thái</th>
                <th width="150">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(customer, index) in paginatedCustomers" :key="customer.id">
                <td>{{ (currentPage - 1) * perPage + index + 1 }}</td>
                <td>{{ customer.name }}</td>
                <td>{{ customer.email || '-' }}</td>
                <td>{{ customer.phone || '-' }}</td>
                <td>{{ customer.address || '-' }}</td>
                <td>{{ customer.city || '-' }}</td>
                <td>{{ customer.country || '-' }}</td>
                <td>{{ customer.date_of_birth ? formatDate(customer.date_of_birth) : '-' }}</td>
                <td>{{ getGenderDisplay(customer.gender) }}</td>
                <td>{{ formatCurrency(customer.total_spent) }}</td>
                <td>{{ customer.total_visits }}</td>
                <td>
                  <span
                    class="badge"
                    :class="customer.active ? 'bg-success' : 'bg-secondary'"
                  >
                    {{ customer.active ? 'Kích hoạt' : 'Vô hiệu' }}
                  </span>
                </td>
                <td>
                  <button
                    @click="editCustomer(customer)"
                    class="btn btn-sm btn-primary me-1"
                    title="Sửa"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    @click="confirmDelete(customer)"
                    class="btn btn-sm btn-danger"
                    title="Xóa"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="paginatedCustomers.length === 0">
                <td colspan="13" class="text-center">Không có dữ liệu</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div class="d-flex justify-content-between align-items-center mt-3" v-if="hasPermission">
            <div class="input-group" style="width: 120px;">
                <label class="input-group-text" for="perPageSelect">Hiển thị/trang</label>
                <select class="form-select" id="perPageSelect" v-model.number="perPage" @change="changePage(1)">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div>
                Hiển thị {{ (currentPage - 1) * perPage + 1 }} đến {{ Math.min(currentPage * perPage, filteredCustomers.length) }} của {{ filteredCustomers.length }} khách hàng
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item" :class="{ disabled: currentPage === 1 }">
                        <a class="page-link" href="#" @click.prevent="changePage(1)">Đầu tiên</a>
                    </li>
                    <li class="page-item" :class="{ disabled: currentPage === 1 }">
                        <a class="page-link" href="#" @click.prevent="changePage(currentPage - 1)">Trước</a>
                    </li>
                    <li class="page-item" v-for="page in paginationPages" :key="page" :class="{ active: currentPage === page }">
                        <a class="page-link" href="#" @click.prevent="changePage(page)">{{ page }}</a>
                    </li>
                    <li class="page-item" :class="{ disabled: currentPage === lastPage }">
                        <a class="page-link" href="#" @click.prevent="changePage(currentPage + 1)">Sau</a>
                    </li>
                    <li class="page-item" :class="{ disabled: currentPage === lastPage }">
                        <a class="page-link" href="#" @click.prevent="changePage(lastPage)">Cuối cùng</a>
                    </li>
                </ul>
            </nav>
            <div class="d-flex align-items-center">
                <div class="input-group me-2" style="width: 150px;">
                    <span class="input-group-text">Trang</span>
                    <input type="number" class="form-control" v-model.number="pageInput" @keyup.enter="goToPage" min="1" :max="lastPage">
                </div>
            </div>
        </div>
      </div>
    </div>

    <!-- Modal thêm/sửa khách hàng -->
    <div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">{{ isEditing ? 'Cập nhật' : 'Thêm mới' }} Khách hàng</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="submitForm" class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Tên khách hàng <span class="text-danger">*</span></label>
                <input type="text" class="form-control" v-model="form.name" required>
                <div v-if="formErrors.name" class="text-danger small mt-1">{{ formErrors.name[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" v-model="form.email">
                <div v-if="formErrors.email" class="text-danger small mt-1">{{ formErrors.email[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Điện thoại</label>
                <input type="tel" class="form-control" v-model="form.phone">
                <div v-if="formErrors.phone" class="text-danger small mt-1">{{ formErrors.phone[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Địa chỉ</label>
                <input type="text" class="form-control" v-model="form.address">
                <div v-if="formErrors.address" class="text-danger small mt-1">{{ formErrors.address[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Thành phố</label>
                <input type="text" class="form-control" v-model="form.city">
                <div v-if="formErrors.city" class="text-danger small mt-1">{{ formErrors.city[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Quốc gia</label>
                <input type="text" class="form-control" v-model="form.country">
                <div v-if="formErrors.country" class="text-danger small mt-1">{{ formErrors.country[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Ngày sinh</label>
                <input type="date" class="form-control" v-model="form.date_of_birth">
                <div v-if="formErrors.date_of_birth" class="text-danger small mt-1">{{ formErrors.date_of_birth[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Giới tính</label>
                <select class="form-select" v-model="form.gender">
                  <option value="">-- Chọn giới tính --</option>
                  <option value="male">Nam</option>
                  <option value="female">Nữ</option>
                  <option value="other">Khác</option>
                </select>
                <div v-if="formErrors.gender" class="text-danger small mt-1">{{ formErrors.gender[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Tổng chi tiêu</label>
                <input type="number" class="form-control" v-model.number="form.total_spent" min="0" step="0.01">
                <div v-if="formErrors.total_spent" class="text-danger small mt-1">{{ formErrors.total_spent[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Tổng lượt ghé thăm</label>
                <input type="number" class="form-control" v-model.number="form.total_visits" min="0">
                <div v-if="formErrors.total_visits" class="text-danger small mt-1">{{ formErrors.total_visits[0] }}</div>
              </div>
              <div class="col-12 form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="activeCustomerCheck" v-model="form.active">
                <label class="form-check-label" for="activeCustomerCheck">Kích hoạt khách hàng</label>
              </div>

              <!-- Form Actions -->
              <div class="col-12 text-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary" :disabled="loading">
                  <span v-if="loading" class="spinner-border spinner-border-sm"></span>
                  {{ isEditing ? 'Cập nhật' : 'Thêm mới' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal xác nhận xóa -->
    <div class="modal fade" id="deleteCustomerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">Xác nhận xóa khách hàng</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Bạn có chắc chắn muốn xóa khách hàng <strong>{{ customerToDelete?.name }}</strong>?</p>
            <p class="text-danger">Lưu ý: Thao tác này không thể hoàn tác!</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button
              type="button"
              class="btn btn-danger"
              @click="deleteCustomer"
              :disabled="deleteLoading"
            >
              <span v-if="deleteLoading" class="spinner-border spinner-border-sm"></span>
              Xóa
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import _ from 'lodash' // For debouncing search input

export default {
  name: 'CustomerManagement',
  // Thêm props để nhận user và userPermissions
  props: {
    user: {
      type: Object,
      default: () => ({})
    },
    userPermissions: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      allCustomers: [], // Stores all raw customer data from the API
      searchQuery: '',

      // Form
      isEditing: false,
      form: {
        id: null,
        name: '',
        email: '',
        phone: '',
        address: '',
        city: '',
        country: '',
        date_of_birth: null,
        gender: '',
        total_spent: 0,
        total_visits: 0,
        active: true, // Default to active for new customers
      },
      formErrors: {}, // For displaying validation errors from Laravel

      // Delete
      customerToDelete: null,

      // Loading states
      loading: false,        // For form submission
      deleteLoading: false,  // For delete action

      // Pagination data
      currentPage: 1,
      perPage: 10, // Default items per page
      pageInput: 1, // Used for the page number input field

      // Modal instances
      customerModal: null,
      deleteCustomerModal: null,
    }
  },
  computed: {
    // Check if the user has permission to access this app
    hasPermission() {
      const isAdmin = this.user && this.user.roles && this.user.roles.some(role => role.name === 'admin');
      // If user is admin, they have full access
      if (isAdmin) {
        return true;
      }
      // Otherwise, check for specific permission for ql_khachhang
      return this.userPermissions.includes('access_app_ql_khachhang');
    },
    // Filter customers based on search query
    filteredCustomers() {
      if (!this.searchQuery) {
        return this.allCustomers;
      }
      const lowerCaseQuery = this.searchQuery.toLowerCase();
      return this.allCustomers.filter(customer =>
        customer.name.toLowerCase().includes(lowerCaseQuery) ||
        (customer.email && customer.email.toLowerCase().includes(lowerCaseQuery)) ||
        (customer.phone && customer.phone.toLowerCase().includes(lowerCaseQuery)) ||
        (customer.address && customer.address.toLowerCase().includes(lowerCaseQuery))
      );
    },
    // Paginate the filtered customers
    paginatedCustomers() {
      const startIndex = (this.currentPage - 1) * this.perPage;
      const endIndex = startIndex + this.perPage;
      return this.filteredCustomers.slice(startIndex, endIndex);
    },
    // Calculate the last page number
    lastPage() {
      return Math.ceil(this.filteredCustomers.length / this.perPage);
    },
    // Generates an array of page numbers to display in the pagination controls
    paginationPages() {
      const pages = [];
      const startPage = Math.max(1, this.currentPage - 2);
      const endPage = Math.min(this.lastPage, this.currentPage + 2);

      for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
      }
      return pages;
    }
  },
  mounted() {
    this.$nextTick(() => {
      // Initialize Bootstrap modals
      if (typeof bootstrap !== 'undefined') {
        try {
          this.customerModal = new bootstrap.Modal(document.getElementById('customerModal'));
          this.deleteCustomerModal = new bootstrap.Modal(document.getElementById('deleteCustomerModal'));

          // Attach event listeners for modals to handle extra backdrops
          this.customerModal._element.addEventListener('shown.bs.modal', () => {
              this.removeExtraBackdrops();
          });
          this.customerModal._element.addEventListener('hidden.bs.modal', () => {
              this.resetForm(); // Reset form when modal is hidden
          });
          this.deleteCustomerModal._element.addEventListener('shown.bs.modal', () => {
              this.removeExtraBackdrops();
          });
          console.log("Bootstrap modals initialized successfully.");
        } catch (e) {
            console.error("Error initializing Bootstrap modals:", e);
            console.warn("Bootstrap JS object might be corrupted or an element ID is missing.");
        }
      } else {
        console.warn("Bootstrap JS object is not available. Modals may not function correctly. Ensure Bootstrap's JS is loaded before your Vue app mounts.");
      }

      // Chỉ fetch dữ liệu nếu người dùng có quyền
      if (this.hasPermission) {
        this.fetchCustomers(); // Initial fetch of all customers
      }
    });
  },
  methods: {
    // Fetches all customers from the backend
    async fetchCustomers() {
      // Bỏ qua nếu không có quyền
      if (!this.hasPermission) {
        console.warn('Không có quyền truy cập để fetch khách hàng.');
        return;
      }
      try {
        const response = await axios.get('/api/customers', {
          params: {
            search: this.searchQuery // Send search query to backend
          }
        });
        this.allCustomers = response.data;
        // After fetching/filtering, ensure pagination is reset or adjusted
        this.changePage(1); // Reset to first page
      } catch (error) {
        console.error('Lỗi khi tải danh sách khách hàng:', error);
        this.showToast('Lỗi khi tải danh sách khách hàng', 'danger');
      }
    },

    // Debounced search to avoid too many API calls
    debounceSearch: _.debounce(function() {
      this.fetchCustomers();
    }, 500),

    // Change page for pagination
    changePage(page) {
      if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
        this.currentPage = page;
        this.pageInput = page;
      } else if (this.lastPage === 0) { // Handle case where there are no results
        this.currentPage = 1;
        this.pageInput = 1;
      }
    },
    // Go to specific page from input
    goToPage() {
      if (this.pageInput >= 1 && this.pageInput <= this.lastPage && this.pageInput !== this.currentPage) {
        this.changePage(this.pageInput);
      } else {
        this.pageInput = this.currentPage; // Revert to current page if invalid input
      }
    },

    // Opens the add customer modal
    openAddModal() {
      this.isEditing = false;
      this.resetForm();
      this.form.active = true; // Default active for new customers
      this.customerModal?.show();
    },

    // Opens the edit customer modal and populates form
    editCustomer(customer) {
      this.isEditing = true;
      this.resetForm(); // Reset before populating

      // Format date_of_birth for input type="date"
      const dob = customer.date_of_birth ? new Date(customer.date_of_birth).toISOString().split('T')[0] : null;

      this.form = {
        id: customer.id,
        name: customer.name,
        email: customer.email || '',
        phone: customer.phone || '',
        address: customer.address || '',
        city: customer.city || '',
        country: customer.country || '',
        date_of_birth: dob,
        gender: customer.gender || '',
        total_spent: customer.total_spent,
        total_visits: customer.total_visits,
        active: customer.active,
      };
      this.customerModal?.show();
    },

    // Submits the customer form (create/update)
    async submitForm() {
      this.loading = true;
      this.formErrors = {}; // Clear previous errors

      try {
        const url = this.isEditing
          ? `/api/customers/${this.form.id}`
          : '/api/customers';
        const method = this.isEditing ? 'put' : 'post';

        await axios[method](url, this.form);

        this.showToast(`Khách hàng đã ${this.isEditing ? 'cập nhật' : 'thêm mới'} thành công`, 'success');
        this.customerModal?.hide();
        this.fetchCustomers(); // Refresh data after changes
      } catch (error) {
        console.error('Error submitting form:', error);
        if (error.response && error.response.status === 422) {
            this.formErrors = error.response.data.errors || {};
            this.showToast('Lỗi xác thực dữ liệu. Vui lòng kiểm tra lại form.', 'danger');
        } else if (error.response && error.response.data && error.response.data.message) {
          this.showToast(`Lỗi: ${error.response.data.message}`, 'danger');
        } else {
          this.showToast('Đã xảy ra lỗi khi lưu khách hàng', 'danger');
        }
      } finally {
        this.loading = false;
      }
    },

    // Confirms deletion of a customer
    confirmDelete(customer) {
      this.customerToDelete = customer;
      this.deleteCustomerModal?.show();
    },

    // Deletes a customer
    async deleteCustomer() {
      this.deleteLoading = true;
      try {
        await axios.delete(`/api/customers/${this.customerToDelete.id}`);
        this.showToast('Khách hàng đã được xóa thành công', 'success');
        this.deleteCustomerModal?.hide();
        this.fetchCustomers(); // Refresh data
      } catch (error) {
        console.error('Error deleting customer:', error);
        this.showToast('Lỗi khi xóa khách hàng', 'danger');
      } finally {
        this.deleteLoading = false;
      }
    },

    // Resets the form to its initial empty state
    resetForm() {
      this.form = {
        id: null,
        name: '',
        email: '',
        phone: '',
        address: '',
        city: '',
        country: '',
        date_of_birth: null,
        gender: '',
        total_spent: 0,
        total_visits: 0,
        active: true,
      };
      this.formErrors = {};
    },

    /**
     * Helper to format currency.
     * @param {number} value
     * @returns {string} formatted currency string
     */
    formatCurrency(value) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(value);
    },

    /**
     * Helper to format date.
     * @param {string} dateString
     * @returns {string} formatted date string
     */
    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('vi-VN'); // Format as dd/mm/yyyy
    },

    /**
     * Helper to display gender.
     * @param {string} genderCode
     * @returns {string} localized gender display
     */
    getGenderDisplay(genderCode) {
        switch (genderCode) {
            case 'male': return 'Nam';
            case 'female': return 'Nữ';
            case 'other': return 'Khác';
            default: return '-';
        }
    },

    /**
     * Show toast notifications.
     * @param {string} message - The message to display.
     * @param {string} variant - Bootstrap color variant (e.g., 'success', 'danger', 'info', 'warning').
     * @param {string} position - Position of the toast (e.g., 'bottom-right', 'top-right', 'top-center', 'middle-center').
     */
    showToast(message, variant = 'success', position = 'bottom-right') {
      let toastContainer = document.querySelector('.toast-container');

      // Create toast container if it doesn't exist
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed p-3';
        toastContainer.style.zIndex = '1050'; // Ensure it's above other elements
        document.body.appendChild(toastContainer);
      }

      // Clear all previous position-related inline styles
      toastContainer.style.top = '';
      toastContainer.style.right = '';
      toastContainer.style.bottom = '';
      toastContainer.style.left = '';
      toastContainer.style.transform = '';

      const offset = '2rem'; // Khoảng cách từ mép (khoảng 32px)

      // Apply new position styles based on the 'position' argument
      switch (position) {
        case 'top-right':
          toastContainer.style.top = offset;
          toastContainer.style.right = offset;
          break;
        case 'top-center':
          toastContainer.style.top = offset;
          toastContainer.style.left = '50%';
          toastContainer.style.transform = 'translateX(-50%)';
          break;
        case 'bottom-center':
          toastContainer.style.bottom = offset;
          toastContainer.style.left = '50%';
          toastContainer.style.transform = 'translateX(-50%)';
          break;
        case 'middle-center':
          toastContainer.style.top = '50%';
          toastContainer.style.left = '50%';
          toastContainer.style.transform = 'translate(-50%, -50%)';
          break;
        case 'bottom-left':
          toastContainer.style.bottom = offset;
          toastContainer.style.left = offset;
          break;
        case 'top-left':
          toastContainer.style.top = offset;
          toastContainer.style.left = offset;
          break;
        case 'bottom-right': // Default
        default:
          toastContainer.style.bottom = offset;
          toastContainer.style.right = offset;
          break;
      }

      const toastEl = document.createElement('div');
      toastEl.className = `toast align-items-center text-white bg-${variant} border-0`;
      toastEl.setAttribute('role', 'alert');
      toastEl.setAttribute('aria-live', 'assertive');
      toastEl.setAttribute('aria-atomic', 'true');

      toastEl.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      `;

      toastContainer.appendChild(toastEl);
      const toast = new bootstrap.Toast(toastEl, {
        autohide: true,
        delay: 3000
      });
      toast.show();

      toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
        // Remove toast container if it's empty
        if (toastContainer.children.length === 0) {
          toastContainer.remove();
        }
      });
    },

    // Removes extra Bootstrap modal backdrops
    removeExtraBackdrops() {
      const backdrops = document.querySelectorAll('.modal-backdrop');
      if (backdrops.length > 1) {
          for (let i = 0; i < backdrops.length - 1; i++) {
              backdrops[i].remove();
              console.warn('Removed extra modal-backdrop.');
          }
      }
    }
  }
}
</script>

<style scoped>
.customer-management {
  padding: 20px;
}

.table-responsive {
  max-height: calc(100vh - 250px); /* Adjust as needed */
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

.badge {
  font-size: 0.85em;
  padding: 5px 8px;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}
</style>
<style>
/* Global styles for modals, ensuring z-index */
.modal-backdrop {
    --bs-backdrop-zindex: 1000;
}
</style>
