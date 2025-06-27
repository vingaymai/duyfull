<template>
  <div class="new-order container-fluid py-4">
    <div class="row g-4">
      <!-- Left Column: Product Selection & Search -->
      <div class="col-lg-7">
        <div class="card h-100 shadow-sm">
          <div class="card-header bg-primary text-white d-flex flex-wrap align-items-center justify-content-between py-2 px-3">
            <!-- Product Search & Barcode Scan on one line -->
            <div class="input-group flex-grow-1 my-1 me-2" style="max-width: 300px;">
                <input
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="Tìm sản phẩm theo tên/SKU..."
                  v-model="productSearchQuery"
                  @input="debounceProductSearch"
                  aria-label="Search Products"
                >
                <button class="btn btn-light btn-sm" type="button" @click="fetchProducts">
                  <i class="fas fa-search text-primary"></i>
                </button>
            </div>
            <div class="input-group flex-grow-1 my-1" style="max-width: 250px;">
                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                <input
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="Quét mã vạch..."
                  v-model="barcodeInput"
                  @keyup.enter="handleBarcodeScan"
                  aria-label="Barcode Scan"
                >
            </div>
          </div>
          <div class="card-body product-list-area">
            <div class="text-center py-5" v-if="loadingProducts">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang tải sản phẩm...</span>
              </div>
              <p class="mt-2">Đang tải sản phẩm...</p>
            </div>
            <div class="text-center py-5 text-muted" v-else-if="filteredProducts.length === 0">
              <p>Không tìm thấy sản phẩm nào.</p>
            </div>
            <div v-else>
              <!-- Grid View -->
              <div class="row g-3" v-if="productDisplayMode === 'grid'">
                <div :class="gridColumnClass" v-for="product in paginatedProducts" :key="product.id">
                  <div class="product-card h-100 d-flex flex-column shadow-sm rounded-3 overflow-hidden" @click="addToCart(product)">
                    <img
                      :src="product.image_url || 'https://placehold.co/400x300/e0e0e0/ffffff?text=No+Image'"
                      class="card-img-top product-image"
                      alt="Product Image"
                      onerror="this.onerror=null;this.src='https://placehold.co/400x300/e0e0e0/ffffff?text=No+Image';"
                    >
                    <div class="card-body d-flex flex-column p-2">
                      <h6 class="card-title mb-1 text-truncate" :title="product.name">{{ product.name }}</h6>
                      <!-- Display base_price using formatCurrency, defaulting to 0 if product.base_price is null/undefined -->
                      <p class="card-text text-success fw-bold mb-1">{{ formatCurrency(product.base_price || 0) }}</p>
                      <p class="card-text text-muted small mb-2">
                        <!-- Display stock, defaulting to '0' if null/undefined, or 'N/A' if not tracked -->
                        <span v-if="product.track_stock">Tồn kho: <span :class="{'text-danger': product.stock <= 5}">{{ product.stock === null || product.stock === undefined ? '0' : product.stock }}</span></span>
                        <span v-else>Không theo dõi tồn kho</span>
                      </p>
                      <button
                        class="btn btn-sm btn-outline-primary mt-auto w-100"
                        :disabled="product.track_stock && (product.stock === null || product.stock === undefined || product.stock <= 0)"
                      >
                        <i class="fas fa-plus-circle me-1"></i> Thêm vào giỏ
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <!-- List View -->
              <div v-else-if="productDisplayMode === 'list'">
                <div class="table-responsive">
                  <table class="table table-hover table-striped product-list-table align-middle">
                    <thead>
                      <tr>
                        <th style="width: 50px;">Ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th class="text-end">Giá</th>
                        <th class="text-end">Tồn kho</th>
                        <th class="text-center">Thao tác</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="product in paginatedProducts" :key="product.id" class="product-list-row">
                        <td>
                          <img
                                :src="product.image_url || 'https://placehold.co/50x50/e0e0e0/ffffff?text=No+Img'"
                                alt="Product Thumbnail"
                                class="img-fluid rounded-3"
                                style="width: 50px; height: 50px; object-fit: cover;"
                                onerror="this.onerror=null;this.src='https://placehold.co/50x50/e0e0e0/ffffff?text=No+Img';"
                          >
                        </td>
                        <td>
                          <h6 class="mb-0 text-primary">{{ product.name }}</h6>
                          <small class="text-muted" v-if="product.sku">SKU: {{ product.sku }}</small>
                        </td>
                        <!-- Display base_price using formatCurrency, defaulting to 0 if product.base_price is null/undefined -->
                        <td class="text-end fw-bold text-success">{{ formatCurrency(product.base_price || 0) }}</td>
                        <td class="text-end">
                          <!-- Display stock, defaulting to '0' if null/undefined, or 'N/A' if not tracked -->
                          <span v-if="product.track_stock" :class="{'text-danger': product.stock <= 5}">{{ product.stock === null || product.stock === undefined ? '0' : product.stock }}</span>
                          <span v-else class="text-muted">N/A</span>
                        </td>
                        <td class="text-center">
                          <button
                                class="btn btn-sm btn-outline-primary"
                                :disabled="product.track_stock && (product.stock === null || product.stock === undefined || product.stock <= 0)"
                                @click.stop="addToCart(product)"
                          >
                            <i class="fas fa-plus-circle"></i> Thêm
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-light d-flex flex-wrap align-items-center justify-content-between py-2 px-3">
            <!-- Product View Toggle moved to bottom-left -->
            <div class="d-flex align-items-center my-1">
                <div class="btn-group btn-group-sm me-2" role="group" aria-label="Product View Toggle">
                    <button type="button" class="btn" :class="{'btn-primary': productDisplayMode === 'grid', 'btn-outline-primary': productDisplayMode !== 'grid'}" @click="productDisplayMode = 'grid'">
                        <i class="fas fa-th-large"></i> Lưới
                    </button>
                    <button type="button" class="btn" :class="{'btn-primary': productDisplayMode === 'list', 'btn-outline-primary': productDisplayMode !== 'list'}" @click="productDisplayMode = 'list'">
                        <i class="fas fa-list"></i> Danh sách
                    </button>
                </div>
                <!-- Grid Columns Selector -->
                <div v-if="productDisplayMode === 'grid'" class="input-group input-group-sm" style="max-width: 120px;">
                    <label class="input-group-text d-none d-md-flex" for="gridCols">Cột</label>
                    <select class="form-select form-select-sm" id="gridCols" v-model="gridProductsPerRow">
                        <option :value="2">2</option>
                        <option :value="3">3</option>
                        <option :value="4">4</option>
                        <option :value="6">6</option>
                    </select>
                </div>
            </div>

            <!-- Pagination on the right -->
            <nav v-if="filteredProducts.length > perPageProducts" class="my-1 ms-auto">
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="{ disabled: currentProductPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="changeProductPage(currentProductPage - 1)">Trước</a>
                </li>
                <li class="page-item" v-for="page in productPaginationPages" :key="page" :class="{ active: currentProductPage === page }">
                  <a class="page-link" href="#" @click.prevent="changeProductPage(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: currentProductPage === lastProductPage }">
                  <a class="page-link" href="#" @click.prevent="changeProductPage(currentProductPage + 1)">Sau</a>
                </li>
              </ul>
            </nav>
            <span v-else class="text-muted small my-1">Hiển thị {{ filteredProducts.length }} sản phẩm</span>
          </div>
        </div>
      </div>

      <!-- Right Column: Order Cart & Payment -->
      <div class="col-lg-5">
        <div class="card h-100 shadow-sm">
          <div class="card-header bg-success text-white py-2 px-3">
            <h6 class="mb-0">Giỏ hàng và Thanh toán</h6>
          </div>
          <div class="card-body order-payment-body d-flex flex-column">
            <!-- Customer Selection & Search -->
            <div class="mb-3 p-2 border rounded bg-light">
              <label for="customerSearchInput" class="form-label mb-1 fw-bold">Khách hàng</label>
              <div class="input-group mb-2">
                <input
                  type="text"
                  class="form-control form-control-md"
                  id="customerSearchInput"
                  placeholder="Tìm khách hàng (tên, SĐT, email, địa chỉ)..."
                  v-model="customerSearchQuery"
                  @input="debounceCustomerSearch"
                  aria-label="Search Customer"
                >
                <button class="btn btn-outline-primary btn-md" type="button" @click="openNewCustomerModal">
                  <i class="fas fa-user-plus me-1"></i> Thêm mới
                </button>
              </div>

              <div v-if="selectedCustomerDisplayName" class="mb-2">
                <span class="badge bg-info p-2 fs-6">
                  <i class="fas fa-user me-1"></i> Đã chọn: {{ selectedCustomerDisplayName }}
                  <button type="button" class="btn-close btn-close-white ms-2" aria-label="Clear selected customer" @click="clearSelectedCustomer"></button>
                </span>
              </div>
              <div v-else>
                <span class="text-muted small">Khách vãng lai</span>
              </div>

              <div class="customer-search-results mt-2" v-if="customerSearchResults.length > 0 && customerSearchQuery">
                <ul class="list-group list-group-flush shadow-sm rounded">
                  <li
                    class="list-group-item list-group-item-action py-2"
                    v-for="customer in customerSearchResults"
                    :key="customer.id"
                    @click="selectCustomer(customer)"
                  >
                    <small class="fw-bold">{{ customer.name }}</small>
                    <small v-if="customer.phone" class="text-muted ms-2">({{ customer.phone }})</small>
                    <small v-if="customer.email" class="text-muted ms-2">({{ customer.email }})</small>
                    <small v-if="customer.address" class="text-muted d-block mt-1 small-address">Địa chỉ: {{ customer.address }}</small>
                  </li>
                </ul>
              </div>
            </div>


            <!-- Cart Items List (Invoice Style) -->
            <div class="cart-items-area flex-grow-1 mb-3 border rounded p-2 bg-light overflow-auto">
              <h6 class="mb-2 text-primary">Sản phẩm trong giỏ hàng:</h6>
              <div v-if="cart.length > 0" class="table-responsive">
                <table class="table cart-invoice-table align-middle">
                  <thead>
                    <tr>
                      <th style="width: 40%;">SP</th>
                      <th class="text-center" style="width: 20%;">SL</th>
                      <th class="text-end" style="width: 20%;">Đơn giá</th>
                      <th class="text-end" style="width: 20%;">Thành tiền</th>
                      <th class="text-center" style="width: 50px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, index) in cart" :key="item.product_id">
                      <td>
                        <div class="fw-bold">{{ item.product_name }}</div>
                        <textarea
                          v-model="item.modifiers_options_notes"
                          class="form-control form-control-sm mt-1"
                          rows="1"
                          placeholder="Ghi chú (modifier/tùy chọn)"
                          aria-label="Item Notes"
                        ></textarea>
                      </td>
                      <td class="text-center">
                        <input
                            type="number"
                            class="form-control form-control-sm text-center quantity-input-table"
                            v-model.number="item.quantity"
                            @change="enforceQuantityBounds(index)"
                            :step="item.sold_by_weight ? 0.01 : 1"
                            :min="item.sold_by_weight ? 0.01 : 1"
                            aria-label="Item Quantity"
                        >
                      </td>
                      <td class="text-end">{{ formatCurrency(item.unit_price) }}</td>
                      <td class="text-end fw-bold text-danger">{{ formatCurrency(item.subtotal) }}</td>
                      <td class="text-center">
                        <button @click="removeFromCart(index)" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="text-center py-4 text-muted">
                <i class="fas fa-shopping-cart fa-3x mb-2"></i>
                <p>Giỏ hàng trống.</p>
              </div>
            </div>

            <!-- Payment Summary -->
            <div class="payment-summary border rounded p-3 bg-white">
              <div class="d-flex justify-content-between mb-2 fs-5">
                <span>Tổng tiền hàng:</span>
                <span class="fw-bold text-primary">{{ formatCurrency(totalAmount) }}</span>
              </div>
              <div class="mb-3">
                <label for="paidAmount" class="form-label">Tiền khách đưa <span class="text-danger">*</span></label>
                <input
                  type="number"
                  class="form-control form-control-md"
                  id="paidAmount"
                  v-model.number="paidAmount"
                  @input="calculateChange"
                  min="0"
                  step="any"
                  aria-label="Paid Amount"
                >
                <div v-if="formErrors.paid_amount" class="text-danger small mt-1">{{ formErrors.paid_amount[0] }}</div>
              </div>
              <div class="d-flex justify-content-between mb-2 fs-5">
                <span>Tiền thừa:</span>
                <span class="fw-bold text-info">{{ formatCurrency(changeAmount) }}</span>
              </div>
              <div class="mb-3">
                <label for="paymentMethod" class="form-label">Phương thức thanh toán <span class="text-danger">*</span></label>
                <select class="form-select form-select-md" id="paymentMethod" v-model="paymentMethod" aria-label="Payment Method">
                  <option value="cash">Tiền mặt</option>
                  <option value="card">Thẻ tín dụng/ghi nợ</option>
                  <option value="transfer">Chuyển khoản</option>
                  <option value="momo">Momo (Ví điện tử)</option>
                  <option value="other">Khác</option>
                </select>
                <div v-if="paymentMethod !== 'cash'" class="mt-2">
                  <input type="text" class="form-control form-control-sm" placeholder="Mã giao dịch/ID thanh toán (nếu có)" v-model="transactionId">
                </div>
                <div v-if="formErrors.payment_method" class="text-danger small mt-1">{{ formErrors.payment_method[0] }}</div>
              </div>
              <div class="mb-3">
                <label for="orderNotes" class="form-label">Ghi chú đơn hàng</label>
                <textarea class="form-control" id="orderNotes" rows="2" v-model="orderNotes" aria-label="Order Notes"></textarea>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-3 d-grid gap-2">
              <button
                @click="placeOrder"
                class="btn btn-success btn-lg py-3 fw-bold"
                :disabled="cart.length === 0 || loadingOrder || paidAmount < totalAmount"
              >
                <span class="spinner-border spinner-border-sm me-2" v-if="loadingOrder"></span>
                <i class="fas fa-money-bill-wave me-2"></i> Hoàn tất đơn hàng
              </button>
              <button
                @click="clearOrder"
                class="btn btn-outline-secondary btn-lg py-2"
                :disabled="loadingOrder"
              >
                <i class="fas fa-undo me-2"></i> Hủy và làm mới
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- New Customer Modal -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1" aria-labelledby="newCustomerModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="newCustomerModalLabel">Thêm Khách hàng mới</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" @click="closeNewCustomerModal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="newCustomerName" class="form-label">Tên khách hàng <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="newCustomerName" v-model="newCustomer.name" :class="{'is-invalid': newCustomerErrors.name}">
              <div class="invalid-feedback" v-if="newCustomerErrors.name">{{ newCustomerErrors.name[0] }}</div>
            </div>
            <div class="mb-3">
              <label for="newCustomerPhone" class="form-label">Số điện thoại <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="newCustomerPhone" v-model="newCustomer.phone" :class="{'is-invalid': newCustomerErrors.phone}">
              <div class="invalid-feedback" v-if="newCustomerErrors.phone">{{ newCustomerErrors.phone[0] }}</div>
            </div>
            <div class="mb-3">
              <label for="newCustomerEmail" class="form-label">Email</label>
              <input type="email" class="form-control" id="newCustomerEmail" v-model="newCustomer.email" :class="{'is-invalid': newCustomerErrors.email}">
              <div class="invalid-feedback" v-if="newCustomerErrors.email">{{ newCustomerErrors.email[0] }}</div>
            </div>
            <div class="mb-3">
              <label for="newCustomerAddress" class="form-label">Địa chỉ</label>
              <input type="text" class="form-control" id="newCustomerAddress" v-model="newCustomer.address">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="closeNewCustomerModal">Hủy</button>
            <button type="button" class="btn btn-primary" @click="createNewCustomer" :disabled="loadingNewCustomer">
              <span class="spinner-border spinner-border-sm me-2" v-if="loadingNewCustomer"></span>
              Lưu khách hàng
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Hidden div for print receipt -->
    <div id="print-receipt" style="display: none;">
      <div class="receipt">
        <h4 class="text-center mb-2">CỬA HÀNG ABC</h4>
        <p class="text-center mb-3">Địa chỉ: 123 Đường XYZ, TP. HCM</p>
        <p class="text-center mb-3">Điện thoại: 0123 456 789</p>
        <h5 class="text-center mb-3 fw-bold">HÓA ĐƠN BÁN HÀNG</h5>
        <p><strong>Mã ĐH:</strong> #{{ lastCreatedOrder?.id }}</p>
        <p><strong>Ngày:</strong> {{ lastCreatedOrder ? formatDate(lastCreatedOrder.created_at) : '' }}</p>
        <p v-if="lastCreatedOrder && lastCreatedOrder.customer"><strong>Khách hàng:</strong> {{ lastCreatedOrder.customer.name }}</p>
        <p v-else><strong>Khách hàng:</strong> Khách vãng lai</p>
        <hr class="my-2">
        <table class="receipt-items-table">
          <thead>
            <tr>
              <th>SP</th>
              <th class="text-end">SL</th>
              <th class="text-end">ĐG</th>
              <th class="text-end">TT</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in lastCreatedOrder?.order_items" :key="item.id">
              <td>
                {{ item.product_name_at_time_of_order || (item.product ? item.product.name : 'Sản phẩm đã xóa') }}
                <span v-if="item.modifiers_options_notes" class="d-block small text-muted">({{ item.modifiers_options_notes }})</span>
              </td>
              <td class="text-end">{{ item.quantity }}</td>
              <td class="text-end">{{ formatCurrency(item.unit_price) }}</td>
              <td class="text-end">{{ formatCurrency(item.subtotal) }}</td>
            </tr>
          </tbody>
        </table>
        <hr class="my-2">
        <div class="d-flex justify-content-between fw-bold">
          <span>TỔNG CỘNG:</span>
          <span>{{ formatCurrency(lastCreatedOrder?.total_amount) }}</span>
        </div>
        <div class="d-flex justify-content-between">
          <span>Tiền khách trả:</span>
          <span>{{ formatCurrency(lastCreatedOrder?.paid_amount) }}</span>
        </div>
        <div class="d-flex justify-content-between">
          <span>Tiền thừa:</span>
          <span>{{ formatCurrency(lastCreatedOrder?.change_amount) }}</span>
        </div>
        <p><strong>P.thức TT:</strong> {{ lastCreatedOrder ? getPaymentMethodDisplay(lastCreatedOrder.payment_method) : '' }}</p>
        <p v-if="lastCreatedOrder?.notes"><strong>Ghi chú:</strong> {{ lastCreatedOrder.notes }}</p>
        <hr class="my-2">
        <p class="text-center mt-3">Cảm ơn quý khách và hẹn gặp lại!</p>
        <p class="text-center small">{{ formatDate(new Date(), true) }}</p>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'; // Import computed
import axios from 'axios';
import _ from 'lodash';
import { Modal, Toast } from 'bootstrap'; // Import Bootstrap's Modal and Toast

// Sử dụng window.location.origin cho URL tuyệt đối để đảm bảo các cuộc gọi API chính xác
// Đặt BASE URL là '/api' để gọi các route như /api/customers, /api/orders
const API_BASE_URL = window.location.origin + '/api';

// Reactive state variables using ref
const allProducts = ref([]);
const productSearchQuery = ref('');
const barcodeInput = ref('');
const currentProductPage = ref(1);
const perPageProducts = ref(9);
const loadingProducts = ref(false);
const productDisplayMode = ref('grid');
const gridProductsPerRow = ref(3);

const allCustomers = ref([]);
const customerSearchQuery = ref('');
const customerSearchResults = ref([]);
const selectedCustomer = ref(null);
const selectedCustomerDisplayName = ref('');
const loadingNewCustomer = ref(false);

const newCustomerModal = ref(null); // Ref to hold Bootstrap Modal instance
const newCustomer = ref({
  name: '',
  phone: '',
  email: '',
  address: ''
});
const newCustomerErrors = ref({});

const cart = ref([]);

const paidAmount = ref(0);
const changeAmount = ref(0);
const paymentMethod = ref('cash');
const transactionId = ref('');
const orderNotes = ref('');

const loadingOrder = ref(false);
const formErrors = ref({});
const lastCreatedOrder = ref(null);
const selectedBranchId = ref(1); // Default branch ID, assuming 1 for initial load

// Computed properties
const filteredProducts = computed(() => {
  if (!productSearchQuery.value) {
    return allProducts.value;
  }
  const lowerCaseQuery = productSearchQuery.value.toLowerCase();
  return allProducts.value.filter(product =>
    product.name.toLowerCase().includes(lowerCaseQuery) ||
    (product.sku && product.sku.toLowerCase().includes(lowerCaseQuery))
  );
});

const paginatedProducts = computed(() => {
  const startIndex = (currentProductPage.value - 1) * perPageProducts.value;
  const endIndex = startIndex + perPageProducts.value;
  return filteredProducts.value.slice(startIndex, endIndex);
});

const lastProductPage = computed(() => {
  return Math.ceil(filteredProducts.value.length / perPageProducts.value);
});

const productPaginationPages = computed(() => {
  const pages = [];
  const startPage = Math.max(1, currentProductPage.value - 1);
  const endPage = Math.min(lastProductPage.value, currentProductPage.value + 1);

  for (let i = startPage; i <= endPage; i++) {
    pages.push(i);
  }
  if (lastProductPage.value > 1 && !pages.includes(1)) pages.unshift(1);
  if (lastProductPage.value > 1 && !pages.includes(lastProductPage.value)) pages.push(lastProductPage.value);
  return pages.sort((a, b) => a - b);
});

const gridColumnClass = computed(() => {
  switch (gridProductsPerRow.value) {
    case 2: return 'col-md-6 col-sm-6 col-12';
    case 3: return 'col-md-4 col-sm-6 col-12';
    case 4: return 'col-md-3 col-sm-6 col-12';
    case 6: return 'col-md-2 col-sm-4 col-12';
    default: return 'col-md-4 col-sm-6 col-12';
  }
});

const totalAmount = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.subtotal, 0);
});

// Watchers
watch(selectedBranchId, () => {
  fetchProducts();
});

watch(cart, (newCart) => {
  newCart.forEach(item => {
    item.quantity = Math.max(item.sold_by_weight ? 0.01 : 1, parseFloat(item.quantity) || (item.sold_by_weight ? 0.01 : 1));
    if (item.sold_by_weight) {
      item.quantity = parseFloat(item.quantity.toFixed(2));
    }
    item.subtotal = parseFloat((item.quantity * item.unit_price).toFixed(2));
  });
  calculateChange();
}, { deep: true });

watch(paidAmount, (newVal) => {
  if (newVal === null || isNaN(newVal)) {
    paidAmount.value = 0;
  }
  calculateChange();
});

watch(productSearchQuery, () => {
  currentProductPage.value = 1;
});

watch(customerSearchQuery, _.debounce((newQuery) => {
  filterCustomers(newQuery);
}, 300));

// Lifecycle Hooks
onMounted(() => {
  fetchProducts();
  fetchCustomers();
  calculateChange();
  newCustomerModal.value = new Modal(document.getElementById('newCustomerModal'));
});

// Methods
const fetchProducts = async () => {
  loadingProducts.value = true;
  try {
    showToast('', 'transparent'); // Clear any previous toast by showing a transparent one

    // Calling /api/banhang/sales-products as per routes/api.php
    const response = await axios.get(`${API_BASE_URL}/banhang/sales-products`, {
      params: {
        search: productSearchQuery.value,
        branch_id: selectedBranchId.value,
        active_only: true
      }
    });

    console.log("Dữ liệu Sản phẩm gốc nhận được từ API (SalesProductController):", response.data);

    allProducts.value = response.data.map(product => ({
      ...product,
      base_price: parseFloat(product.base_price) || 0,
      stock: parseFloat(product.stock) || 0, // Ensure 'stock' is parsed correctly
      track_stock: !!product.track_stock,
      sold_by_weight: !!product.sold_by_weight
    }));

    console.log("Dữ liệu Sản phẩm sau khi Frontend xử lý (standardized):", allProducts.value);
    changeProductPage(1);
  } catch (error) {
    console.error('Lỗi khi tải danh sách sản phẩm cho POS:', error);
    showToast('Lỗi khi tải danh sách sản phẩm cho POS.', 'danger');
    allProducts.value = [];
  } finally {
    loadingProducts.value = false;
  }
};

const debounceProductSearch = _.debounce(() => {
  fetchProducts();
}, 500);

const changeProductPage = (page) => {
  if (page >= 1 && page <= lastProductPage.value) {
    currentProductPage.value = page;
  }
};

const handleBarcodeScan = () => {
  if (!barcodeInput.value) return;

  const product = allProducts.value.find(p => p.sku === barcodeInput.value);
  if (product) {
    addToCart(product);
    barcodeInput.value = '';
  } else {
    showToast(`Không tìm thấy sản phẩm với mã vạch: ${barcodeInput.value}`, 'warning');
  }
  barcodeInput.value = '';
};

const fetchCustomers = async (searchParams = {}) => {
  try {
    // Calling /api/customers as per routes/api.php
    const response = await axios.get(`${API_BASE_URL}/customers`, {
        params: { ...searchParams, active_only: true }
    });
    allCustomers.value = response.data;
    filterCustomers(customerSearchQuery.value);
  } catch (error) {
    console.error('Lỗi khi tải danh sách khách hàng:', error);
    showToast('Lỗi khi tải danh sách khách hàng.', 'danger');
  }
};

const filterCustomers = (query) => {
  if (!query) {
    customerSearchResults.value = [];
    return;
  }
  const lowerCaseQuery = query.toLowerCase();
  customerSearchResults.value = allCustomers.value.filter(customer =>
    customer.name.toLowerCase().includes(lowerCaseQuery) ||
    (customer.phone && customer.phone.includes(lowerCaseQuery)) ||
    (customer.email && customer.email.toLowerCase().includes(lowerCaseQuery)) ||
    (customer.address && customer.address.toLowerCase().includes(lowerCaseQuery))
  ).slice(0, 5);
};

const selectCustomer = (customer) => {
  selectedCustomer.value = customer;
  selectedCustomerDisplayName.value = `${customer.name} ${customer.phone ? `(${customer.phone})` : ''}`;
  customerSearchQuery.value = '';
  customerSearchResults.value = [];
  showToast(`Đã chọn khách hàng: ${selectedCustomerDisplayName.value}`, 'info');
};

const clearSelectedCustomer = () => {
  selectedCustomer.value = null;
  selectedCustomerDisplayName.value = '';
  showToast('Đã bỏ chọn khách hàng.', 'info');
};

const openNewCustomerModal = () => {
  newCustomerErrors.value = {};
  newCustomer.value = { name: '', phone: '', email: '', address: '' };
  newCustomerModal.value.show();
};

const closeNewCustomerModal = () => {
  newCustomerModal.value.hide();
  newCustomerErrors.value = {};
  newCustomer.value = { name: '', phone: '', email: '', address: '' };
};

const createNewCustomer = async () => {
  loadingNewCustomer.value = true;
  newCustomerErrors.value = {};

  try {
    // Calling /api/customers as per routes/api.php
    const response = await axios.post(`${API_BASE_URL}/customers`, newCustomer.value);
    showToast('Đã thêm khách hàng mới thành công!', 'success');
    fetchCustomers();
    selectCustomer(response.data.customer);
    closeNewCustomerModal();
  } catch (error) {
    console.error('Lỗi khi tạo khách hàng mới:', error);
    if (error.response && error.response.status === 422) {
      newCustomerErrors.value = error.response.data.errors || {};
      showToast('Lỗi xác thực. Vui lòng kiểm tra lại thông tin khách hàng.', 'danger');
    } else if (error.response && error.response.data && error.response.data.message) {
      showToast(`Lỗi: ${error.response.data.message}`, 'danger');
    } else {
      showToast('Đã xảy ra lỗi khi thêm khách hàng mới.', 'danger');
    }
  } finally {
    loadingNewCustomer.value = false;
  }
};

const addToCart = (product) => {
  showToast('', 'transparent'); // Clear any previous toast

  const productInAllProductsIndex = allProducts.value.findIndex(p => p.id === product.id);
  if (productInAllProductsIndex === -1) {
    showToast(`Sản phẩm ${product.name} không khả dụng.`, 'danger');
    return;
  }
  const productInAllProducts = allProducts.value[productInAllProductsIndex];

  const existingItemIndex = cart.value.findIndex(item => item.product_id === product.id);

  const quantityToAdd = product.sold_by_weight ? 0.01 : 1;

  if (existingItemIndex > -1) {
    // Product already in cart, increment quantity
    const existingItem = cart.value[existingItemIndex];
    const currentTotalInCart = existingItem.quantity;
    let newDesiredQuantity = currentTotalInCart + quantityToAdd;

    if (productInAllProducts.track_stock) {
      if (newDesiredQuantity > productInAllProducts.stock + currentTotalInCart) { // Check against original available stock + current in cart
          const maxAddable = parseFloat((productInAllProducts.stock).toFixed(2));
          if (maxAddable <= 0.00) {
              showToast(`Sản phẩm "${product.name}" đã hết hàng.`, 'warning');
              return;
          }
          // Only add up to remaining available stock
          newDesiredQuantity = currentTotalInCart + maxAddable;
          showToast(`Không đủ tồn kho cho "${product.name}". Đã thêm tối đa ${maxAddable}.`, 'warning');
      }
      // Update stock in allProducts directly
      const actualQuantityAdded = newDesiredQuantity - currentTotalInCart;
      allProducts.value[productInAllProductsIndex].stock = parseFloat((productInAllProducts.stock - actualQuantityAdded).toFixed(2));
    }
    existingItem.quantity = product.sold_by_weight ? parseFloat(newDesiredQuantity.toFixed(2)) : newDesiredQuantity;
    existingItem.subtotal = parseFloat((existingItem.quantity * existingItem.unit_price).toFixed(2));
    showToast(`Đã tăng số lượng "${product.name}" trong giỏ hàng lên ${existingItem.quantity}.`, 'success');

  } else {
    // Product not in cart, add new item
    if (productInAllProducts.track_stock) {
      if (productInAllProducts.stock < quantityToAdd) {
        showToast(`Sản phẩm "${product.name}" không đủ số lượng tối thiểu (${quantityToAdd}). Tồn kho hiện có: ${productInAllProducts.stock}.`, 'warning');
        return;
      }
      // Update stock in allProducts directly
      allProducts.value[productInAllProductsIndex].stock = parseFloat((productInAllProducts.stock - quantityToAdd).toFixed(2));
    }
    const initialQuantity = product.sold_by_weight ? 0.01 : 1;
    cart.value.push({
      product_id: product.id,
      product_name: product.name,
      quantity: initialQuantity,
      unit_price: product.base_price,
      subtotal: parseFloat((product.base_price * initialQuantity).toFixed(2)),
      modifiers_options_notes: '',
      track_stock: product.track_stock,
      sold_by_weight: product.sold_by_weight,
    });
    showToast(`Đã thêm "${product.name}" vào giỏ hàng.`, 'success');
  }
  calculateChange();
};

const removeFromCart = (index) => {
  const removedItems = cart.value.splice(index, 1); // splice returns an array
  if (removedItems.length > 0) {
    const product = removedItems[0];
    if (product.track_stock) {
        const productInAllProductsIndex = allProducts.value.findIndex(p => p.id === product.product_id);
        if (productInAllProductsIndex !== -1) {
            const currentStock = allProducts.value[productInAllProductsIndex].stock;
            // Update stock in allProducts directly
            allProducts.value[productInAllProductsIndex].stock = parseFloat((currentStock + product.quantity).toFixed(2));
        }
    }
    showToast(`Đã xóa "${product.product_name}" khỏi giỏ hàng.`, 'info');
  }
  calculateChange();
};

const enforceQuantityBounds = (index) => {
  let item = cart.value[index];
  if (!item) return;

  const productInAllProductsIndex = allProducts.value.findIndex(p => p.id === item.product_id);
  if (productInAllProductsIndex === -1) {
    removeFromCart(index);
    showToast(`Sản phẩm "${item.product_name}" không còn tồn tại, đã xóa khỏi giỏ hàng.`, 'warning');
    return;
  }
  const productInAllProducts = allProducts.value[productInAllProductsIndex];

  const minQty = item.sold_by_weight ? 0.01 : 1;
  let newQuantity = parseFloat(item.quantity);
  let oldQuantity = item.quantity;

  if (isNaN(newQuantity) || newQuantity < minQty) {
    newQuantity = minQty;
    showToast(`Số lượng "${item.product_name}" không được nhỏ hơn ${minQty}. Đã điều chỉnh.`, 'warning');
  }

  if (item.sold_by_weight) {
    newQuantity = parseFloat(newQuantity.toFixed(2));
  }

  // Before adjusting stock, calculate the total available stock if this item was completely removed
  // This is productInAllProducts.stock (current UI stock) + oldQuantity (what was in cart)
  const totalAvailableForThisProduct = parseFloat((productInAllProducts.stock + oldQuantity).toFixed(2));

  if (item.track_stock) {
    if (newQuantity > totalAvailableForThisProduct) {
      newQuantity = totalAvailableForThisProduct; // Cap at max available
      showToast(`Số lượng "${item.product_name}" vượt quá tồn kho hiện có. Đã điều chỉnh.`, 'warning');
    }
  }
  
  const finalQuantityDifference = newQuantity - oldQuantity;
  if (item.track_stock && finalQuantityDifference !== 0) {
    // Update stock in allProducts directly
    allProducts.value[productInAllProductsIndex].stock = parseFloat((productInAllProducts.stock - finalQuantityDifference).toFixed(2));
  }

  item.quantity = newQuantity;
  item.subtotal = parseFloat((item.quantity * item.unit_price).toFixed(2));
};

const calculateChange = () => {
  const paid = parseFloat(paidAmount.value) || 0;
  changeAmount.value = paid - totalAmount.value;
};

const placeOrder = async () => {
  loadingOrder.value = true;
  formErrors.value = {};
  showToast('', 'transparent'); // Clear any previous toast

  if (cart.value.length === 0) {
    showToast('Giỏ hàng trống. Vui lòng chọn sản phẩm.', 'warning');
    loadingOrder.value = false;
    return;
  }
  if (paidAmount.value < totalAmount.value) {
    showToast('Số tiền khách đưa không đủ để thanh toán.', 'warning');
    loadingOrder.value = false;
    return;
  }
  if (!paymentMethod.value) {
    showToast('Vui lòng chọn phương thức thanh toán.', 'warning');
    loadingOrder.value = false;
    return;
  }

  const orderItemsPayload = cart.value.map(item => ({
    product_id: item.product_id,
    quantity: item.quantity,
    unit_price: item.unit_price,
    modifiers_options_notes: item.modifiers_options_notes,
  }));

  const payload = {
    branch_id: selectedBranchId.value,
    customer_id: selectedCustomer.value ? selectedCustomer.value.id : null,
    total_amount: totalAmount.value,
    paid_amount: paidAmount.value,
    payment_method: paymentMethod.value,
    status: 'completed', // Default to completed for POS orders
    notes: orderNotes.value + (transactionId.value ? ` (Mã GD: ${transactionId.value})` : ''),
    items: orderItemsPayload,
  };

  try {
    // Calling /api/orders as per routes/api.php
    const response = await axios.post(`${API_BASE_URL}/orders`, payload);
    showToast('Đơn hàng đã được tạo thành công!', 'success');
    lastCreatedOrder.value = response.data.order;
    console.log('Đơn hàng đã được tạo:', lastCreatedOrder.value);
    clearOrder(false);
    // fetchProducts() is called by clearOrder()
    
    setTimeout(() => {
      promptPrintReceipt();
    }, 500);

  } catch (error) {
    console.error('Lỗi khi hoàn tất đơn hàng:', error);
    if (error.response && error.response.status === 422) {
      formErrors.value = error.response.data.errors || {};
      let errorMessage = 'Lỗi xác thực dữ liệu. Vui lòng kiểm tra lại thông tin.';
      if (error.response.data.message) {
        errorMessage += ` ${error.response.data.message}`;
      }
      if (error.response.data.validation_errors) {
          const validationMessages = [];
          if (Array.isArray(error.response.data.validation_errors)) {
            error.response.data.validation_errors.forEach(err => {
              if (typeof err === 'string') {
                validationMessages.push(err);
              } else if (typeof err === 'object') {
                for (const key in err) {
                  if (Array.isArray(err[key])) {
                    err[key].forEach(nestedErr => {
                      if (typeof nestedErr === 'string') {
                        validationMessages.push(nestedErr);
                      }
                    });
                  }
                }
              }
            });
          } else if (typeof error.response.data.validation_errors === 'object') {
             for (const key in error.response.data.validation_errors) {
                if (Array.isArray(error.response.data.validation_errors[key])) {
                    error.response.data.validation_errors[key].forEach(nestedErr => {
                        if (typeof nestedErr === 'string') {
                            validationMessages.push(nestedErr);
                        }
                    });
                }
             }
          }
          if (validationMessages.length > 0) {
              errorMessage = `Lỗi: ${validationMessages.join('. ')}`;
          }
      } else if (error.response.data.product_name && typeof error.response.data.remaining_stock !== 'undefined') {
        // Specific stock error from OrderController might be here
        errorMessage = `Lỗi: Không đủ tồn kho cho sản phẩm "${error.response.data.product_name}". Còn lại: ${error.response.data.remaining_stock}`;
      }

      showToast(errorMessage, 'danger');
    } else if (error.response && error.response.data && error.response.data.message) {
      showToast(`Lỗi: ${error.response.data.message}`, 'danger');
    } else {
      showToast('Đã xảy ra lỗi khi hoàn tất đơn hàng. Vui lòng thử lại.', 'danger');
    }
    // If order submission failed, re-fetch products to restore stock to original state from backend
    fetchProducts();
  } finally {
    loadingOrder.value = false;
  }
};

const clearOrder = (resetLastOrder = true) => {
  cart.value = [];
  selectedCustomer.value = null;
  selectedCustomerDisplayName.value = '';
  customerSearchQuery.value = '';
  customerSearchResults.value = [];
  paidAmount.value = 0;
  changeAmount.value = 0;
  paymentMethod.value = 'cash';
  transactionId.value = '';
  orderNotes.value = '';
  formErrors.value = {};
  if (resetLastOrder) {
    lastCreatedOrder.value = null;
  }
  showToast('Đơn hàng đã được làm mới.', 'info');
  // When clearing the order, reset product stock in UI to fetched state
  fetchProducts(); 
};

const promptPrintReceipt = () => {
  if (!lastCreatedOrder.value) {
    showToast('Không có đơn hàng nào để in.', 'warning');
    return;
  }
  showPrintConfirmToast();
};

const showPrintConfirmToast = () => {
  let toastContainer = document.querySelector('.toast-container');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container position-fixed p-3 bottom-center';
    toastContainer.style.zIndex = '1050';
    document.body.appendChild(toastContainer);
  } else {
    toastContainer.style.top = '';
    toastContainer.style.right = '';
    toastContainer.style.bottom = '2rem';
    toastContainer.style.left = '50%';
    toastContainer.style.transform = 'translateX(-50%)';
  }

  const toastEl = document.createElement('div');
  toastEl.className = `toast align-items-center text-white bg-primary border-0 show`;
  toastEl.setAttribute('role', 'alert');
  toastEl.setAttribute('aria-live', 'assertive');
  toastEl.setAttribute('aria-atomic', 'true');

  toastEl.innerHTML = `
    <div class="d-flex p-2">
      <div class="toast-body flex-grow-1">
        Đơn hàng đã được tạo thành công. Bạn có muốn in hóa đơn không?
      </div>
      <button type="button" class="btn btn-sm btn-light me-2 print-yes">In hóa đơn</button>
      <button type="button" class="btn btn-sm btn-outline-light print-no" data-bs-dismiss="toast">Không</button>
    </div>
  `;

  toastContainer.appendChild(toastEl);
  // Use Bootstrap's Toast class for the print confirmation as well
  const toast = new Toast(toastEl, {
    autohide: false // Don't autohide, wait for user action
  });
  toast.show();


  toastEl.querySelector('.print-yes').addEventListener('click', () => {
    printReceipt();
    toast.hide(); // Hide the toast after action
  });

  toastEl.querySelector('.print-no').addEventListener('click', () => {
    toast.hide(); // Hide the toast after action
  });

  // Listener to remove toast element from DOM after it's hidden
  toastEl.addEventListener('hidden.bs.toast', () => {
    toastEl.remove();
    if (toastContainer.children.length === 0) {
      toastContainer.remove(); // Remove container if no more toasts
    }
  });
};


const printReceipt = () => {
  if (!lastCreatedOrder.value) {
    showToast('Không có đơn hàng nào để in.', 'warning');
    return;
  }

  const printWindow = window.open('', '_blank', 'height=600,width=400');
  if (!printWindow) {
      showToast('Vui lòng cho phép cửa sổ bật lên để in hóa đơn.', 'danger');
      return;
  }

  const receiptContent = document.getElementById('print-receipt').innerHTML;
  const printHtml = `
    <html>
    <head>
        <title>Hóa đơn bán hàng</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; margin: 15px; font-size: 14px; color: #333; }
            .receipt { width: 100%; max-width: 300px; margin: 0 auto; padding: 10px; border: 1px dashed #ccc; }
            .receipt h4, .receipt h5 { margin-bottom: 5px; }
            .receipt hr { border-top: 1px dashed #ccc; margin: 10px 0; }
            .receipt-items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            .receipt-items-table th, .receipt-items-table td { padding: 4px 0; border: 1px solid #ccc; }
            .receipt-items-table th { text-align: left; }
            .receipt-items-table td { text-align: right; vertical-align: top; }
            .receipt-items-table td:first-child { text-align: left; }
            .d-flex { display: flex; justify-content: space-between; margin-bottom: 5px; }
            .fw-bold { font-weight: bold; }
            .text-center { text-align: center; }
            .text-end { text-align: right; }
            .small { font-size: 0.85em; }
            @media print {
                body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .receipt { border: none !important; }
            }
        </style>
    </head>
    <body>
        ${receiptContent}
    </body>
    </html>
  `;

  printWindow.document.open();
  printWindow.document.write(printHtml);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
};

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '0 ₫';
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(value);
};

const formatDate = (dateInput, includeTime = true) => {
    if (!dateInput) return '-';
    const date = new Date(dateInput);
    const options = {
        year: 'numeric', month: '2-digit', day: '2-digit'
    };
    if (includeTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
        options.second = '2-digit';
    }
    return date.toLocaleDateString('vi-VN', options) + (includeTime ? ' ' + date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '');
};

const getPaymentMethodDisplay = (methodCode) => {
    switch (methodCode) {
        case 'cash': return 'Tiền mặt';
        case 'card': return 'Thẻ tín dụng/ghi nợ';
        case 'transfer': return 'Chuyển khoản';
        case 'momo': return 'Momo';
        case 'other': return 'Khác';
        default: return methodCode;
    }
};

const showToast = (messageText, variant = 'success', position = 'bottom-right', delay = 3000) => {
  let toastContainer = document.querySelector('.toast-container');

  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container position-fixed p-3';
    toastContainer.style.zIndex = '1050';
    document.body.appendChild(toastContainer);
  }

  // Reset positioning styles to apply new position
  toastContainer.style.top = '';
  toastContainer.style.right = '';
  toastContainer.style.bottom = '';
  toastContainer.style.left = '';
  toastContainer.style.transform = '';

  const offset = '2rem'; // Consistent offset from edges

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
    case 'bottom-right':
    default:
      toastContainer.style.bottom = offset;
      toastContainer.style.right = offset;
      break;
  }

  // Create a new toast element
  const toastEl = document.createElement('div');
  toastEl.className = `toast align-items-center text-white bg-${variant} border-0`;
  toastEl.setAttribute('role', 'alert');
  toastEl.setAttribute('aria-live', 'assertive');
  toastEl.setAttribute('aria-atomic', 'true');

  toastEl.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">
        ${messageText}
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  `;

  // Append the toast element to the container
  toastContainer.appendChild(toastEl);

  // Initialize and show the Bootstrap Toast
  const toast = new Toast(toastEl, {
    autohide: true,
    delay: delay // Use the passed delay or default to 3000ms
  });
  toast.show();

  // Remove the toast element from the DOM after it's hidden
  toastEl.addEventListener('hidden.bs.toast', () => {
    toastEl.remove();
    if (toastContainer.children.length === 0) {
      toastContainer.remove(); // Remove container if no more toasts
    }
  });
};
</script>

<style scoped>
/* Scoped styles for this component, if any, go here.
   Tailwind CSS is primarily used for styling. */
.new-order {
  min-height: calc(100vh - 40px); /* Adjust based on your header/footer if any */
  font-family: 'Inter', sans-serif; /* Use Inter font */
}

.card {
  border-radius: 0.75rem;
  border: none; /* No default card border */
}

.card-header {
  border-bottom: none; /* No default header border */
  padding: 0.75rem 1rem; /* Smaller padding for header */
}

.card-header h6 {
  font-size: 1rem; /* Smaller font for header title */
}

/* Base sizes for form controls */
.form-control-sm, .btn-sm, .form-select-sm {
  height: calc(2.5rem + 2px);
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
  border-radius: 0.3rem;
}

.form-control-md, .btn-md, .form-select-md {
  height: calc(3rem + 2px);
  padding: 0.5rem 1rem;
  font-size: 1rem;
  border-radius: 0.4rem;
}

.form-control-lg, .btn-lg, .form-select-lg {
  height: calc(3.5rem + 2px);
  padding: 0.75rem 1.25rem;
  font-size: 1.2rem;
  border-radius: 0.5rem;
}

.product-list-area {
  max-height: 45vh; /* Adjusted height */
  overflow-y: auto;
  padding-right: 15px; /* For scrollbar space */
}

.product-card {
  transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  cursor: pointer;
  border: 1px solid #dee2e6; /* Light border */
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); /* Subtle shadow */
  background-color: #fff;
}

.product-card:hover {
  transform: translateY(-3px); /* Slightly less lift on hover */
  box-shadow: 0 0.4rem 0.8rem rgba(0, 0, 0, 0.1) !important; /* Lighter shadow */
}

.product-image {
  height: 120px; /* Fixed height for consistent image display */
  object-fit: cover;
  background-color: #f8f9fa;
  border-top-left-radius: 0.75rem;
  border-top-right-radius: 0.75rem;
}

.cart-items-area {
  max-height: 40vh; /* Adjust as needed */
  min-height: 200px;
  background-color: #f8f9fa; /* Light background for cart */
}

/* Styles for the new cart table (invoice style) */
.cart-invoice-table {
  width: 100%;
  margin-bottom: 0;
  border-collapse: collapse; /* Ensure borders are collapsed */
}

.cart-invoice-table th,
.cart-invoice-table td {
  padding: 0.5rem 0.75rem;
  vertical-align: middle;
  border: 1px solid #dee2e6; /* All cells have borders */
}

.cart-invoice-table thead th {
  background-color: #e9ecef;
  font-weight: 600;
  border-bottom: 1px solid #dee2e6;
  border-top: none; /* No top border for header for cleaner look */
}
.cart-invoice-table tbody tr:first-child td {
  border-top: none; /* No top border for first data row for cleaner look */
}

.quantity-input-table {
  width: 100%; /* Make input fill cell */
  max-width: 80px; /* Max width for quantity input in table */
  font-size: 0.9rem; /* Slightly smaller font for quantity in table */
  padding: 0.2rem 0.4rem;
  text-align: center;
}


.list-group-item {
  border-bottom: 1px dashed #ced4da; /* Dashed border for list items */
}

.list-group-item:last-child {
  border-bottom: none;
}

.payment-summary {
  background-color: #e9ecef; /* Slightly darker background for summary */
  border: 1px solid #dee2e6;
}

.customer-search-results {
  max-height: 150px; /* Limit height of search results dropdown */
  overflow-y: auto;
  position: relative; /* Ensure it respects z-index if needed for pop-over */
}

.customer-search-results .list-group-item {
  cursor: pointer;
}

.customer-search-results .list-group-item:hover {
  background-color: #e9ecef;
}

/* Styles for Product List View */
.product-list-table {
  width: 100%;
  margin-bottom: 0;
}

.product-list-table th,
.product-list-table td {
  padding: 0.5rem 0.75rem;
  vertical-align: middle;
}

.product-list-table th {
  background-color: #e9ecef;
  font-weight: 600;
  border-bottom: 1px solid #dee2e6;
}

.product-list-table tbody tr {
  cursor: pointer;
}

.product-list-table tbody tr:hover {
  background-color: #f2f2f2;
}

/* Custom styles for text size in Order Cart & Payment section */
.order-payment-body {
  font-size: 0.9rem; /* Smaller base font size for the right column's content */
}

.order-payment-body label {
  font-size: 0.95rem; /* Labels slightly larger than body text but smaller than section titles */
}

/* Price and change amount should still be prominent */
.payment-summary .fs-5 {
  font-size: 1.15rem !important; /* Slightly smaller than original fs-5, but still clear */
}


/* Ensure toast container z-index is high */
.toast-container {
  z-index: 1080 !important;
}

/* Print specific styles for receipt */
@media print {
  body * {
    visibility: hidden;
  }
  #print-receipt, #print-receipt * {
    visibility: visible;
  }
  #print-receipt {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: auto;
    margin: 0;
    padding: 0;
    font-size: 12px; /* Smaller font for receipt */
  }
  .receipt {
    width: 80mm; /* Standard receipt width, adjust as needed */
    margin: 0 auto;
    padding: 5mm;
    border: none !important;
    box-shadow: none !important;
  }
  .receipt-items-table th, .receipt-items-table td {
    font-size: 12px;
    padding: 2px 0;
    border: 1px solid #ccc; /* Ensure borders are present in print */
  }
  .d-flex {
    margin-bottom: 2px;
  }
  .mb-2, .mb-3, .mt-3 {
    margin-bottom: 5px !important; /* Reduce margins for print */
    margin-top: 5px !important;
  }
  hr {
    margin: 5px 0 !important;
  }
}
</style>
