<template>
  <div class="returns-management p-4">
    <div class="card mb-4">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-undo-alt me-2"></i>Quản lý Phiếu Trả hàng</h5>
        <button class="btn btn-light btn-sm" @click="openCreateReturnModal">
          <i class="fas fa-plus me-2"></i>Tạo Phiếu Trả hàng mới
        </button>
      </div>
      <div class="card-body">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary me-2" role="status">
            <span class="visually-hidden">Đang tải phiếu trả hàng...</span>
          </div>
          <strong>Đang tải phiếu trả hàng...</strong>
        </div>
        <div v-else>
          <div class="mb-3 d-flex align-items-center">
            <input type="text" v-model="searchQuery" class="form-control me-2" placeholder="Tìm kiếm theo mã phiếu, trạng thái, lý do..." @input="debounceSearch">
            <button class="btn btn-outline-secondary" @click="fetchReturns">Tìm kiếm</button>
          </div>

          <div class="table-responsive">
            <table class="table table-hover table-striped">
              <thead>
                <tr>
                  <th scope="col">Mã Phiếu</th>
                  <th scope="col">Mã ĐH gốc</th>
                  <th scope="col">Khách hàng</th>
                  <th scope="col">Tổng hoàn trả</th>
                  <th scope="col">Trạng thái</th>
                  <th scope="col">Ngày trả</th>
                  <th scope="col">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="paginatedReturns.length === 0">
                  <td colspan="7" class="text-center text-muted">Không có phiếu trả hàng nào.</td>
                </tr>
                <tr v-for="ret in paginatedReturns" :key="ret.id">
                  <td>#{{ ret.id }}</td>
                  <td>#{{ ret.order_id }}</td>
                  <td>{{ ret.order?.customer?.name || 'Khách vãng lai' }}</td>
                  <td>{{ formatCurrency(ret.total_refund_amount) }}</td>
                  <td>
                    <span :class="getReturnStatusClass(ret.status)">
                      {{ getReturnStatusText(ret.status) }}
                    </span>
                  </td>
                  <td>{{ formatDate(ret.return_date) }}</td>
                  <td>
                    <button class="btn btn-info btn-sm me-2" @click="viewReturnDetails(ret)" title="Xem chi tiết">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning btn-sm me-2" @click="editReturn(ret)" title="Chỉnh sửa">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" @click="confirmDeleteReturn(ret.id)" title="Xóa">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <nav v-if="filteredReturns.length > 0">
            <ul class="pagination justify-content-center">
              <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button class="page-link" @click="changePage(1)">Đầu tiên</button>
              </li>
              <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button class="page-link" @click="changePage(currentPage - 1)">Trước</button>
              </li>
              <li class="page-item" v-for="page in paginationPages" :key="page" :class="{ active: currentPage === page }">
                <button v-if="page" class="page-link" @click="changePage(page)">{{ page }}</button>
                <span v-else class="page-link">...</span>
              </li>
              <li class="page-item" :class="{ disabled: currentPage === lastPage }">
                <button class="page-link" @click="changePage(currentPage + 1)">Sau</button>
              </li>
              <li class="page-item" :class="{ disabled: currentPage === lastPage }">
                <button class="page-link" @click="changePage(lastPage)">Cuối cùng</button>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>

    <!-- Create/Edit Return Modal -->
    <div class="modal fade" id="returnFormModal" tabindex="-1" aria-labelledby="returnFormModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="returnFormModalLabel">{{ isEditMode ? 'Chỉnh sửa Phiếu Trả hàng' : 'Tạo Phiếu Trả hàng mới' }}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveReturn">
              <div v-if="returnFormError" class="alert alert-danger">{{ returnFormError }}</div>

              <div class="mb-3">
                <label for="orderId" class="form-label">Đơn hàng gốc:</label>
                <select v-model="returnForm.order_id" class="form-select" @change="fetchOrderDetailsForReturn">
                  <option :value="null">-- Chọn đơn hàng gốc --</option>
                  <option v-for="order in ordersForSelection" :key="order.id" :value="order.id">
                    #{{ order.id }} - {{ order.customer ? order.customer.name : 'Khách vãng lai' }} ({{ formatDate(order.created_at) }})
                  </option>
                </select>
                <div v-if="validationErrors.order_id" class="text-danger small mt-1">{{ validationErrors.order_id[0] }}</div>
              </div>

              <div v-if="selectedOrderForReturn" class="mb-3">
                <h6>Sản phẩm trong đơn hàng #{{ selectedOrderForReturn.id }}:</h6>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                  <table class="table table-bordered table-sm">
                    <thead>
                      <tr>
                        <th>Sản phẩm</th>
                        <th>SL đã bán</th>
                        <th>SL trả về</th>
                        <th>Đơn giá</th>
                        <th>Hoàn tiền dự kiến</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in selectedOrderForReturn.order_items" :key="item.id">
                        <td>{{ item.product ? item.product.name : 'Sản phẩm không rõ' }}</td>
                        <td>{{ item.quantity }}</td>
                        <td>
                          <input 
                            type="number" 
                            v-model.number="returnForm.returned_items[item.id]" 
                            min="0" 
                            :max="item.quantity" 
                            class="form-control form-control-sm"
                            @input="updateCalculatedRefundAmount"
                          >
                          <div v-if="validationErrors[`returned_items.${item.id}`]" class="text-danger small mt-1">{{ validationErrors[`returned_items.${item.id}`][0] }}</div>
                        </td>
                        <td>{{ formatCurrency(item.unit_price) }}</td>
                        <td>{{ formatCurrency(item.unit_price * (returnForm.returned_items[item.id] || 0)) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="mb-3">
                <label for="returnReason" class="form-label">Lý do trả hàng:</label>
                <textarea id="returnReason" v-model="returnForm.reason" class="form-control" rows="2"></textarea>
                <div v-if="validationErrors.reason" class="text-danger small mt-1">{{ validationErrors.reason[0] }}</div>
              </div>
              
              <div class="d-flex justify-content-end mt-3">
                <strong>Tổng tiền hoàn trả dự kiến: {{ formatCurrency(calculatedRefundAmount) }}</strong>
              </div>

              <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                  <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                  {{ isSubmitting ? 'Đang lưu...' : 'Lưu Phiếu Trả hàng' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- View Return Details Modal -->
    <div class="modal fade" id="returnDetailModal" tabindex="-1" aria-labelledby="returnDetailModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title" id="returnDetailModalLabel">Chi tiết Phiếu Trả hàng #{{ selectedReturn?.id }}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" v-if="selectedReturn">
            <div class="row mb-3">
              <div class="col-md-6">
                <p><strong>Mã Đơn hàng gốc:</strong> #{{ selectedReturn.order_id }}</p>
                <p><strong>Khách hàng:</strong> {{ selectedReturn.order?.customer?.name || 'Khách vãng lai' }}</p>
                <p><strong>Ngày trả:</strong> {{ formatDate(selectedReturn.return_date) }}</p>
                <p><strong>Lý do:</strong> {{ selectedReturn.reason || '-' }}</p>
              </div>
              <div class="col-md-6 text-md-end">
                <p><strong>Tổng tiền hoàn trả:</strong> {{ formatCurrency(selectedReturn.total_refund_amount) }}</p>
                <p><strong>Trạng thái:</strong> <span :class="getReturnStatusClass(selectedReturn.status)">{{ getReturnStatusText(selectedReturn.status) }}</span></p>
                <p><strong>Ghi chú:</strong> {{ selectedReturn.notes || '-' }}</p>
              </div>
            </div>

            <h6>Sản phẩm trả về:</h6>
            <div class="table-responsive mb-3">
              <table class="table table-bordered table-sm">
                <thead>
                  <tr>
                    <th>Sản phẩm</th>
                    <th>SL trả về</th>
                    <th>Đơn giá</th>
                    <th>Hoàn tiền</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in selectedReturn.return_items" :key="item.id">
                    <td>
                      <span v-if="item.product_name_at_return">{{ item.product_name_at_return }}</span>
                      <span v-else-if="item.product && item.product.name">{{ item.product.name }}</span>
                      <span v-else class="text-muted">[Sản phẩm đã xóa]</span>
                    </td>
                    <td>{{ item.quantity }}</td>
                    <td>{{ formatCurrency(item.unit_price) }}</td>
                    <td>{{ formatCurrency(item.refund_amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteReturnModal" tabindex="-1" aria-labelledby="deleteReturnModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="deleteReturnModalLabel">Xác nhận xóa Phiếu Trả hàng</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Bạn có chắc chắn muốn xóa phiếu trả hàng #<strong>{{ returnToDeleteId }}</strong> này không? Thao tác này sẽ hoàn tác tồn kho.
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="button" class="btn btn-danger" @click="deleteReturn" :disabled="isDeleting">Xóa</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import _ from 'lodash';
import { Modal } from 'bootstrap';

export default {
  name: 'ReturnManagement',
  data() {
    return {
      allReturns: [],
      ordersForSelection: [], // For the dropdown when creating/editing a return
      searchQuery: '',
      selectedReturn: null,
      selectedOrderForReturn: null, // Stores order details when creating a return

      // Pagination
      currentPage: 1,
      perPage: 10,
      pageInput: 1,

      // Modals
      returnFormModal: null,
      returnDetailModal: null,
      deleteReturnModal: null,

      // Form data
      returnForm: {
        id: null,
        order_id: null,
        reason: '',
        returned_items: {}, // { order_item_id: quantity_to_return }
      },
      isEditMode: false,
      isSubmitting: false,
      isDeleting: false,
      loading: false, // For main table loading
      returnFormError: '',
      validationErrors: {}, // For backend validation errors

      returnToDeleteId: null,
    };
  },
  computed: {
    filteredReturns() {
      if (!this.searchQuery) {
        return this.allReturns;
      }
      const lowerCaseQuery = this.searchQuery.toLowerCase();
      return this.allReturns.filter(ret =>
        String(ret.id).includes(lowerCaseQuery) ||
        String(ret.order_id).includes(lowerCaseQuery) ||
        this.getReturnStatusText(ret.status).toLowerCase().includes(lowerCaseQuery) ||
        (ret.reason && ret.reason.toLowerCase().includes(lowerCaseQuery)) ||
        (ret.order?.customer?.name && ret.order.customer.name.toLowerCase().includes(lowerCaseQuery))
      );
    },
    paginatedReturns() {
      const startIndex = (this.currentPage - 1) * this.perPage;
      const endIndex = startIndex + this.perPage;
      return this.filteredReturns.slice(startIndex, endIndex);
    },
    lastPage() {
      return Math.ceil(this.filteredReturns.length / this.perPage);
    },
    paginationPages() {
      const pages = [];
      const startPage = Math.max(1, this.currentPage - 2);
      const endPage = Math.min(this.lastPage, this.currentPage + 2);
      for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
      }
      return pages;
    },
    calculatedRefundAmount() {
      let total = 0;
      if (this.selectedOrderForReturn && this.selectedOrderForReturn.order_items) {
        this.selectedOrderForReturn.order_items.forEach(item => {
          const quantity = this.returnForm.returned_items[item.id] || 0;
          total += quantity * item.unit_price;
        });
      } else if (this.isEditMode && this.selectedReturn) {
          // In edit mode, if order details not loaded for form, calculate from selectedReturn items
          this.selectedReturn.return_items.forEach(item => {
              // We need the original unit_price, which should be in returnItem or fetched
              // For simplicity, we'll use item.unit_price from the return item itself
              total += (this.returnForm.returned_items[item.order_item_id] || 0) * item.unit_price;
          });
      }
      return total;
    }
  },
  mounted() {
    this.$nextTick(() => {
      if (typeof bootstrap !== 'undefined') {
        try {
          this.returnFormModal = new Modal(document.getElementById('returnFormModal'));
          this.returnDetailModal = new Modal(document.getElementById('returnDetailModal'));
          this.deleteReturnModal = new Modal(document.getElementById('deleteReturnModal'));

          this.returnFormModal._element.addEventListener('shown.bs.modal', this.removeExtraBackdrops);
          this.returnFormModal._element.addEventListener('hidden.bs.modal', this.resetReturnForm);
          this.returnDetailModal._element.addEventListener('shown.bs.modal', this.removeExtraBackdrops);
          this.deleteReturnModal._element.addEventListener('shown.bs.modal', this.removeExtraBackdrops);
          console.log("Bootstrap modals initialized successfully for ReturnManagement.");
        } catch (e) {
          console.error("Error initializing Bootstrap modals in ReturnManagement:", e);
        }
      }

      this.fetchReturns();
      this.fetchOrdersForSelection(); // Fetch orders for the create return dropdown
    });
  },
  methods: {
    async fetchReturns() {
      this.loading = true;
      try {
        const response = await axios.get('/api/returns', {
          params: { search: this.searchQuery }
        });
        this.allReturns = response.data.data || response.data;
        this.changePage(1);
      } catch (error) {
        console.error('Lỗi khi tải danh sách phiếu trả hàng:', error);
        this.showToast('Lỗi khi tải danh sách phiếu trả hàng', 'danger');
      } finally {
        this.loading = false;
      }
    },

    async fetchOrdersForSelection() {
      try {
        const response = await axios.get('/api/orders?per_page=-1'); // Get all orders, or enough for selection
        this.ordersForSelection = response.data.data || response.data;
      } catch (error) {
        console.error('Lỗi khi tải danh sách đơn hàng để chọn:', error);
        this.showToast('Lỗi khi tải danh sách đơn hàng để tạo phiếu trả', 'danger');
      }
    },

    async fetchOrderDetailsForReturn() {
        if (!this.returnForm.order_id) {
            this.selectedOrderForReturn = null;
            this.returnForm.returned_items = {};
            return;
        }
        try {
            const response = await axios.get(`/api/orders/${this.returnForm.order_id}?include=orderItems.product`);
            this.selectedOrderForReturn = response.data;
            // Initialize returned_items quantities to 0 for all order items
            const initialReturnedItems = {};
            this.selectedOrderForReturn.order_items.forEach(item => {
                initialReturnedItems[item.id] = 0;
            });
            this.returnForm.returned_items = initialReturnedItems;
        } catch (error) {
            console.error('Lỗi khi tải chi tiết đơn hàng cho phiếu trả:', error);
            this.showToast('Không thể tải chi tiết đơn hàng. Vui lòng thử lại.', 'danger');
            this.selectedOrderForReturn = null;
            this.returnForm.returned_items = {};
        }
    },

    debounceSearch: _.debounce(function() {
      this.fetchReturns();
    }, 500),

    changePage(page) {
      if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
        this.currentPage = page;
        this.pageInput = page;
      } else if (this.lastPage === 0) {
        this.currentPage = 1;
        this.pageInput = 1;
      }
    },
    goToPage() {
      if (this.pageInput >= 1 && this.pageInput <= this.lastPage && this.pageInput !== this.currentPage) {
        this.changePage(this.pageInput);
      } else {
        this.pageInput = this.currentPage;
      }
    },

    openCreateReturnModal() {
      this.isEditMode = false;
      this.resetReturnForm();
      this.fetchOrdersForSelection(); // Refresh orders list
      this.returnFormModal?.show();
    },

    editReturn(ret) {
      this.isEditMode = true;
      this.resetReturnForm();
      this.selectedReturn = ret;
      this.returnForm.id = ret.id;
      this.returnForm.order_id = ret.order_id;
      this.returnForm.reason = ret.reason;

      // Populate returned_items for editing based on existing return items
      const returnedItemsMap = {};
      if (ret.return_items) {
          ret.return_items.forEach(item => {
              returnedItemsMap[item.order_item_id] = item.quantity;
          });
      }
      this.returnForm.returned_items = returnedItemsMap;

      // Fetch the original order details to show correct product quantities
      this.fetchOrderDetailsForReturn(); // This will populate selectedOrderForReturn
      
      this.returnFormModal?.show();
    },

    async saveReturn() {
        this.isSubmitting = true;
        this.returnFormError = '';
        this.validationErrors = {};

        const itemsToSubmit = [];
        if (this.selectedOrderForReturn && this.selectedOrderForReturn.order_items) {
            this.selectedOrderForReturn.order_items.forEach(originalItem => {
                const quantityToReturn = this.returnForm.returned_items[originalItem.id] || 0;
                if (quantityToReturn > 0) {
                    itemsToSubmit.push({
                        product_id: originalItem.product_id,
                        order_item_id: originalItem.id,
                        quantity: quantityToReturn,
                    });
                }
            });
        }

        if (itemsToSubmit.length === 0) {
            this.returnFormError = 'Vui lòng chọn ít nhất một sản phẩm để trả hàng với số lượng lớn hơn 0.';
            this.isSubmitting = false;
            return;
        }

        const payload = {
            order_id: this.returnForm.order_id,
            reason: this.returnForm.reason,
            returned_items: itemsToSubmit,
        };

        try {
            const url = this.isEditMode
                ? `/api/returns/${this.returnForm.id}`
                : '/api/returns';
            const method = this.isEditMode ? 'put' : 'post';

            await axios[method](url, payload);
            this.showToast(`Phiếu trả hàng đã ${this.isEditMode ? 'cập nhật' : 'tạo'} thành công!`);
            this.returnFormModal?.hide();
            this.fetchReturns(); // Refresh list
        } catch (error) {
            console.error('Lỗi khi lưu phiếu trả hàng:', error);
            if (error.response && error.response.data) {
                if (error.response.status === 422) {
                    this.validationErrors = error.response.data.errors || {};
                    this.returnFormError = 'Có lỗi xác thực. Vui lòng kiểm tra lại thông tin nhập.';
                    // Detailed message can be built from validationErrors if needed
                } else {
                    this.returnFormError = error.response.data.message || 'Lỗi khi lưu phiếu trả hàng. Vui lòng thử lại.';
                }
            } else {
                this.returnFormError = 'Đã xảy ra lỗi không xác định khi lưu phiếu trả hàng. Vui lòng thử lại.';
            }
        } finally {
            this.isSubmitting = false;
        }
    },

    confirmDeleteReturn(id) {
        this.returnToDeleteId = id;
        this.deleteReturnModal?.show();
    },

    async deleteReturn() {
        this.isDeleting = true;
        try {
            await axios.delete(`/api/returns/${this.returnToDeleteId}`);
            this.showToast('Phiếu trả hàng đã được xóa thành công và tồn kho đã được hoàn tác!', 'success');
            this.deleteReturnModal?.hide();
            this.fetchReturns();
        } catch (error) {
            console.error('Lỗi khi xóa phiếu trả hàng:', error);
            this.showToast('Lỗi khi xóa phiếu trả hàng. Vui lòng thử lại.', 'danger');
        } finally {
            this.isDeleting = false;
        }
    },

    resetReturnForm() {
      this.returnForm = {
        id: null,
        order_id: null,
        reason: '',
        returned_items: {},
      };
      this.selectedOrderForReturn = null;
      this.returnFormError = '';
      this.validationErrors = {};
      this.isEditMode = false;
    },

    updateCalculatedRefundAmount() {
        // This computed property handles the calculation automatically
        // No explicit function needed here, just trigger reactivity
    },

    viewReturnDetails(ret) {
      this.selectedReturn = ret;
      this.returnDetailModal?.show();
    },

    formatCurrency(value) {
      if (typeof value !== 'number' || isNaN(value)) return '0 VNĐ';
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
    },
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      if (isNaN(date.getTime())) return '-';
      return date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    },
    getReturnStatusText(statusCode) {
      switch (statusCode) {
        case 'pending': return 'Đang chờ';
        case 'completed': return 'Hoàn thành';
        case 'cancelled': return 'Hủy';
        default: return statusCode || 'Không xác định';
      }
    },
    getReturnStatusClass(statusCode) {
      switch (statusCode) {
        case 'pending': return 'badge bg-warning text-dark';
        case 'completed': return 'badge bg-success';
        case 'cancelled': return 'badge bg-danger';
        default: return 'badge bg-secondary';
      }
    },
    // Using existing getPaymentMethodDisplay from ql_donhang for consistency
    getPaymentMethodDisplay(methodCode) {
        switch (methodCode) {
            case 'cash': return 'Tiền mặt';
            case 'card': return 'Thẻ';
            case 'transfer': return 'Chuyển khoản';
            case 'momo': return 'Momo'; 
            default: return methodCode || 'Không xác định';
        }
    },

    showToast(message, variant = 'success', position = 'bottom-right') {
      let toastContainer = document.querySelector('.toast-container');
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed p-3';
        toastContainer.style.zIndex = '1050';
        document.body.appendChild(toastContainer);
      }
      toastContainer.style.top = ''; toastContainer.style.right = ''; toastContainer.style.bottom = ''; toastContainer.style.left = ''; toastContainer.style.transform = '';
      const offset = '2rem';
      switch (position) {
        case 'top-right': toastContainer.style.top = offset; toastContainer.style.right = offset; break;
        case 'top-center': toastContainer.style.top = offset; toastContainer.style.left = '50%'; toastContainer.style.transform = 'translateX(-50%)'; break;
        case 'bottom-center': toastContainer.style.bottom = offset; toastContainer.style.left = '50%'; toastContainer.style.transform = 'translateX(-50%)'; break;
        case 'middle-center': toastContainer.style.top = '50%'; toastContainer.style.left = '50%'; toastContainer.style.transform = 'translate(-50%, -50%)'; break;
        case 'bottom-left': toastContainer.style.bottom = offset; toastContainer.style.left = offset; break;
        case 'top-left': toastContainer.style.top = offset; toastContainer.style.left = offset; break;
        case 'bottom-right': default: toastContainer.style.bottom = offset; toastContainer.style.right = offset; break;
      }
      const toastEl = document.createElement('div');
      toastEl.className = `toast align-items-center text-white bg-${variant} border-0`;
      toastEl.setAttribute('role', 'alert'); toastEl.setAttribute('aria-live', 'assertive'); toastEl.setAttribute('aria-atomic', 'true');
      toastEl.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      `;
      toastContainer.appendChild(toastEl);
      const toast = new bootstrap.Toast(toastEl, { autohide: true, delay: 3000 });
      toast.show();
      toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
        if (toastContainer.children.length === 0) { toastContainer.remove(); }
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
};
</script>

<style scoped>
.returns-management {
  font-family: 'Inter', sans-serif;
  background-color: #f0f2f5;
  min-height: 100vh;
}

.card {
  border-radius: 0.75rem;
  overflow: hidden;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
  border-bottom: 1px solid rgba(0, 0, 0, 0.125);
  background-color: #007bff;
  color: white;
}

.card-body {
  padding: 1.5rem;
}

.table-responsive {
  max-height: calc(100vh - 350px);
  overflow-y: auto;
  margin-bottom: 0;
  border-radius: 0.5rem;
}

.table-responsive table {
  margin-bottom: 0;
}

.table thead th {
  background-color: #e9ecef;
  position: sticky;
  top: 0;
  z-index: 10;
  padding: 0.75rem 1rem;
  font-weight: 600;
  color: #495057;
  border-bottom: 2px solid #dee2e6;
}

.table tbody tr:hover {
  background-color: #f8f9fa;
}

.table td {
  vertical-align: middle;
  padding: 0.75rem 1rem;
}

.badge {
  font-size: 0.8em;
  padding: 0.4em 0.7em;
  border-radius: 0.5rem;
}

.modal-lg {
  max-width: 800px;
}
.modal-xl {
  max-width: 1140px;
}

.modal-header {
  border-bottom: 1px solid #e9ecef;
}

.modal-footer {
  border-top: 1px solid #e9ecef;
}

.form-select, .form-control {
    border-radius: 0.5rem;
    border-color: #ced4da;
}

.btn {
    border-radius: 0.5rem;
    transition: all 0.2s ease-in-out;
}

.btn-info { background-color: #17a2b8; border-color: #17a2b8; }
.btn-info:hover { background-color: #138496; border-color: #117a8b; }
.btn-warning { background-color: #ffc107; border-color: #ffc107; color: #212529; }
.btn-warning:hover { background-color: #e0a800; border-color: #cc9900; }
.btn-danger { background-color: #dc3545; border-color: #dc3545; }
.btn-danger:hover { background-color: #c82333; border-color: #bd2130; }
.btn-success { background-color: #28a745; border-color: #28a745; }
.btn-success:hover { background-color: #218838; border-color: #1e7e34; }
.btn-primary { background-color: #007bff; border-color: #007bff; }
.btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
.btn-secondary { background-color: #6c757d; border-color: #6c757d; }
.btn-secondary:hover { background-color: #5a6268; border-color: #5a6268; }

.pagination .page-item .page-link {
    border-radius: 0.5rem;
    margin: 0 3px;
    border: 1px solid #dee2e6;
    color: #007bff;
}
.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}
.pagination .page-item.disabled .page-link {
    color: #6c757d;
}
.input-group-text {
    border-radius: 0.5rem 0 0 0.5rem;
}
.form-control[type="number"] {
    border-radius: 0 0.5rem 0.5rem 0;
}
</style>
<style>
.modal-backdrop { --bs-backdrop-zindex: 1000; }
</style>
