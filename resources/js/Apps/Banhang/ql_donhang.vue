<template>
  <div class="orders-management font-inter">
    <div class="card mb-4 shadow-lg rounded-xl overflow-hidden">
      <div class="card-header bg-gradient-to-r from-blue-500 to-blue-700 text-white d-flex justify-content-between align-items-center py-3 px-4 rounded-t-xl">
        <!-- Đã bỏ tiêu đề "Quản lý Đơn hàng" và nút "Tạo Đơn hàng mới" -->
        <h5 class="mb-0 text-xl font-bold flex items-center">
          <i class="fas fa-box me-2"></i>Danh sách Đơn hàng
        </h5>
      </div>
      <div class="card-body p-4">
        <!-- Filter Bar: Search, Selects, Date Pickers in one row -->
        <div class="filter-bar d-flex flex-wrap align-items-center gap-2 mb-3">
            <input
              type="text"
              v-model="searchQuery"
              class="form-control form-control-sm flex-grow-1"
              placeholder="Mã đơn, KH, SĐT, TT, PTTT..."
              @input="debounceSearch"
              style="min-width: 150px;"
            >
            <select
              v-model="filterStatus"
              class="form-select form-select-sm"
              @change="fetchOrders"
              style="width: auto; min-width: 120px;"
            >
              <option value="">Tất cả TT</option>
              <option value="pending">Đang chờ</option>
              <option value="completed">Hoàn thành</option>
              <option value="cancelled">Hủy</option>
              <option value="refunded">Đã hoàn tiền</option>
              <option value="partially_refunded">Hoàn tiền một phần</option>
            </select>
             <select
              v-model="filterPaymentMethod"
              class="form-select form-select-sm"
              @change="fetchOrders"
              style="width: auto; min-width: 120px;"
            >
              <option value="">Tất cả PTTT</option>
              <option value="cash">Tiền mặt</option>
              <option value="card">Thẻ</option>
              <option value="transfer">Chuyển khoản</option>
              <option value="momo">Momo</option>
              <option value="other">Khác</option>
            </select>
            <input
              type="date"
              v-model="filterStartDate"
              class="form-control form-control-sm"
              @change="fetchOrders"
              title="Từ ngày"
              style="width: auto; min-width: 130px;"
            >
            <input
              type="date"
              v-model="filterEndDate"
              class="form-control form-control-sm"
              @change="fetchOrders"
              title="Đến ngày"
              style="width: auto; min-width: 130px;"
            >
            <button class="btn btn-primary btn-sm" @click="fetchOrders" title="Lọc">
              <i class="fas fa-filter"></i> Lọc
            </button>
            <button class="btn btn-secondary btn-sm" title="Reset bộ lọc">
              <i class="fas fa-redo-alt"></i> Reset
            </button>
        </div>

        <!-- Total Price Display -->
        <div class="mb-3 p-2 bg-light rounded-md shadow-sm d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-muted">Tổng giá trị đơn hàng (hiển thị):</h6>
            <h5 class="mb-0 text-primary font-bold">{{ formatCurrency(totalFilteredOrdersAmount) }}</h5>
        </div>

        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-info me-2" role="status">
            <span class="visually-hidden">Đang tải đơn hàng...</span>
          </div>
          <strong>Đang tải đơn hàng...</strong>
        </div>
        <div v-else>
          <div class="table-responsive">
            <table class="table table-hover table-striped table-sm">
              <thead>
                <tr>
                  <th scope="col" class="py-2 px-3">Mã Đơn</th>
                  <th scope="col" class="py-2 px-3">Khách hàng</th>
                  <th scope="col" class="py-2 px-3 text-end">Tổng tiền</th>
                  <th scope="col" class="py-2 px-3">Trạng thái</th>
                  <th scope="col" class="py-2 px-3">P.thức TT</th>
                  <th scope="col" class="py-2 px-3">Ngày tạo</th>
                  <th scope="col" class="py-2 px-3">Người tạo</th> <!-- Added User Column -->
                  <th scope="col" class="py-2 px-3">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="paginatedOrders.length === 0">
                  <td colspan="8" class="text-center text-muted py-4">Không có đơn hàng nào.</td>
                </tr>
                <tr v-for="order in paginatedOrders" :key="order.id">
                  <td class="py-2 px-3">#{{ order.id }}</td>
                  <td class="py-2 px-3">{{ order.customer ? order.customer.name : 'Khách vãng lai' }}</td>
                  <td class="py-2 px-3 text-end">{{ formatCurrency(order.total_amount) }}</td>
                  <td class="py-2 px-3">
                    <span :class="getOrderStatusClass(order.status)">
                      {{ getOrderStatusText(order.status) }}
                    </span>
                  </td>
                  <td class="py-2 px-3">{{ getPaymentMethodDisplay(order.payment_method) }}</td>
                  <td class="py-2 px-3">{{ formatDate(order.created_at) }}</td>
                  <td class="py-2 px-3">{{ order.user ? order.user.name : 'N/A' }}</td> <!-- Display User Name -->
                  <td class="py-2 px-3 whitespace-nowrap">
                    <button class="btn btn-info btn-xs me-1" @click="viewOrderDetails(order)" title="Xem chi tiết">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning btn-xs me-1" @click="editOrder(order)" title="Chỉnh sửa">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-xs" @click="confirmDeleteOrder(order.id)" title="Xóa">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                    <!-- Return button, visible only if order is not fully refunded/cancelled -->
                    <button
                        v-if="order.status !== 'refunded' && order.status !== 'cancelled'"
                        class="btn btn-success btn-xs ms-1"
                        @click="openReturnOrderModal(order)"
                        title="Trả hàng"
                    >
                        <i class="fas fa-undo-alt"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <nav v-if="filteredOrders.length > 0">
            <ul class="pagination justify-content-center mt-3">
              <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button class="page-link py-1 px-2" @click="changePage(1)">Đầu tiên</button>
              </li>
              <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button class="page-link py-1 px-2" @click="changePage(currentPage - 1)">Trước</button>
              </li>
              <li class="page-item" v-for="page in paginationPages" :key="page" :class="{ active: currentPage === page }">
                <button v-if="page" class="page-link py-1 px-2" @click="changePage(page)">{{ page }}</button>
                <span v-else class="page-link py-1 px-2">...</span>
              </li>
              <li class="page-item" :class="{ disabled: currentPage === lastPage }">
                <button class="page-link py-1 px-2" @click="changePage(currentPage + 1)">Sau</button>
              </li>
              <li class="page-item" :class="{ disabled: currentPage === lastPage }">
                <button class="page-link py-1 px-2" @click="changePage(lastPage)">Cuối cùng</button>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>

    <!-- Edit Order Modal (Chỉ dùng cho chỉnh sửa) -->
    <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="orderModalLabel">Chỉnh sửa Đơn hàng</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveOrder">
              <div class="mb-3">
                <label for="customer" class="form-label">Khách hàng:</label>
                <select v-model="form.customer_id" class="form-select">
                  <option :value="null">-- Chọn khách hàng (tùy chọn) --</option>
                  <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                    {{ customer.name }} ({{ customer.phone }})
                  </option>
                </select>
              </div>

              <h6>Sản phẩm trong đơn hàng:</h6>
              <div class="mb-3 table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-bordered table-sm">
                  <thead>
                    <tr>
                      <th>Sản phẩm</th>
                      <th>SL</th>
                      <th>Đơn giá</th>
                      <th>Thành tiền</th>
                      <th>Thao tác</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, index) in form.order_items" :key="index">
                      <td>
                        <select v-model="item.product_id" class="form-select form-select-sm" @change="updateItemPrice(item)">
                          <option :value="null">-- Chọn sản phẩm --</option>
                          <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }} ({{ formatCurrency(product.base_price) }})
                          </option>
                        </select>
                      </td>
                      <td>
                        <input type="number" v-model.number="item.quantity" min="1" class="form-control form-control-sm" @input="updateTotalAmount">
                      </td>
                      <td>
                        <input type="number" v-model.number="item.unit_price" min="0" step="0.01" class="form-control form-control-sm" @input="updateTotalAmount">
                      </td>
                      <td>{{ formatCurrency(item.quantity * item.unit_price) }}</td>
                      <td>
                        <button type="button" class="btn btn-danger btn-sm" @click="removeOrderItem(index)">Xóa</button>
                      </td>
                    </tr>
                    <tr v-if="form.order_items.length === 0">
                      <td colspan="5" class="text-center text-muted">Chưa có sản phẩm nào trong đơn hàng.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button type="button" class="btn btn-success btn-sm mb-3" @click="addOrderItem">
                <i class="fas fa-plus me-2"></i>Thêm sản phẩm
              </button>

              <div class="mb-3">
                <label for="totalAmount" class="form-label">Tổng tiền đơn hàng:</label>
                <input type="text" id="totalAmount" :value="formatCurrency(form.total_amount)" class="form-control" readonly>
              </div>

              <div class="mb-3">
                <label for="paidAmount" class="form-label">Số tiền khách trả:</label>
                <input type="number" id="paidAmount" v-model.number="form.paid_amount" min="0" :max="form.total_amount" step="0.01" class="form-control" @input="calculateChange">
              </div>

              <div class="mb-3">
                <label for="changeAmount" class="form-label">Tiền thừa trả khách:</label>
                <input type="text" id="changeAmount" :value="formatCurrency(form.change_amount)" class="form-control" readonly>
              </div>

              <div class="mb-3">
                <label for="paymentMethod" class="form-label">Phương thức thanh toán:</label>
                <select v-model="form.payment_method" class="form-select">
                  <option value="cash">Tiền mặt</option>
                  <option value="card">Thẻ ngân hàng</option>
                  <option value="transfer">Chuyển khoản</option>
                </select>
              </div>

              <div class="mb-3">
                <label for="status" class="form-label">Trạng thái đơn hàng:</label>
                <select v-model="form.status" class="form-select">
                  <option value="pending">Chờ xử lý</option>
                  <option value="completed">Hoàn thành</option>
                  <option value="cancelled">Hủy</option>
                  <option value="refunded">Đã hoàn tiền</option>
                  <option value="partially_refunded">Hoàn tiền một phần</option>
                </select>
              </div>

              <div class="mb-3">
                <label for="notes" class="form-label">Ghi chú:</label>
                <textarea id="notes" v-model="form.notes" class="form-control" rows="3"></textarea>
              </div>

              <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                  <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                  {{ isSubmitting ? 'Đang lưu...' : 'Lưu Đơn hàng' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- View Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title" id="orderDetailsModalLabel">Chi tiết Đơn hàng #{{ selectedOrder?.id }}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" v-if="selectedOrder">
            <div class="row mb-3">
              <div class="col-md-6">
                <p><strong>Khách hàng:</strong> {{ selectedOrder.customer ? selectedOrder.customer.name : 'Khách vãng lai' }}</p>
                <p><strong>Email:</strong> {{ selectedOrder.customer ? selectedOrder.customer.email || '-' : '-' }}</p>
                <p><strong>Số điện thoại:</strong> {{ selectedOrder.customer ? selectedOrder.customer.phone || '-' : '-' }}</p>
                <p><strong>Người tạo:</strong> {{ selectedOrder.user ? selectedOrder.user.name : 'N/A' }}</p>
              </div>
              <div class="col-md-6 text-md-end">
                <p><strong>Ngày tạo:</strong> {{ formatDate(selectedOrder.created_at) }}</p>
                <p><strong>Tổng tiền:</strong> {{ formatCurrency(selectedOrder.total_amount) }}</p>
                <p><strong>Trạng thái:</strong> <span :class="getOrderStatusClass(selectedOrder.status)">{{ getOrderStatusText(selectedOrder.status) }}</span></p>
                <p><strong>Phương thức TT:</strong> {{ selectedOrder.payment_method }}</p>
                <p><strong>Số tiền khách trả:</strong> {{ formatCurrency(selectedOrder.paid_amount) }}</p>
                <p><strong>Tiền thừa trả khách:</strong> {{ formatCurrency(selectedOrder.paid_amount - selectedOrder.total_amount) }}</p>
              </div>
            </div>

            <h6>Sản phẩm:</h6>
            <div class="table-responsive mb-3">
              <table class="table table-bordered table-sm">
                <thead>
                  <tr>
                    <th>Sản phẩm</th>
                    <th>SL</th>
                    <th>Đơn giá</th>
                    <th>Thành tiền</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in selectedOrder.order_items" :key="item.id">
                    <td>{{ item.product ? item.product.name : 'Sản phẩm không rõ' }}</td>
                    <td>{{ item.quantity }}</td>
                    <td>{{ formatCurrency(item.unit_price) }}</td>
                    <td>{{ formatCurrency(item.quantity * item.unit_price) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <h6>Ghi chú:</h6>
            <p>{{ selectedOrder.notes || 'Không có ghi chú.' }}</p>
          </div>
          <div class="modal-footer">
            <!-- Nút "Tạo Trả hàng" -->
            <button type="button" class="btn btn-secondary"
                    @click="openReturnOrderModal(selectedOrder)"
                    :disabled="selectedOrder && (selectedOrder.status === 'cancelled' || selectedOrder.status === 'refunded')">
                <i class="fas fa-undo-alt me-2"></i>Tạo Trả hàng
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Return Order Modal -->
    <ReturnOrderModal
        :is-visible="showReturnOrderModal"
        :order="selectedOrder"
        @close="showReturnOrderModal = false"
        @return-success="handleReturnSuccess"
    />

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="deleteOrderModalLabel">Xác nhận xóa Đơn hàng</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Bạn có chắc chắn muốn xóa đơn hàng này không? Thao tác này không thể hoàn tác.
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="button" class="btn btn-danger" @click="deleteOrder">Xóa</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import _ from 'lodash';
import { Modal } from 'bootstrap'; // Correct import syntax for Bootstrap's Modal JS
import ReturnOrderModal from './ReturnOrderModal.vue'; // Import ReturnOrderModal component

export default {
  name: 'OrderManagement',
  components: {
    ReturnOrderModal
  },
  data() {
    // Calculate last 7 days for default date filter
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(endDate.getDate() - 6); // Go back 6 days to include today (7 days total)

    return {
      allOrders: [],
      searchQuery: '',
      filterStatus: '', // New filter: status
      filterPaymentMethod: '', // New filter: payment method
      filterStartDate: startDate.toISOString().slice(0, 10), // Default to 7 days ago
      filterEndDate: endDate.toISOString().slice(0, 10),     // Default to today
      selectedOrder: null,

      currentPage: 1,
      perPage: 10,
      pageInput: 1,

      orderDetailModal: null,
      orderModal: null,
      deleteOrderModal: null,

      form: {
        customer_id: null,
        total_amount: 0,
        paid_amount: 0,
        change_amount: 0,
        payment_method: 'cash',
        status: 'pending',
        notes: '',
        order_items: [],
      },
      products: [],
      customers: [],
      isEditMode: false,
      isSubmitting: false,
      orderToDeleteId: null,

      showReturnOrderModal: false,
      loading: false, // Loading state for the main table
    };
  },
  computed: {
    filteredOrders() {
      let filtered = this.allOrders;

      if (this.searchQuery) {
        const lowerCaseQuery = this.searchQuery.toLowerCase();
        filtered = filtered.filter(order =>
          String(order.id).includes(lowerCaseQuery) ||
          (order.customer?.name && order.customer.name.toLowerCase().includes(lowerCaseQuery)) ||
          (order.customer?.phone && order.customer.phone.toLowerCase().includes(lowerCaseQuery)) ||
          this.getOrderStatusText(order.status).toLowerCase().includes(lowerCaseQuery) || // Filter by status text
          this.getPaymentMethodDisplay(order.payment_method).toLowerCase().includes(lowerCaseQuery) // Filter by payment method text
        );
      }

      if (this.filterStatus) {
        filtered = filtered.filter(order => order.status === this.filterStatus);
      }

      if (this.filterPaymentMethod) {
        filtered = filtered.filter(order => order.payment_method === this.filterPaymentMethod);
      }

      // Client-side date filtering (if backend doesn't handle it with per_page=-1)
      if (this.filterStartDate || this.filterEndDate) {
          const start = this.filterStartDate ? new Date(this.filterStartDate).setHours(0,0,0,0) : null;
          const end = this.filterEndDate ? new Date(this.filterEndDate).setHours(23,59,59,999) : null;

          filtered = filtered.filter(order => {
              const orderDate = new Date(order.created_at).getTime();
              return (!start || orderDate >= start) && (!end || orderDate <= end);
          });
      }

      return filtered;
    },
    paginatedOrders() {
      const startIndex = (this.currentPage - 1) * this.perPage;
      const endIndex = startIndex + this.perPage;
      return this.filteredOrders.slice(startIndex, endIndex);
    },
    lastPage() {
      return Math.ceil(this.filteredOrders.length / this.perPage);
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
    totalFilteredOrdersAmount() {
        return this.filteredOrders.reduce((sum, order) => sum + parseFloat(order.total_amount || 0), 0);
    }
  },
  mounted() {
    this.$nextTick(async () => {
      if (typeof bootstrap !== 'undefined') {
        try {
          this.orderModal = new Modal(document.getElementById('orderModal'));
          this.orderDetailModal = new Modal(document.getElementById('orderDetailsModal'));
          this.deleteOrderModal = new Modal(document.getElementById('deleteOrderModal'));

          this.orderDetailModal._element.addEventListener('shown.bs.modal', this.removeExtraBackdrops);
          this.orderModal._element.addEventListener('shown.bs.modal', this.removeExtraBackdrops);
          this.deleteOrderModal._element.addEventListener('shown.bs.modal', this.removeExtraBackdrops);
          console.log("Bootstrap modals initialized successfully for OrderManagement.");
        } catch (e) {
            console.error("Error initializing Bootstrap modals in OrderManagement:", e);
            console.warn("Bootstrap JS object might be corrupted or an element ID is missing.");
        }
      } else {
        console.warn("Bootstrap JS object is not available. Modals may not function correctly. Ensure Bootstrap's JS is loaded before your Vue app mounts.");
      }

      await this.fetchOrders();
      await this.fetchProducts();
      await this.fetchCustomers();
    });
  },
  methods: {
    async fetchOrders() {
      this.loading = true;
      try {
        const response = await axios.get('/api/orders', {
          params: {
            search: this.searchQuery,
            status: this.filterStatus,
            payment_method: this.filterPaymentMethod,
            start_date: this.filterStartDate,
            end_date: this.filterEndDate,
            include: 'customer,user' // Ensure user relation is loaded
          }
        });
        // Explicitly parse numerical fields to ensure they are numbers
        this.allOrders = (response.data.data || response.data).map(order => ({
            ...order,
            total_amount: parseFloat(order.total_amount),
            paid_amount: parseFloat(order.paid_amount),
            change_amount: parseFloat(order.change_amount),
            // Ensure order_items also have numerical unit_price
            order_items: order.order_items ? order.order_items.map(item => ({
                ...item,
                unit_price: parseFloat(item.unit_price)
            })) : []
        }));
        this.changePage(1);
      } catch (error) {
        console.error('Error fetching orders:', error);
        this.showToast('Lỗi khi tải danh sách đơn hàng', 'danger');
      } finally {
        this.loading = false;
      }
    },

    debounceSearch: _.debounce(function() {
      this.fetchOrders();
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

    // openNewOrderInterface() đã bị xóa
    editOrder(order) {
      this.isEditMode = true; // Giữ lại cờ này nếu modal vẫn dùng cho edit
      this.selectedOrder = order;
      this.form = JSON.parse(JSON.stringify(order));
      if (!this.form.order_items) {
        this.form.order_items = [];
      }
      this.orderModal.show();
    },
    async saveOrder() {
        this.isSubmitting = true;
        try {
            // Chỉ thực hiện PUT (cập nhật) vì chức năng tạo mới đã bỏ
            await axios.put(`/api/orders/${this.selectedOrder.id}`, this.form);
            this.showToast('Cập nhật đơn hàng thành công!', 'success');
            
            this.orderModal.hide();
            await this.fetchOrders();
        } catch (error) {
            console.error('Lỗi khi lưu đơn hàng:', error);
            const errorMessage = error.response?.data?.message || 'Lỗi khi lưu đơn hàng. Vui lòng kiểm tra lại thông tin.';
            this.showToast(errorMessage, 'danger');
        } finally {
            this.isSubmitting = false;
        }
    },
    confirmDeleteOrder(id) {
        this.orderToDeleteId = id;
        this.deleteOrderModal.show();
    },
    async deleteOrder() {
        try {
            await axios.delete(`/api/orders/${this.orderToDeleteId}`);
            this.showToast('Xóa đơn hàng thành công!', 'success');
            this.deleteOrderModal.hide();
            await this.fetchOrders();
        } catch (error) {
            console.error('Lỗi khi xóa đơn hàng:', error);
            const errorMessage = error.response?.data?.message || 'Lỗi khi xóa đơn hàng. Vui lòng thử lại.';
            this.showToast(errorMessage, 'danger');
        }
    },
    addOrderItem() {
        this.form.order_items.push({
            product_id: null,
            quantity: 1,
            unit_price: 0,
        });
    },
    removeOrderItem(index) {
        this.form.order_items.splice(index, 1);
        this.updateTotalAmount();
    },
    updateItemPrice(item) {
        const product = this.products.find(p => p.id === item.product_id);
        if (product) {
            item.unit_price = parseFloat(product.base_price);
        }
        this.updateTotalAmount();
    },
    updateTotalAmount() {
        let total = 0;
        this.form.order_items.forEach(item => {
            total += (item.quantity || 0) * (item.unit_price || 0);
        });
        this.form.total_amount = total;
        this.calculateChange();
    },
    calculateChange() {
        this.form.change_amount = (this.form.paid_amount || 0) - (this.form.total_amount || 0);
    },
    resetForm() {
        this.form = {
            customer_id: null,
            total_amount: 0,
            paid_amount: 0,
            change_amount: 0,
            payment_method: 'cash',
            status: 'pending',
            notes: '',
            order_items: [],
        };
    },
    async fetchProducts() {
        try {
            const response = await axios.get('/api/products?per_page=-1');
            this.products = response.data.data;
        } catch (error) {
            console.error('Error fetching products:', error);
            this.showToast('Lỗi khi tải danh sách sản phẩm', 'danger');
        }
    },
    async fetchCustomers() {
        try {
            const response = await axios.get('/api/customers?per_page=-1');
            this.customers = response.data.data;
        } catch (error) {
            console.error('Error fetching customers:', error);
            this.showToast('Lỗi khi tải danh sách khách hàng', 'danger');
        }
    },

    async viewOrderDetails(order) {
      try {
        const response = await axios.get(`/api/orders/${order.id}?include=orderItems.product,customer,user`); // Include user
        this.selectedOrder = response.data;
        this.orderDetailModal?.show();
      } catch (error) {
        console.error('Lỗi khi tải chi tiết đơn hàng', error);
        this.showToast('Lỗi khi tải chi tiết đơn hàng', 'danger');
      }
    },

    openReturnOrderModal(order) {
        axios.get(`/api/orders/${order.id}?include=orderItems.product,customer,user`) // Include user
             .then(response => {
                 this.selectedOrder = response.data;
                 this.showReturnOrderModal = true;
                 this.orderDetailModal?.hide();
             })
             .catch(error => {
                 console.error('Lỗi khi tải chi tiết đơn hàng để trả hàng:', error);
                 this.showToast('Không thể tải chi tiết đơn hàng để tạo phiếu trả. Vui lòng thử lại.', 'danger');
             });
    },

    handleReturnSuccess(newReturn) {
        console.log('Phiếu trả hàng đã được tạo:', newReturn);
        this.showToast('Phiếu trả hàng đã được xử lý thành công!', 'success');
        this.showReturnOrderModal = false;
        this.fetchOrders();
    },

    formatCurrency(value) {
      if (typeof value !== 'number' || isNaN(value)) return '0 VNĐ';
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(value);
    },

    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    },

    getOrderStatusText(statusCode) {
        switch (statusCode) {
            case 'pending': return 'Đang chờ';
            case 'completed': return 'Hoàn thành';
            case 'cancelled': return 'Đã hủy';
            case 'refunded': return 'Đã hoàn tiền';
            case 'partially_refunded': return 'Hoàn tiền một phần';
            default: return statusCode;
        }
    },

    getOrderStatusClass(statusCode) {
        switch (statusCode) {
            case 'pending': return 'badge bg-yellow-100 text-yellow-700'; // Using Tailwind-like classes
            case 'completed': return 'badge bg-green-100 text-green-700';
            case 'cancelled': return 'badge bg-red-100 text-red-700';
            case 'refunded': return 'badge bg-blue-100 text-blue-700';
            case 'partially_refunded': return 'badge bg-purple-100 text-purple-700';
            default: return 'badge bg-gray-100 text-gray-700';
        }
    },

    getPaymentMethodDisplay(methodCode) {
        switch (methodCode) {
            case 'cash': return 'Tiền mặt';
            case 'card': return 'Thẻ';
            case 'transfer': return 'Chuyển khoản';
            case 'momo': return 'Momo';
            case 'other': return 'Khác';
            default: return methodCode;
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

      toastContainer.style.top = '';
      toastContainer.style.right = '';
      toastContainer.style.bottom = '';
      toastContainer.style.left = '';
      toastContainer.style.transform = '';

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
      const toast = new Modal(toastEl, { autohide: true, delay: 3000 }); // Changed to Bootstrap's Toast
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
.orders-management {
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
  background-color: #007bff; /* Changed from info to primary color */
  color: white;
}

.card-body {
  padding: 1.5rem;
}

/* Filter bar styling */
.filter-bar .form-control-sm,
.filter-bar .form-select-sm,
.filter-bar .btn-sm {
    padding: 0.25rem 0.5rem; /* Smaller padding */
    font-size: 0.875rem; /* Smaller font size */
    height: auto; /* Allow height to adjust based on content */
    border-radius: 0.375rem; /* Slightly less rounded */
}

.filter-bar .btn-sm i {
    font-size: 0.8em; /* Make icons slightly smaller too */
}

/* Table styling for smaller density */
.table-responsive {
  max-height: calc(100vh - 350px);
  overflow-y: auto;
  margin-bottom: 0;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb; /* Add a light border */
}

.table-responsive table {
  margin-bottom: 0;
}

.table thead th {
  background-color: #f9fafb; /* Light header background */
  position: sticky;
  top: 0;
  z-index: 10;
  padding: 0.5rem 0.75rem; /* Reduced padding for smaller rows */
  font-weight: 600;
  color: #4b5563; /* Darker gray text */
  border-bottom: 2px solid #e5e7eb;
  font-size: 0.85rem; /* Smaller font for headers */
}

.table tbody tr:hover {
  background-color: #f9fafb;
}

.table td {
  vertical-align: middle;
  padding: 0.5rem 0.75rem; /* Reduced padding for smaller rows */
  font-size: 0.8rem; /* Smaller font for table data */
}

.badge {
  font-size: 0.75em; /* Even smaller font for badges */
  color: #dc3545;
  padding: 0.3em 0.6em; /* Smaller padding */
  border-radius: 0.5rem;
}

/* Action button in table cells */
.btn-xs {
    padding: 0.15rem 0.4rem; /* Extra small padding */
    font-size: 0.7rem; /* Extra small font size */
    border-radius: 0.25rem; /* Smaller border radius */
    line-height: 1; /* Tighter line height for buttons */
}
.btn-xs i {
    font-size: 0.7em; /* Smaller icon size */
}

.btn-info {
    background-color: #17a2b8;
    border-color: #17a2b8;
}
.btn-info:hover {
    background-color: #138496;
    border-color: #117a8b;
}

.btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}
.btn-warning:hover {
    background-color: #e0a800;
    border-color: #cc9900;
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}
.btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}
.btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

/* Pagination styles */
.pagination .page-item .page-link {
    border-radius: 0.375rem; /* Smaller rounded corners */
    margin: 0 2px; /* Less margin */
    padding: 0.25rem 0.6rem; /* Smaller padding */
    font-size: 0.8rem; /* Smaller font */
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
</style>
<style>
/* Global styles for modals, ensuring z-index */
.modal-backdrop {
    --bs-backdrop-zindex: 1000;
}
</style>
