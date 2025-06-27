<template>
  <div class="branch-management">
    <!-- Main card for branch listing -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Quản lý Chi nhánh</h5>
        <div>
          <button @click="openAddModal" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm mới
          </button>
        </div>
      </div>

      <div class="card-body">
        <!-- Search Bar (Optional but good practice) -->
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="input-group">
              <input
                type="text"
                class="form-control"
                placeholder="Tìm kiếm chi nhánh theo tên, địa chỉ, SĐT..."
                v-model="searchQuery"
                @input="debounceSearch"
              >
              <button class="btn btn-outline-secondary" type="button" @click="fetchBranches">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Branch list table -->
        <div class="table-responsive table-scroll-area">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th width="60">STT</th>
                <th>Tên chi nhánh</th>
                <th>Địa chỉ</th>
                <th>Điện thoại</th>
                <th>Người quản lý</th>
                <th>Trạng thái</th>
                <th width="150">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(branch, index) in paginatedBranches" :key="branch.id">
                <td>{{ (currentPage - 1) * perPage + index + 1 }}</td>
                <td>{{ branch.name }}</td>
                <td>{{ branch.address }}</td>
                <td>{{ branch.phone || '-' }}</td>
                <td>{{ branch.manager_name || '-' }}</td>
                <td>
                  <span
                    class="badge"
                    :class="branch.active ? 'bg-success' : 'bg-secondary'"
                  >
                    {{ branch.active ? 'Kích hoạt' : 'Vô hiệu' }}
                  </span>
                </td>
                <td>
                  <button
                    @click="editBranch(branch)"
                    class="btn btn-sm btn-primary me-1"
                    title="Sửa"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    @click="confirmDelete(branch)"
                    class="btn btn-sm btn-danger"
                    title="Xóa"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="paginatedBranches.length === 0">
                <td colspan="7" class="text-center">Không có dữ liệu</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="input-group" style="width: 120px;">
                <label class="input-group-text" for="perPageSelect">Hiển thị/trang</label>
                <select class="form-select" id="perPageSelect" v-model.number="perPage" @change="changePage(1)">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div>
                Hiển thị {{ (currentPage - 1) * perPage + 1 }} đến {{ Math.min(currentPage * perPage, filteredBranches.length) }} của {{ filteredBranches.length }} chi nhánh
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

    <!-- Modal thêm/sửa chi nhánh -->
    <div class="modal fade" id="branchModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">{{ isEditing ? 'Cập nhật' : 'Thêm mới' }} Chi nhánh</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="submitForm">
              <div class="mb-3">
                <label class="form-label">Tên chi nhánh <span class="text-danger">*</span></label>
                <input type="text" class="form-control" v-model="form.name" required>
                <div v-if="formErrors.name" class="text-danger small mt-1">{{ formErrors.name[0] }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Địa chỉ <span class="text-danger">*</span></label>
                <input type="text" class="form-control" v-model="form.address" required>
                <div v-if="formErrors.address" class="text-danger small mt-1">{{ formErrors.address[0] }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Điện thoại</label>
                <input type="tel" class="form-control" v-model="form.phone">
                <div v-if="formErrors.phone" class="text-danger small mt-1">{{ formErrors.phone[0] }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Người quản lý</label>
                <input type="text" class="form-control" v-model="form.manager_name">
                <div v-if="formErrors.manager_name" class="text-danger small mt-1">{{ formErrors.manager_name[0] }}</div>
              </div>
              <div class="mb-3 form-check form-switch">
                <input class="form-check-input" type="checkbox" id="activeBranchCheck" v-model="form.active">
                <label class="form-check-label" for="activeBranchCheck">Kích hoạt chi nhánh</label>
              </div>

              <!-- Form Actions -->
              <div class="text-end mt-4">
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
    <div class="modal fade" id="deleteBranchModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">Xác nhận xóa chi nhánh</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Bạn có chắc chắn muốn xóa chi nhánh <strong>{{ branchToDelete?.name }}</strong>?</p>
            <p class="text-danger">Lưu ý: Thao tác này không thể hoàn tác và có thể ảnh hưởng đến dữ liệu liên quan (sản phẩm tồn kho, đơn hàng...).</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button
              type="button"
              class="btn btn-danger"
              @click="deleteBranch"
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
  name: 'BranchManagement',
  data() {
    return {
      allBranches: [], // Stores all raw branch data from the API
      searchQuery: '',

      // Form
      isEditing: false,
      form: {
        id: null,
        name: '',
        address: '',
        phone: '',
        manager_name: '',
        active: true, // Default to active for new branches
      },
      formErrors: {}, // For displaying validation errors from Laravel

      // Delete
      branchToDelete: null,

      // Loading states
      loading: false,        // For form submission
      deleteLoading: false,  // For delete action

      // Pagination data
      currentPage: 1,
      perPage: 10, // Default items per page
      pageInput: 1, // Used for the page number input field

      // Modal instances
      branchModal: null,
      deleteBranchModal: null,
    }
  },
  computed: {
    // Filter branches based on search query
    filteredBranches() {
      if (!this.searchQuery) {
        return this.allBranches;
      }
      const lowerCaseQuery = this.searchQuery.toLowerCase();
      return this.allBranches.filter(branch =>
        branch.name.toLowerCase().includes(lowerCaseQuery) ||
        branch.address.toLowerCase().includes(lowerCaseQuery) ||
        (branch.phone && branch.phone.toLowerCase().includes(lowerCaseQuery))
      );
    },
    // Paginate the filtered branches
    paginatedBranches() {
      const startIndex = (this.currentPage - 1) * this.perPage;
      const endIndex = startIndex + this.perPage;
      return this.filteredBranches.slice(startIndex, endIndex);
    },
    // Calculate the last page number
    lastPage() {
      return Math.ceil(this.filteredBranches.length / this.perPage);
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
          this.branchModal = new bootstrap.Modal(document.getElementById('branchModal'));
          this.deleteBranchModal = new bootstrap.Modal(document.getElementById('deleteBranchModal'));

          // Attach event listeners for modals to handle extra backdrops
          this.branchModal._element.addEventListener('shown.bs.modal', () => {
              this.removeExtraBackdrops();
          });
          this.branchModal._element.addEventListener('hidden.bs.modal', () => {
              this.resetForm(); // Reset form when modal is hidden
          });
          this.deleteBranchModal._element.addEventListener('shown.bs.modal', () => {
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

      this.fetchBranches(); // Initial fetch of all branches
    });
  },
  methods: {
    // Fetches all branches from the backend
    async fetchBranches() {
      try {
        const response = await axios.get('/api/branches', {
          params: {
            search: this.searchQuery // Send search query to backend
          }
        });
        this.allBranches = response.data;
        // After fetching/filtering, ensure pagination is reset or adjusted
        this.changePage(1); // Reset to first page
      } catch (error) {
        console.error('Error fetching branches:', error);
        this.showToast('Lỗi khi tải danh sách chi nhánh', 'danger');
      }
    },

    // Debounced search to avoid too many API calls
    debounceSearch: _.debounce(function() {
      this.fetchBranches();
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


    // Opens the add branch modal
    openAddModal() {
      this.isEditing = false;
      this.resetForm();
      this.form.active = true; // Default active for new branches
      this.branchModal?.show();
    },

    // Opens the edit branch modal and populates form
    editBranch(branch) {
      this.isEditing = true;
      this.resetForm(); // Reset before populating
      this.form = { ...branch }; // Copy branch data to form
      this.branchModal?.show();
    },

    // Submits the branch form (create/update)
    async submitForm() {
      this.loading = true;
      this.formErrors = {}; // Clear previous errors

      try {
        const url = this.isEditing
          ? `/api/branches/${this.form.id}`
          : '/api/branches';
        const method = this.isEditing ? 'put' : 'post';

        await axios[method](url, this.form);

        this.showToast(`Chi nhánh đã ${this.isEditing ? 'cập nhật' : 'thêm mới'} thành công`);
        this.branchModal?.hide();
        this.fetchBranches(); // Refresh data after changes
      } catch (error) {
        console.error('Error submitting form:', error);
        if (error.response && error.response.status === 422) {
            this.formErrors = error.response.data.errors || {};
            this.showToast('Lỗi xác thực dữ liệu. Vui lòng kiểm tra lại form.', 'danger');
        } else if (error.response && error.response.data && error.response.data.message) {
          this.showToast(`Lỗi: ${error.response.data.message}`, 'danger');
        } else {
          this.showToast('Đã xảy ra lỗi khi lưu chi nhánh', 'danger');
        }
      } finally {
        this.loading = false;
      }
    },

    // Confirms deletion of a branch
    confirmDelete(branch) {
      this.branchToDelete = branch;
      this.deleteBranchModal?.show();
    },

    // Deletes a branch
    async deleteBranch() {
      this.deleteLoading = true;
      try {
        await axios.delete(`/api/branches/${this.branchToDelete.id}`);
        this.showToast('Chi nhánh đã được xóa thành công');
        this.deleteBranchModal?.hide();
        this.fetchBranches(); // Refresh data
      } catch (error) {
        console.error('Error deleting branch:', error);
        this.showToast('Lỗi khi xóa chi nhánh', 'danger');
      } finally {
        this.deleteLoading = false;
      }
    },

    // Resets the form to its initial empty state
    resetForm() {
      this.form = {
        id: null,
        name: '',
        address: '',
        phone: '',
        manager_name: '',
        active: true,
      };
      this.formErrors = {};
    },

    // Show toast notifications (re-using the same utility)
    showToast(message, variant = 'success', position = 'bottom-right') {
      let toastContainer = document.querySelector('.toast-container');

      // Create toast container if it doesn't exist
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        // Apply position-fixed and p-3 for padding directly, without position classes
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
.branch-management {
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
