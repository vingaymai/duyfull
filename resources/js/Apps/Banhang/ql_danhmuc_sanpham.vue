<template>
  <div class="category-management">
    <!-- Phần quản lý danh sách -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Quản lý Danh mục Sản phẩm</h5>
        <div>
          <button @click="openAddModal" class="btn btn-primary me-2">
            <i class="fas fa-plus"></i> Thêm mới
          </button>
          <button @click="exportExcel" class="btn btn-success me-2">
            <i class="fas fa-file-export"></i> Xuất Excel
          </button>
          <button @click="openImportModal" class="btn btn-info">
            <i class="fas fa-file-import"></i> Nhập Excel
          </button>
        </div>
      </div>

      <!-- Thanh tìm kiếm -->
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="input-group">
              <input
                type="text"
                class="form-control"
                placeholder="Tìm kiếm danh mục..."
                v-model="searchQuery"
                @input="debounceSearch"
              >
              <button class="btn btn-outline-secondary" type="button" @click="applySearchAndPagination(1)">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Bảng danh sách danh mục -->
        <div class="table-responsive table-scroll-area">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th width="60">STT</th>
                <th>Tên danh mục</th>
                <th>Slug</th>
                <th>Danh mục cha</th>
                <th width="100">Vị trí</th>
                <th width="120">Trạng thái</th>
                <th width="150">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(category, index) in categories" :key="category.id">
                <td>{{ (currentPage - 1) * perPage + index + 1 }}</td>
                <td :style="{'padding-left': (category.level * 20) + 'px'}">
                  <span v-if="category.level > 0" class="text-muted">--- </span>{{ category.name }}
                </td>
                <td>{{ category.slug }}</td>
                <td>{{ category.parent ? category.parent.name : '-' }}</td>
                <td>{{ category.position }}</td>
                <td>
                  <span
                    class="badge"
                    :class="category.active ? 'bg-success' : 'bg-secondary'"
                  >
                    {{ category.active ? 'Kích hoạt' : 'Vô hiệu' }}
                  </span>
                </td>
                <td>
                  <button
                    @click="editCategory(category)"
                    class="btn btn-sm btn-primary me-1"
                    title="Sửa"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    @click="confirmDelete(category)"
                    class="btn btn-sm btn-danger"
                    title="Xóa"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="categories.length === 0">
                <td colspan="7" class="text-center">Không có dữ liệu</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="input-group" style="width: 120px;">
              <label class="input-group-text" for="perPageSelect">Hiển thị/trang</label>
              <select class="form-select" id="perPageSelect" v-model.number="perPage" @change="applySearchAndPagination(1)">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
          <div>
            Hiển thị {{ (currentPage - 1) * perPage + 1 }} đến {{ Math.min(currentPage * perPage, totalFilteredCategories) }} của {{ totalFilteredCategories }} danh mục
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

    <!-- Modal thêm/sửa danh mục -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">{{ isEditing ? 'Cập nhật' : 'Thêm mới' }} Danh mục</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="submitForm">
              <div class="mb-3">
                <label class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                <input
                  type="text"
                  class="form-control"
                  v-model="form.name"
                  required
                  @input="generateSlug"
                >
              </div>
              <div class="mb-3">
                <label class="form-label">Slug <span class="text-danger">*</span></label>
                <input
                  type="text"
                  class="form-control"
                  v-model="form.slug"
                  required
                >
              </div>
              <div class="mb-3">
                <label class="form-label">Danh mục cha</label>
                <select class="form-select" v-model="form.parent_id">
                  <option value="">-- Không có --</option>
                  <option
                    v-for="cat in parentCategories"
                    :value="cat.id"
                    :key="cat.id"
                    :disabled="cat.id === form.id"
                    :style="{'padding-left': (cat.level * 20) + 'px'}"
                  >
                    <span v-if="cat.level > 0">--- </span>{{ cat.name }}
                  </option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Vị trí</label>
                <input
                  type="number"
                  class="form-control"
                  v-model="form.position"
                  min="0"
                >
              </div>
              <div class="mb-3 form-check">
                <input
                  type="checkbox"
                  class="form-check-input"
                  v-model="form.active"
                  id="activeCheck"
                >
                <label class="form-check-label" for="activeCheck">Kích hoạt</label>
              </div>
              <div class="text-end">
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
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">Xác nhận xóa</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Bạn có chắc chắn muốn xóa danh mục <strong>{{ categoryToDelete?.name }}</strong>?</p>
            <p class="text-danger">Lưu ý: Tất cả danh mục con cũng sẽ bị xóa!</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button
              type="button"
              class="btn btn-danger"
              @click="deleteCategory"
              :disabled="deleteLoading"
            >
              <span v-if="deleteLoading" class="spinner-border spinner-border-sm"></span>
              Xóa
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- New Modal for Excel Import -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title">Nhập Danh mục từ Excel</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Chọn file Excel (.xlsx) chứa danh mục để nhập.</p>
            <div class="mb-3">
              <label for="excelFile" class="form-label">Chọn File Excel</label>
              <input
                class="form-control"
                type="file"
                id="excelFile"
                ref="excelFileInput"
                accept=".xlsx, .xls"
                @change="handleFileChange"
              >
              <div v-if="importErrors.file" class="text-danger mt-1">{{ importErrors.file[0] }}</div>
            </div>
            <div v-if="importStatus.messages.length > 0" class="mt-3">
              <div
                v-for="(msg, index) in importStatus.messages"
                :key="index"
                :class="{'text-success': importStatus.success, 'text-danger': !importStatus.success}"
              >
                {{ msg }}
              </div>
            </div>
            <div v-if="importStatus.validationErrors.length > 0" class="mt-3">
              <h6 class="text-danger">Lỗi dữ liệu trong file:</h6>
              <ul class="text-danger">
                <li v-for="(error, index) in importStatus.validationErrors" :key="index">
                  Hàng {{ error.row }}: {{ error.errors.join(', ') }}
                </li>
              </ul>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Hủy</button>
            <button
              type="button"
              class="btn btn-info"
              @click="importExcel"
              :disabled="!selectedFile || importLoading"
            >
              <span v-if="importLoading" class="spinner-border spinner-border-sm"></span>
              Nhập Dữ liệu
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import _ from 'lodash'

export default {
  name: 'CategoryManagement',
  data() {
    return {
      allCategoriesData: [], // Stores all raw category data from the API
      categories: [], // Processed, hierarchical, and paginated categories for display
      parentCategories: [], // Parent categories for the dropdown in modals
      searchQuery: '',

      // Pagination data
      currentPage: 1,
      perPage: 10, // Default items per page is 20
      totalFilteredCategories: 0, // Total categories after applying search filter
      lastPage: 1,
      pageInput: 1, // Used for the page number input field

      // Form
      isEditing: false,
      form: {
        id: null,
        name: '',
        slug: '',
        parent_id: null,
        position: 0,
        active: true
      },

      // Delete
      categoryToDelete: null,

      // Loading states
      loading: false,
      deleteLoading: false,
      importLoading: false, // Loading state for import

      // Excel Import
      selectedFile: null,
      importErrors: {}, // Server-side validation errors (e.g., file type)
      importStatus: { // Import status and detailed validation errors from Excel
        success: false,
        messages: [],
        validationErrors: []
      },

      // Modal instances
      categoryModal: null,
      deleteModal: null,
      importModal: null // Instance for import modal
    }
  },
  computed: {
    // Generates an array of page numbers to display in the pagination controls
    paginationPages() {
      const pages = [];
      // Display up to 5 nearby pages (2 before, 2 after the current page)
      const startPage = Math.max(1, this.currentPage - 2);
      const endPage = Math.min(this.lastPage, this.currentPage + 2);

      for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
      }
      return pages;
    }
  },
  watch: {
      // Watch for changes in searchQuery and re-apply search and pagination from page 1
      searchQuery: function() {
          this.debounceSearch();
      }
  },
  mounted() {
    this.$nextTick(() => {
        if (typeof bootstrap !== 'undefined') {
            try {
                // Initialize Bootstrap modals
                this.categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
                this.deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                this.importModal = new bootstrap.Modal(document.getElementById('importModal'));

                // Attach event listeners for modals to handle extra backdrops
                this.categoryModal._element.addEventListener('shown.bs.modal', () => {
                    console.log('Category Modal: shown');
                    this.removeExtraBackdrops();
                });
                this.categoryModal._element.addEventListener('hidden.bs.modal', () => {
                    console.log('Category Modal: hidden');
                });
                this.deleteModal._element.addEventListener('shown.bs.modal', () => {
                    console.log('Delete Modal: shown');
                    this.removeExtraBackdrops();
                });
                this.deleteModal._element.addEventListener('hidden.bs.modal', () => {
                    console.log('Delete Modal: hidden');
                });
                this.importModal._element.addEventListener('shown.bs.modal', () => {
                    console.log('Import Modal: shown');
                    this.removeExtraBackdrops();
                });
                this.importModal._element.addEventListener('hidden.bs.modal', () => {
                    console.log('Import Modal: hidden');
                    this.resetImportState(); // Reset import state when modal is closed
                });

                console.log("Bootstrap modals initialized successfully.");
            } catch (e) {
                console.error("Error initializing Bootstrap modals:", e);
                console.warn("Bootstrap JS object might be corrupted or an element ID is missing.");
            }
        } else {
            console.warn("Bootstrap JS object is not available. Modals may not function correctly. Ensure Bootstrap's JS is loaded before your Vue app mounts.");
        }

        // Load all categories and parent categories when the component is mounted
        this.fetchAllCategoriesAndApply();
        this.fetchParentCategories();
    });
  },
  methods: {
    // Method to fetch all categories from the backend and then apply search/pagination locally
    async fetchAllCategoriesAndApply() {
      try {
        const response = await axios.get('/api/categories');
        this.allCategoriesData = response.data; // Store all raw data

        // Apply search and pagination to the locally stored data
        this.applySearchAndPagination(this.currentPage);
      } catch (error) {
        console.error('Error fetching all categories:', error);
        this.showToast('Lỗi khi tải toàn bộ danh sách danh mục', 'danger');
      }
    },

    // Method to build a hierarchical category list (used for full dataset, e.g., parentCategories)
    buildCategoryHierarchy(categories, parentId = null, level = 0) {
      let hierarchicalList = [];
      // Filter direct children from the provided categories set
      const children = categories.filter(cat => cat.parent_id === parentId);

      // Sort children by position for consistent display order
      children.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));

      for (const category of children) {
        // Add the current category with its level
        hierarchicalList.push({ ...category, level: level });

        // Recursively add its children
        hierarchicalList = hierarchicalList.concat(
          this.buildCategoryHierarchy(categories, category.id, level + 1)
        );
      }
      return hierarchicalList;
    },

    async fetchParentCategories() {
      try {
        const response = await axios.get('/api/categories/parents')
        // Apply buildCategoryHierarchy to the list of parent categories
        // To display hierarchical structure in the modal's select option
        this.parentCategories = this.buildCategoryHierarchy(response.data);
      } catch (error) {
        console.error('Error fetching parent categories:', error)
      }
    },

    // Method to apply search and pagination on the frontend data
    applySearchAndPagination(page = 1) {
      this.currentPage = page;
      this.pageInput = page;

      // Build the full hierarchical structure from allCategoriesData
      // This ensures correct levels are assigned even if parents are not filtered
      const hierarchicalFullList = this.buildCategoryHierarchy(this.allCategoriesData);

      // Filter data based on searchQuery
      let filtered = hierarchicalFullList;
      if (this.searchQuery) {
        const lowerCaseQuery = this.searchQuery.toLowerCase();
        filtered = hierarchicalFullList.filter(category =>
          category.name.toLowerCase().includes(lowerCaseQuery) ||
          category.slug.toLowerCase().includes(lowerCaseQuery)
        );
      }

      this.totalFilteredCategories = filtered.length;
      this.lastPage = Math.ceil(this.totalFilteredCategories / this.perPage);

      // Ensure the current page does not exceed lastPage after filtering
      if (this.currentPage > this.lastPage && this.lastPage > 0) {
        this.currentPage = this.lastPage;
        this.pageInput = this.lastPage;
      } else if (this.lastPage === 0) { // If no results, reset to page 1
        this.currentPage = 1;
        this.pageInput = 1;
      }

      const startIndex = (this.currentPage - 1) * this.perPage;
      const endIndex = startIndex + this.perPage;
      this.categories = filtered.slice(startIndex, endIndex); // Data for the current page
    },

    // Change page when pagination button is clicked
    changePage(page) {
      if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
        this.applySearchAndPagination(page);
      }
    },

    // Go to a specific page when page number is entered and Enter is pressed
    goToPage() {
      if (this.pageInput >= 1 && this.pageInput <= this.lastPage && this.pageInput !== this.currentPage) {
        this.applySearchAndPagination(this.pageInput);
      } else {
        // If the page number is invalid or is the current page, reset the input value to the current page
        this.pageInput = this.currentPage;
      }
    },

    openAddModal() {
      this.isEditing = false
      this.resetForm()
      if (this.categoryModal) {
        console.log('Attempting to show category modal (Add mode)...');
        this.categoryModal.show();
      } else {
        console.error("Cannot open Add Modal: categoryModal is not initialized.");
        this.showToast('Lỗi: Không thể mở hộp thoại thêm danh mục. Vui lòng thử lại.', 'danger');
      }
    },

    generateSlug() {
      if (!this.isEditing) {
        this.form.slug = this.form.name
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/đ/g, 'd').replace(/Đ/g, 'D')
          .replace(/[^\w\s-]/g, '')
          .replace(/\s+/g, '-')
          .replace(/--+/g, '-')
          .trim();
      }
    },

    // Debounced search function, will call applySearchAndPagination
    debounceSearch: _.debounce(function() {
      this.applySearchAndPagination(1); // Reset to the first page for a new search
    }, 500),

    editCategory(category) {
      this.isEditing = true
      this.form = {
        id: category.id,
        name: category.name,
        slug: category.slug,
        parent_id: category.parent_id,
        position: category.position,
        active: category.active
      }
      if (this.categoryModal) {
        console.log('Attempting to show category modal (Edit mode)...');
        this.categoryModal.show();
      } else {
        console.error("Cannot open Edit Modal: categoryModal is not initialized.");
        this.showToast('Lỗi: Không thể mở hộp thoại cập nhật danh mục. Vui lòng thử lại.', 'danger');
      }
    },

    async submitForm() {
      this.loading = true

      try {
        const url = this.isEditing
          ? `/api/categories/${this.form.id}`
          : '/api/categories'

        const method = this.isEditing ? 'put' : 'post'
        await axios[method](url, this.form)

        this.showToast(`Danh mục đã ${this.isEditing ? 'cập nhật' : 'thêm mới'} thành công`)
        console.log('Attempting to hide category modal after submit...');
        this.categoryModal?.hide()
        this.fetchAllCategoriesAndApply(); // Reload all data and re-apply search/pagination
        this.fetchParentCategories();
      } catch (error) {
        console.error('Error submitting form:', error)
        if (error.response && error.response.data && error.response.data.message) {
          this.showToast(`Lỗi: ${error.response.data.message}`, 'danger')
        } else {
          this.showToast('Đã xảy ra lỗi khi lưu danh mục', 'danger')
        }
      } finally {
        this.loading = false
      }
    },

    confirmDelete(category) {
      this.categoryToDelete = category
      if (this.deleteModal) {
        console.log('Attempting to show delete modal...');
        this.deleteModal.show();
      } else {
        console.error("Cannot open Delete Modal: deleteModal is not initialized.");
        this.showToast('Lỗi: Không thể mở hộp thoại xác nhận xóa. Vui lòng thử lại.', 'danger');
      }
    },

    async deleteCategory() {
      this.deleteLoading = true

      try {
        await axios.delete(`/api/categories/${this.categoryToDelete.id}`)
        this.showToast('Danh mục đã được xóa thành công')
        console.log('Attempting to hide delete modal after delete...');
        this.deleteModal?.hide()
        this.fetchAllCategoriesAndApply(); // Reload all data and re-apply search/pagination
        this.fetchParentCategories();
      } catch (error) {
        console.error('Error deleting category:', error)
        this.showToast('Lỗi khi xóa danh mục', 'danger')
      } finally {
        this.deleteLoading = false
      }
    },

    async exportExcel() {
      try {
        const response = await axios.get('/api/categories/export', {
          responseType: 'blob'
        })

        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `danh_muc_san_pham_${new Date().toISOString().slice(0,10)}.xlsx`)
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url);
      } catch (error) {
        console.error('Error exporting Excel:', error)
        this.showToast('Lỗi khi xuất dữ liệu ra Excel', 'danger')
      }
    },

    openImportModal() {
      this.resetImportState();
      if (this.importModal) {
        console.log('Attempting to show import modal...');
        this.importModal.show();
      } else {
        console.error("Cannot open Import Modal: importModal is not initialized.");
        this.showToast('Lỗi: Không thể mở hộp thoại nhập Excel. Vui lòng thử lại.', 'danger');
      }
    },

    handleFileChange(event) {
      this.selectedFile = event.target.files[0];
      this.importErrors = {};
      this.importStatus = { success: false, messages: [], validationErrors: [] };
    },

    async importExcel() {
      if (!this.selectedFile) {
        this.showToast('Vui lòng chọn một file Excel.', 'warning');
        return;
      }

      this.importLoading = true;
      this.importErrors = {};
      this.importStatus = { success: false, messages: [], validationErrors: [] };

      const formData = new FormData();
      formData.append('file', this.selectedFile);

      try {
        const response = await axios.post('/api/categories/import', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });
        this.showToast(response.data.message || 'Nhập Excel thành công!', 'success');
        this.importStatus.success = true;
        this.importStatus.messages.push(response.data.message || 'Nhập Excel thành công!');
        if (response.data.validation_errors) {
            this.importStatus.validationErrors = response.data.validation_errors;
            this.importStatus.messages.push('Có lỗi validation trong một số hàng.');
            this.importStatus.success = false;
            this.showToast('Có lỗi validation trong một số hàng. Vui lòng xem chi tiết.', 'warning');
        }

        this.fetchAllCategoriesAndApply(); // Reload all data and re-apply search/pagination
        this.fetchParentCategories();
      } catch (error) {
        console.error('Error importing Excel:', error);
        if (error.response && error.response.status === 422) {
          this.importErrors = error.response?.data?.errors || {}; 
          this.importStatus.messages.push('Lỗi validation file: ' + (error.response.data.message || 'Vui lòng kiểm tra lại file.'));
          this.showToast('Lỗi validation file: ' + (error.response.data.message || 'Vui lòng kiểm tra lại file.'), 'danger');
        } else if (error.response && error.response.data && error.response.data.message) {
          this.showToast(`Lỗi khi nhập Excel: ${error.response.data.message}`, 'danger');
          this.importStatus.messages.push(`Lỗi server: ${error.response.data.message}`);
        } else {
          this.showToast('Đã xảy ra lỗi khi nhập dữ liệu Excel.', 'danger');
          this.importStatus.messages.push('Đã xảy ra lỗi không xác định khi nhập dữ liệu.');
        }
        this.importStatus.success = false;
      } finally {
        this.importLoading = false;
        if (this.$refs.excelFileInput) {
          this.$refs.excelFileInput.value = '';
        }
      }
    },

    resetForm() {
      this.form = {
        id: null,
        name: '',
        slug: '',
        parent_id: null,
        position: 0,
        active: true
      }
    },
    resetImportState() {
      this.selectedFile = null;
      this.importErrors = {};
      this.importStatus = { success: false, messages: [], validationErrors: [] };
      if (this.$refs.excelFileInput) {
        this.$refs.excelFileInput.value = '';
      }
    },

    showToast(message, variant = 'success') {
      let toastContainer = document.querySelector('.toast-container');
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-32 end-0 p-3';
        toastContainer.style.zIndex = '1050';
        document.body.appendChild(toastContainer);
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
        if (toastContainer.children.length === 0) {
          toastContainer.remove();
        }
      });
    },

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
.category-management {
  padding: 20px;
}

.table-responsive {
  /* Set max-height and allow scrolling for the table body */
  max-height: calc(100vh - 300px); /* Adjust this value as needed */
  overflow-y: auto;
  margin-bottom: 0; /* Remove extra margin to avoid unwanted space */
}

/* Ensure the table header is sticky when scrolling */
.table-responsive table thead {
  position: sticky;
  top: 0;
  background-color: #f8f9fa; /* Background color for the header to prevent transparency */
  z-index: 10; /* Ensure header stays on top */
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

.form-check-input {
  margin-top: 0.2rem;
}
</style>
<style>
.modal-backdrop {
    --bs-backdrop-zindex: 1000;
}
</style>
