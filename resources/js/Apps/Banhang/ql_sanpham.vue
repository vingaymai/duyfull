<template>
  <div class="product-management">
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Quản lý Sản phẩm</h5>
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

      <div class="card-body">
        <div class="row mb-3 align-items-center">
          <div class="col-md-4">
            <div class="input-group">
              <input
                type="text"
                class="form-control"
                placeholder="Tìm kiếm sản phẩm theo tên, SKU, mã vạch..."
                v-model="searchQuery"
                @input="debounceSearch"
              >
              <button class="btn btn-outline-secondary" type="button" @click="applySearchAndPagination(1)">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
          <div class="col-md-4">
            <div class="input-group">
              <label class="input-group-text" for="categoryFilter">Danh mục</label>
              <select class="form-select" id="categoryFilter" v-model="selectedCategoryId" @change="fetchAndFilterProducts">
                <option :value="null">Tất cả danh mục</option>
                <option
                  v-for="cat in hierarchicalCategories"
                  :value="cat.id"
                  :key="cat.id"
                  :style="{'padding-left': (cat.level * 20) + 'px'}"
                >
                  <span v-if="cat.level > 0">--- </span>{{ cat.name }}
                </option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="input-group">
              <label class="input-group-text" for="branchFilter">Chi nhánh</label>
              <select class="form-select" id="branchFilter" v-model="selectedBranchId" @change="fetchAndFilterProducts">
                <option :value="null">Tất cả chi nhánh</option>
                <option
                  v-for="branch in allBranches"
                  :value="branch.id"
                  :key="branch.id"
                >
                  {{ branch.name }}
                </option>
              </select>
            </div>
          </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6 offset-md-6"> <div class="input-group">
                    <input
                        type="text"
                        class="form-control"
                        placeholder="Quét hoặc nhập mã vạch nhanh..."
                        v-model="scannedBarcode"
                        @keyup.enter="handleBarcodeScan"
                        ref="barcodeScannerInput"
                    >
                    <button class="btn btn-outline-primary" type="button" @click="handleBarcodeScan">
                        <i class="fas fa-barcode"></i>
                    </button>
                </div>
                <small class="form-text text-muted">Nhấn Enter sau khi nhập/quét mã vạch.</small>
            </div>
        </div>

        <div class="table-responsive table-scroll-area">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th width="60">STT</th>
                <th width="80">Ảnh</th>
                <th>Tên sản phẩm</th>
                <th>Danh mục</th>
                <th>SKU</th>
                <th>Mã vạch</th>
                <th>Giá gốc</th>
                <th>Đơn vị</th>
                <th>Tồn kho</th>
                <th>Trạng thái</th>
                <th width="150">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="productLoading">
                <td colspan="11" class="text-center py-5">
                  <div class="d-flex justify-content-center align-items-center">
                    <div class="spinner-border text-primary me-2" role="status">
                      <span class="visually-hidden">Đang tải...</span>
                    </div>
                    <strong>Đang tải sản phẩm...</strong>
                  </div>
                </td>
              </tr>
              <tr v-else-if="products.length === 0">
                <td colspan="11" class="text-center py-5">Không có dữ liệu</td>
              </tr>
              <tr v-for="(product, index) in products" :key="product.id">
                <td>{{ (currentPage - 1) * perPage + index + 1 }}</td>
                <td>
                    <img v-if="product.image_url" :src="product.image_url" alt="Product Image" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                    <i v-else class="fas fa-image text-muted" style="font-size: 24px;"></i>
                </td>
                <td>{{ product.name }}</td>
                <td>{{ product.category ? product.category.name : '-' }}</td>
                <td>{{ product.sku }}</td>
                <td>{{ product.barcode || '-' }}</td>
                <td>{{ formatCurrency(product.base_price) }}</td>
                <td>{{ product.unit }}</td>
                <td>
                  <span v-if="product.track_stock">
                    {{ getTotalStock(product) }}
                  </span>
                  <span v-else>Không theo dõi</span>
                </td>
                <td>
                  <span
                    class="badge"
                    :class="product.active ? 'bg-success' : 'bg-secondary'"
                  >
                    {{ product.active ? 'Kích hoạt' : 'Vô hiệu' }}
                  </span>
                </td>
                <td>
                  <button
                    @click="editProduct(product)"
                    class="btn btn-sm btn-primary me-1"
                    title="Sửa"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    @click="confirmDelete(product)"
                    class="btn btn-sm btn-danger"
                    title="Xóa"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

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
            Hiển thị {{ (currentPage - 1) * perPage + 1 }} đến {{ Math.min(currentPage * perPage, totalFilteredProducts) }} của {{ totalFilteredProducts }} sản phẩm
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

    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">{{ isEditing ? 'Cập nhật' : 'Thêm mới' }} Sản phẩm</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="submitForm" class="row">
            
              
              <div class="col-md-6 border-end pe-md-4">
                <div class="mb-3">
                  <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="form.name"
                    required
                    @input="generateSku"
                  >
                  <div v-if="formErrors.name" class="text-danger small mt-1">{{ formErrors.name[0] }}</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">SKU <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="form.sku"
                    required
                  >
                  <small class="form-text text-muted">Mã định danh duy nhất cho một mục.</small>
                  <div v-if="formErrors.sku" class="text-danger small mt-1">{{ formErrors.sku[0] }}</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Mã vạch</label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="form.barcode"
                  >
                  <div v-if="formErrors.barcode" class="text-danger small mt-1">{{ formErrors.barcode[0] }}</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Danh mục <span class="text-danger">*</span></label>
                  <select class="form-select" v-model="form.category_id" required>
                    <option value="">-- Chọn danh mục --</option>
                    <option
                      v-for="cat in hierarchicalCategories"
                      :value="cat.id"
                      :key="cat.id"
                      :style="{'padding-left': (cat.level * 20) + 'px'}"
                    >
                      <span v-if="cat.level > 0">--- </span>{{ cat.name }}
                    </option>
                  </select>
                  <div v-if="formErrors.category_id" class="text-danger small mt-1">{{ formErrors.category_id[0] }}</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Giá gốc <span class="text-danger">*</span></label>
                  <input
                    type="number"
                    class="form-control"
                    v-model.number="form.base_price"
                    min="0"
                    step="0.01"
                    required
                  >
                  <div v-if="formErrors.base_price" class="text-danger small mt-1">{{ formErrors.base_price[0] }}</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Giá vốn</label>
                  <input
                    type="number"
                    class="form-control"
                    v-model.number="form.cost_price"
                    min="0"
                    step="0.01"
                  >
                  <small class="form-text text-muted">Giá trị tự động cập nhật khi bạn nhận hàng tồn kho.</small>
                  <div v-if="formErrors.cost_price" class="text-danger small mt-1">{{ formErrors.cost_price[0] }}</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Mô tả</label>
                  <textarea
                    class="form-control"
                    v-model="form.description"
                    rows="4"
                    placeholder="Mô tả sản phẩm..."
                  ></textarea>
                  <div v-if="formErrors.description" class="text-danger small mt-1">{{ formErrors.description[0] }}</div>
                </div>
                <div class="mb-3">
                  <label class="form-label d-block">Bán bởi</label>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="soldByEach" :value="false" v-model="form.sold_by_weight">
                    <label class="form-check-label" for="soldByEach">Mỗi</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="soldByWeight" :value="true" v-model="form.sold_by_weight">
                    <label class="form-check-label" for="soldByWeight">Trọng lượng/Khối lượng</label>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Đơn vị <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="form.unit"
                    required
                  >
                  <div v-if="formErrors.unit" class="text-danger small mt-1">{{ formErrors.unit[0] }}</div>
                </div>
                <div class="mb-3 form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="activeProductCheck" v-model="form.active">
                  <label class="form-check-label" for="activeProductCheck">Kích hoạt sản phẩm</label>
                </div>
              </div>

              <div class="col-md-6 ">
                <div class="col-12 text-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary" :disabled="loading">
                  <span v-if="loading" class="spinner-border spinner-border-sm"></span>
                  {{ isEditing ? 'Cập nhật' : 'Thêm mới' }}
                </button>
              </div>
              
                <div class="mb-3 p-3 border rounded">
                  <h6 class="mb-3">Ảnh sản phẩm</h6>
                  <div class="mb-2">
                    <label class="form-label">Dán liên kết ảnh (URL)</label>
                    <input
                      type="url"
                      class="form-control"
                      v-model="form.image_url"
                      placeholder="https://example.com/image.jpg"
                      @input="handleImageUrlInput"
                    >
                    <div v-if="formErrors.image_url" class="text-danger small mt-1">{{ formErrors.image_url[0] }}</div>
                  </div>
                  <div class="text-center text-muted mb-2">- HOẶC -</div>
                  <div class="mb-2">
                    <label class="form-label">Tải ảnh lên</label>
                    <input
                      type="file"
                      class="form-control"
                      ref="imageFile"
                      @change="handleImageFileChange"
                      accept="image/*"
                    >
                    <div v-if="formErrors.image_file" class="text-danger small mt-1">{{ formErrors.image_file[0] }}</div>
                  </div>
                  <div v-if="imageUrlPreview" class="mt-3 text-center">
                    <img :src="imageUrlPreview" alt="Xem trước ảnh" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2 d-block mx-auto" @click="clearImage">
                      <i class="fas fa-trash"></i> Xóa ảnh
                    </button>
                  </div>
                  <div v-else class="mt-3 text-center text-muted">
                      Không có ảnh nào được chọn.
                  </div>
                </div>
                
                

                <hr>

                <div class="mb-3 form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="trackStockCheck" v-model="form.track_stock">
                  <label class="form-check-label" for="trackStockCheck">Theo dõi hàng tồn kho</label>
                </div>

                <div v-if="form.track_stock" class="mt-3 p-3 border rounded">
                  <h6>Tồn kho theo chi nhánh</h6>
                  <div v-if="form.stocks.length === 0" class="text-muted mb-2">Chưa có thông tin tồn kho cho chi nhánh nào.</div>
                  <div v-for="(stock, index) in form.stocks" :key="index" class="row mb-2 align-items-end">
                    <div class="col-5">
                      <label class="form-label">Chi nhánh</label>
                      <select class="form-select" v-model="stock.branch_id" required>
                        <option value="">-- Chọn chi nhánh --</option>
                        <option v-for="branch in availableBranches(index, stock.branch_id)" :key="branch.id" :value="branch.id">
                          {{ branch.name }}
                        </option>
                      </select>
                      <div v-if="stockErrors[index] && stockErrors[index].branch_id" class="text-danger small">{{ stockErrors[index].branch_id[0] }}</div>
                    </div>
                    <div class="col-3">
                      <label class="form-label">Tồn kho</label>
                      <input type="number" class="form-control" v-model.number="stock.stock" min="0" required>
                      <div v-if="stockErrors[index] && stockErrors[index].stock" class="text-danger small">{{ stockErrors[index].stock[0] }}</div>
                    </div>
                    <div class="col-3">
                      <label class="form-label">Ngưỡng thấp</label>
                      <input type="number" class="form-control" v-model.number="stock.low_stock_threshold" min="0">
                    </div>
                     <div class="col-1">
                      <button type="button" class="btn btn-sm btn-danger" @click="removeProductStock(index)">
                        <i class="fas fa-minus"></i>
                      </button>
                    </div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-2" @click="addProductStock">
                    <i class="fas fa-plus"></i> Thêm tồn kho chi nhánh
                  </button>
                  <div v-if="formErrors.stocks" class="text-danger small mt-1">{{ formErrors.stocks[0] }}</div>
                </div>

                <hr>

                <h6 class="mt-3">Tùy chọn Sản phẩm</h6>
                <div v-if="form.options.length === 0" class="text-muted mb-2">Chưa có tùy chọn nào.</div>
                <div v-for="(option, optIndex) in form.options" :key="optIndex" class="mb-3 p-3 border rounded">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0">Tên tùy chọn <span class="text-danger">*</span></label>
                    <button type="button" class="btn btn-sm btn-danger" @click="removeProductOption(optIndex)">
                      <i class="fas fa-trash"></i> Xóa tùy chọn
                    </button>
                  </div>
                  <input type="text" class="form-control mb-2" v-model="option.name" placeholder="Ví dụ: Màu sắc" required>
                  <div v-if="optionErrors[optIndex] && optionErrors[optIndex].name" class="text-danger small mb-2">{{ optionErrors[optIndex].name[0] }}</div>

                  <label class="form-label mt-2">Giá trị tùy chọn <span class="text-danger">*</span></label>
                  <div v-for="(value, valIndex) in option.values" :key="valIndex" class="input-group mb-2">
                    <input type="text" class="form-control" v-model="option.values[valIndex]" placeholder="Ví dụ: Đỏ" required>
                    <button type="button" class="btn btn-outline-danger" @click="removeOptionValue(optIndex, valIndex)">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-1" @click="addOptionValue(optIndex)">
                    <i class="fas fa-plus"></i> Thêm giá trị
                  </button>
                  <div v-if="optionErrors[optIndex] && optionErrors[optIndex].values" class="text-danger small mt-2">{{ optionErrors[optIndex].values[0] }}</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" @click="addProductOption">
                  <i class="fas fa-plus"></i> Thêm tùy chọn sản phẩm
                </button>
                <div v-if="formErrors.options" class="text-danger small mt-1">{{ formErrors.options[0] }}</div>
              </div>
              
              
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">Xác nhận xóa sản phẩm</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Bạn có chắc chắn muốn xóa sản phẩm <strong>{{ productToDelete?.name }}</strong>?</p>
            <p class="text-danger">Lưu ý: Thao tác này không thể hoàn tác!</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button
              type="button"
              class="btn btn-danger"
              @click="deleteProduct"
              :disabled="deleteLoading"
            >
              <span v-if="deleteLoading" class="spinner-border spinner-border-sm"></span>
              Xóa
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="importProductModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title">Nhập Sản phẩm từ Excel</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Chọn file Excel (.xlsx) chứa dữ liệu sản phẩm để nhập.</p>
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
              @click="importProductExcel"
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
  name: 'ProductManagement',
  data() {
    return {
      allProductsData: [], // Stores all raw product data from the API
      products: [], // Processed, hierarchical (if applicable for products), filtered, and paginated products for display
      allCategories: [], // All categories for dropdown, used to build hierarchical display
      hierarchicalCategories: [], // Hierarchical list of categories for dropdown
      allBranches: [], // All branches for stock management dropdown

      searchQuery: '',
      selectedCategoryId: null, // Data property for category filter
      selectedBranchId: null, // New data property for branch filter

      // Pagination data
      currentPage: 1,
      perPage: 20, // Default items per page
      totalFilteredProducts: 0, // Total products after applying search filter
      lastPage: 1,
      pageInput: 1, // Used for the page number input field

      // Form
      isEditing: false,
      form: {
        id: null,
        category_id: null,
        name: '',
        sku: '',
        barcode: '',
        description: '',
        image_url: null, // Đã thêm: Lưu URL ảnh
        image_file: null, // Đã thêm: Lưu file ảnh để upload
        base_price: 0,
        cost_price: null,
        sold_by_weight: false, // Default to 'Mỗi'
        unit: 'cái', // Default unit
        track_stock: true, // Default to track stock
        active: true,
        options: [], // Array to hold product options [{name: '', values: []}]
        stocks: [],  // Array to hold product stocks [{branch_id: null, stock: 0, low_stock_threshold: 5, price_override: null, available: true}]
        modifiers: []
      },
      imageUrlPreview: null, // Đã thêm: Để hiển thị xem trước ảnh
      formErrors: {}, // General form errors from backend
      optionErrors: [], // Specific errors for product options
      stockErrors: [], // Specific errors for product stocks

      // Delete
      productToDelete: null,

      // Loading states
      loading: false, // For form submission
      deleteLoading: false, // For delete action
      importLoading: false, // For import action
      productLoading: false, // <-- THÊM DÒNG NÀY: Trạng thái loading cho việc tải danh sách sản phẩm

      // Import Excel
      selectedFile: null,
      importErrors: {}, // Server-side validation errors (e.g., file type)
      importStatus: { // Import status and detailed validation errors from Excel
        success: false,
        messages: [],
        validationErrors: []
      },

      // Barcode Scanner/Quick Add
      scannedBarcode: '',

      // Modal instances
      productModal: null,
      deleteProductModal: null,
      importProductModal: null // Add this for the import modal instance
    }
  },
  computed: {
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
  watch: {
    // No direct watch for searchQuery or selectedCategoryId/selectedBranchId
    // since fetchAndFilterProducts handles re-fetching based on these.
    // Debounce is applied directly in template for searchQuery.
  },
  mounted() {
    this.$nextTick(() => {
        if (typeof bootstrap !== 'undefined') {
            try {
                // Initialize Bootstrap modals
                this.productModal = new bootstrap.Modal(document.getElementById('productModal'));
                this.deleteProductModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
                this.importProductModal = new bootstrap.Modal(document.getElementById('importProductModal')); // Initialize import modal

                // Attach event listeners for modals to handle extra backdrops
                this.productModal._element.addEventListener('shown.bs.modal', () => {
                    console.log('Product Modal: shown');
                    this.removeExtraBackdrops();
                });
                this.productModal._element.addEventListener('hidden.bs.modal', () => {
                    console.log('Product Modal: hidden');
                    this.resetForm(); // Reset form when modal is hidden
                });
                this.deleteProductModal._element.addEventListener('shown.bs.modal', () => {
                    console.log('Delete Product Modal: shown');
                    this.removeExtraBackdrops();
                });
                this.deleteProductModal._element.addEventListener('hidden.bs.modal', () => {
                    console.log('Delete Product Modal: hidden');
                });
                // Event listeners for import modal
                this.importProductModal._element.addEventListener('shown.bs.modal', () => {
                    console.log('Import Product Modal: shown');
                    this.removeExtraBackdrops();
                });
                this.importProductModal._element.addEventListener('hidden.bs.modal', () => {
                    console.log('Import Product Modal: hidden');
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

        // Load all data when the component is mounted
        this.fetchAndFilterProducts(); // Use this to fetch data with category and branch filter
        this.fetchCategories(); // Fetch categories for the dropdown
        this.fetchBranches(); // Fetch branches for stock management
    });
  },
  methods: {
    // Fetches all products from the backend with optional category and branch filters,
    // then applies search/pagination locally.
    async fetchAndFilterProducts() {
      this.productLoading = true; // Bắt đầu tải
      try {
        let apiUrl = '/api/products';
        const params = {};
        if (this.selectedCategoryId !== null) {
            params.category_id = this.selectedCategoryId;
        }
        if (this.selectedBranchId !== null) {
            params.branch_id = this.selectedBranchId;
        }

        const response = await axios.get(apiUrl, { params });
        this.allProductsData = response.data; // Store all raw data
        this.applySearchAndPagination(1); // Apply filtering and pagination, always reset to page 1 for new filters
      } catch (error) {
        console.error('Error fetching all products:', error);
        this.showToast('Lỗi khi tải danh sách sản phẩm', 'danger');
        // Optionally clear products if there's an error
        this.allProductsData = [];
        this.products = [];
        this.totalFilteredProducts = 0;
        this.lastPage = 1;
        this.currentPage = 1;
      } finally {
        this.productLoading = false; // Kết thúc tải (dù thành công hay thất bại)
      }
    },

    // Fetches all categories for the dropdowns (e.g., in product form and filter)
    async fetchCategories() {
      try {
        const response = await axios.get('/api/products/categories-list');
        this.allCategories = response.data;
        this.hierarchicalCategories = this.buildCategoryHierarchy(this.allCategories);
      } catch (error) {
        console.error('Error fetching categories:', error);
        this.showToast('Lỗi khi tải danh sách danh mục', 'danger');
      }
    },

    // Fetches all branches for the product stock management and filter dropdown
    async fetchBranches() {
      try {
        const response = await axios.get('/api/products/branches');
        this.allBranches = response.data;
      } catch (error) {
        console.error('Error fetching branches:', error);
        this.showToast('Lỗi khi tải danh sách chi nhánh', 'danger');
      }
    },

    // Helper to build hierarchical category list (for dropdowns)
    buildCategoryHierarchy(categories, parentId = null, level = 0) {
      let hierarchicalList = [];
      const children = categories.filter(cat => cat.parent_id === parentId);
      children.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));

      for (const category of children) {
        hierarchicalList.push({ ...category, level: level });
        hierarchicalList = hierarchicalList.concat(
          this.buildCategoryHierarchy(categories, category.id, level + 1)
        );
      }
      return hierarchicalList;
    },

    // Applies client-side search filter and pagination on the `allProductsData`
    // (which might already be filtered by category/branch from the API).
    applySearchAndPagination(page = 1) {
      this.currentPage = page;
      this.pageInput = page;

      let filteredProducts = this.allProductsData;

      // Apply search query filter (client-side)
      if (this.searchQuery) {
        const lowerCaseQuery = this.searchQuery.toLowerCase();
        filteredProducts = this.allProductsData.filter(product =>
          product.name.toLowerCase().includes(lowerCaseQuery) ||
          product.sku.toLowerCase().includes(lowerCaseQuery) ||
          (product.barcode && String(product.barcode).toLowerCase().includes(lowerCaseQuery))
        );
      }

      this.totalFilteredProducts = filteredProducts.length;
      this.lastPage = Math.ceil(this.totalFilteredProducts / this.perPage);

      // Adjust current page if it's out of bounds after filtering
      if (this.currentPage > this.lastPage && this.lastPage > 0) {
        this.currentPage = this.lastPage;
        this.pageInput = this.lastPage;
      } else if (this.lastPage === 0) {
        this.currentPage = 1;
        this.pageInput = 1;
      }

      // Slice the filtered data for current page
      const startIndex = (this.currentPage - 1) * this.perPage;
      const endIndex = startIndex + this.perPage;
      this.products = filteredProducts.slice(startIndex, endIndex);
    },

    // Change page for pagination
    changePage(page) {
      if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
        this.applySearchAndPagination(page);
      }
    },

    // Go to specific page from input
    goToPage() {
      if (this.pageInput >= 1 && this.pageInput <= this.lastPage && this.pageInput !== this.currentPage) {
        this.applySearchAndPagination(this.pageInput);
      } else {
        this.pageInput = this.currentPage;
      }
    },

    // Opens the add product modal
    openAddModal() {
      this.isEditing = false;
      this.resetForm();
      this.form.active = true; // Default active for new products
      this.form.track_stock = true; // Default track stock for new products
      this.form.unit = 'cái'; // Default unit
      if (this.productModal) {
        this.productModal.show();
      } else {
        this.showToast('Lỗi: Không thể mở hộp thoại thêm sản phẩm. Vui lòng thử lại.', 'danger');
      }
    },

    // Generates SKU from product name for new products
    generateSku() {
      if (!this.isEditing && !this.form.sku) {
        this.form.sku = this.form.name
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

    // Debounced search to avoid too many client-side re-filters
    debounceSearch: _.debounce(function() {
      this.applySearchAndPagination(1);
    }, 500),

    // Opens the edit product modal and populates form
    editProduct(product) {
      this.isEditing = true;
      this.resetForm(); // Reset before populating
      this.form = {
        id: product.id,
        category_id: product.category_id,
        name: product.name,
        sku: product.sku,
        barcode: product.barcode,
        description: product.description,
        image_url: product.image_url,
        base_price: product.base_price,
        cost_price: product.cost_price,
        sold_by_weight: product.sold_by_weight,
        unit: product.unit,
        track_stock: product.track_stock,
        active: product.active,
        options: product.product_options ? product.product_options.map(opt => ({
            id: opt.id,
            name: opt.name,
            values: opt.values
        })) : [],
        stocks: product.product_stocks ? product.product_stocks.map(stock => ({
            id: stock.id,
            branch_id: stock.branch_id,
            stock: stock.stock,
            low_stock_threshold: stock.low_stock_threshold,
            available: stock.available,
            price_override: stock.price_override
        })) : [],
        modifiers: product.modifiers ? product.modifiers.map(modifier => ({
            id: modifier.id,
            name: modifier.name,
            price: modifier.price
        })) : []
      };
      this.imageUrlPreview = product.image_url;
      this.form.image_file = null;

      if (this.productModal) {
        this.productModal.show();
      } else {
        this.showToast('Lỗi: Không thể mở hộp thoại cập nhật sản phẩm. Vui lòng thử lại.', 'danger');
      }
    },

    // Submits the product form (create/update)
    async submitForm() {
      this.loading = true;
      this.formErrors = {};
      this.optionErrors = [];
      this.stockErrors = [];

      try {
        const url = this.isEditing
          ? `/api/products/${this.form.id}`
          : '/api/products';
        const method = this.isEditing ? 'post' : 'post'; // Use POST for both create/update with FormData

        const formData = new FormData(); //

        // Append all form fields
        for (const key in this.form) { //
            if (key === 'options' || key === 'stocks' || key === 'modifiers') { //
                formData.append(key, JSON.stringify(this.form[key])); //
            } else if (key === 'image_file') { //
                if (this.form.image_file) { //
                    formData.append('image_file', this.form.image_file); //
                }
            } else if (key === 'sold_by_weight' || key === 'track_stock' || key === 'active') { //
                // Explicitly convert boolean to 1 or 0 for backend validation
                formData.append(key, this.form[key] ? 1 : 0); //
            }
            else if (this.form[key] !== null) { //
                formData.append(key, this.form[key]); //
            }
        }
        if (this.isEditing) { //
            formData.append('_method', 'PUT'); //
        }
        // If image_file is not present and image_url is explicitly null, send an empty string to clear it
        if (!this.form.image_file && this.form.image_url === null) { //
            formData.append('image_url', ''); //
        }

        await axios({ //
            method: method, //
            url: url, //
            data: formData, //
            headers: { //
                'Content-Type': 'multipart/form-data' //
            }
        });

        this.showToast(`Sản phẩm đã ${this.isEditing ? 'cập nhật' : 'thêm mới'} thành công`);
        this.productModal?.hide();
        this.fetchAndFilterProducts(); // Refresh data after changes (respecting current category/branch filter)
      } catch (error) {
        console.error('Error submitting form:', error);
        if (error.response && error.response.status === 422) {
            this.formErrors = error.response.data.errors || {};

            this.optionErrors = Array(this.form.options.length).fill(null).map(() => ({}));
            this.stockErrors = Array(this.form.stocks.length).fill(null).map(() => ({}));

            for (const key in this.formErrors) {
                if (key.startsWith('options.')) {
                    const parts = key.split('.');
                    const index = parseInt(parts[1]);
                    const field = parts[2];
                    if (this.optionErrors[index]) this.optionErrors[index][field] = this.formErrors[key];
                } else if (key.startsWith('stocks.')) {
                    const parts = key.split('.');
                    const index = parseInt(parts[1]);
                    const field = parts[2];
                    if (this.stockErrors[index]) this.stockErrors[index][field] = this.formErrors[key];
                }
            }

            let errorMessage = 'Lỗi xác thực dữ liệu:';
            for (const field in this.formErrors) {
                if (!field.includes('options.') && !field.includes('stocks.')) {
                     errorMessage += `\n- ${this.formErrors[field][0]}`;
                }
            }
            this.showToast(errorMessage, 'danger');
        } else if (error.response && error.response.data && error.response.data.message) {
          this.showToast(`Lỗi: ${error.response.data.message}`, 'danger');
        } else {
          this.showToast('Đã xảy ra lỗi khi lưu sản phẩm', 'danger');
        }
      } finally {
        this.loading = false;
      }
    },

    // Confirms deletion of a product
    confirmDelete(product) {
      this.productToDelete = product;
      if (this.deleteProductModal) {
        this.deleteProductModal.show();
      } else {
        this.showToast('Lỗi: Không thể mở hộp thoại xác nhận xóa. Vui lòng thử lại.', 'danger');
      }
    },

    // Deletes a product
    async deleteProduct() {
      this.deleteLoading = true;
      try {
        await axios.delete(`/api/products/${this.productToDelete.id}`);
        this.showToast('Sản phẩm đã được xóa thành công');
        this.deleteProductModal?.hide();
        this.fetchAndFilterProducts(); // Refresh data
      } catch (error) {
        console.error('Error deleting product:', error);
        this.showToast('Lỗi khi xóa sản phẩm', 'danger');
      } finally {
        this.deleteLoading = false;
      }
    },

    // Utility to format currency
    formatCurrency(value) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(value);
    },

    // Calculates total stock for display
    getTotalStock(product) {
      if (!product.track_stock || !product.product_stocks) return 'N/A'; // "N/A" if not tracking stock or no stock data
      return product.product_stocks.reduce((sum, stock) => sum + stock.stock, 0);
    },

    /* --- Product Image Management --- */
    handleImageUrlInput() {
        if (this.form.image_url) {
            this.form.image_file = null;
            if (this.$refs.imageFile) {
                this.$refs.imageFile.value = '';
            }
            this.imageUrlPreview = this.form.image_url;
        } else {
            this.imageUrlPreview = null;
        }
    },
    handleImageFileChange(event) {
      const file = event.target.files[0];
      if (file) {
        this.form.image_file = file;
        this.form.image_url = null;
        const reader = new FileReader();
        reader.onload = (e) => {
          this.imageUrlPreview = e.target.result;
        };
        reader.readAsDataURL(file);
      } else {
        this.form.image_file = null;
        this.imageUrlPreview = null;
      }
    },
    clearImage() {
      this.form.image_url = null;
      this.form.image_file = null;
      this.imageUrlPreview = null;
      if (this.$refs.imageFile) {
        this.$refs.imageFile.value = '';
      }
    },

    /* --- Product Options Management --- */
    addProductOption() {
      this.form.options.push({ id: null, name: '', values: [''] });
    },
    removeProductOption(index) {
      this.form.options.splice(index, 1);
      if (this.optionErrors[index]) {
          this.optionErrors.splice(index, 1);
      }
    },
    addOptionValue(optionIndex) {
      this.form.options[optionIndex].values.push('');
    },
    removeOptionValue(optionIndex, valueIndex) {
      this.form.options[optionIndex].values.splice(valueIndex, 1);
      if (this.form.options[optionIndex].values.length === 0) {
          this.form.options[optionIndex].values.push('');
      }
    },

    /* --- Product Stocks Management --- */
    addProductStock() {
        const selectedBranchIds = new Set(this.form.stocks.map(s => s.branch_id));
        const firstAvailableBranch = this.allBranches.find(b => !selectedBranchIds.has(b.id));

        this.form.stocks.push({
            id: null,
            branch_id: firstAvailableBranch ? firstAvailableBranch.id : null,
            stock: 0,
            low_stock_threshold: 5,
            available: true,
            price_override: null,
        });
    },
    removeProductStock(index) {
        this.form.stocks.splice(index, 1);
        if (this.stockErrors[index]) {
            this.stockErrors.splice(index, 1);
        }
    },
    availableBranches(currentIndex, currentBranchId) {
        const selectedBranchIds = new Set(this.form.stocks
            .filter((stock, index) => index !== currentIndex)
            .map(s => s.branch_id)
        );
        if (currentBranchId) {
            selectedBranchIds.delete(currentBranchId);
        }
        return this.allBranches.filter(branch => !selectedBranchIds.has(branch.id) || branch.id === currentBranchId);
    },

    /* --- Export Functionality --- */
    async exportExcel() {
      try {
        const response = await axios.get('/api/products/export', {
          responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `san_pham_${new Date().toISOString().slice(0,10)}.xlsx`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        this.showToast('Đã xuất file Excel sản phẩm thành công!', 'success');
      } catch (error) {
        console.error('Error exporting products Excel:', error);
        this.showToast('Lỗi khi xuất dữ liệu sản phẩm ra Excel', 'danger');
      }
    },

    /* --- Import Functionality --- */
    openImportModal() {
      this.resetImportState();
      if (this.importProductModal) {
        console.log('Attempting to show import product modal...');
        this.importProductModal.show();
      } else {
        console.error("Cannot open Import Modal: importProductModal is not initialized.");
        this.showToast('Lỗi: Không thể mở hộp thoại nhập Excel. Vui lòng thử lại.', 'danger');
      }
    },

    handleFileChange(event) {
      this.selectedFile = event.target.files[0];
      this.importErrors = {};
      this.importStatus = { success: false, messages: [], validationErrors: [] };
    },

    async importProductExcel() {
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
        const response = await axios.post('/api/products/import', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });
        this.showToast(response.data.message || 'Nhập Excel thành công!', 'success');
        this.importStatus.success = true;
        this.importStatus.messages.push(response.data.message || 'Nhập Excel thành công!');
        if (response.data.validation_errors && response.data.validation_errors.length > 0) {
            this.importStatus.validationErrors = response.data.validation_errors;
            this.importStatus.messages.push('Có lỗi validation trong một số hàng.');
            this.importStatus.success = false;
            this.showToast('Có lỗi validation trong một số hàng. Vui lòng xem chi tiết.', 'warning');
        }

        this.fetchAndFilterProducts();
      } catch (error) {
        console.error('Error importing products Excel:', error);
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

    /* --- Barcode Quick Add/Edit --- */
    handleBarcodeScan() {
        const barcode = this.scannedBarcode.trim();
        if (!barcode) {
            this.showToast('Vui lòng nhập mã vạch để tìm kiếm hoặc thêm mới.', 'warning');
            return;
        }

        // Find product by barcode in the currently loaded data
        const foundProduct = this.allProductsData.find(p => p.barcode === barcode);

        if (foundProduct) {
            this.showToast(`Tìm thấy sản phẩm với mã vạch "${barcode}". Mở chế độ chỉnh sửa.`, 'success');
            this.editProduct(foundProduct);
        } else {
            this.showToast(`Không tìm thấy sản phẩm với mã vạch "${barcode}". Mở chế độ thêm mới.`, 'info');
            this.openAddModal();
            this.form.barcode = barcode; // Pre-fill barcode
        }
        this.scannedBarcode = ''; // Clear scanner input after action
        // Optionally focus the barcode input inside the modal if adding/editing
        this.$nextTick(() => {
            if (this.productModal && this.productModal._element.contains(document.activeElement)) {
                const modalBarcodeInput = this.productModal._element.querySelector('input[v-model="form.barcode"]');
                if (modalBarcodeInput) {
                    modalBarcodeInput.focus();
                }
            }
        });
    },

    /* --- Reset and Utility --- */
    resetForm() {
      this.form = {
        id: null,
        category_id: null,
        name: '',
        sku: '',
        barcode: '',
        description: '',
        image_url: null,
        image_file: null,
        base_price: 0,
        cost_price: null,
        sold_by_weight: false,
        unit: 'cái',
        track_stock: true,
        active: true,
        options: [],
        stocks: [],
        modifiers: [],
      };
      this.imageUrlPreview = null;
      this.formErrors = {};
      this.optionErrors = [];
      this.stockErrors = [];
      if (this.$refs.imageFile) {
        this.$refs.imageFile.value = '';
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

    // Show toast notifications
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
.product-management {
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

/* ADD THESE STYLES FOR SCROLLABLE MODAL BODY */
.modal-body {
  max-height: calc(100vh - 200px); /* Adjust this value as needed */
  /* 100vh là chiều cao của viewport.
     200px là ước tính tổng chiều cao của modal header và footer,
     cũng như padding trên/dưới của modal.
     Bạn có thể điều chỉnh 200px để phù hợp nhất với thiết kế của mình. */
  overflow-y: auto; /* Thêm thanh cuộn dọc khi nội dung vượt quá chiều cao */
  padding-bottom: 0; /* Loại bỏ padding dưới để thanh cuộn trông tự nhiên hơn */
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

/* Styles for two-column form layout within modal */
.modal-body .row {
  margin-left: -15px;
  margin-right: -15px;
}
.modal-body .col-md-6 {
  padding-left: 15px;
  padding-right: 15px;
}
.modal-body .border-end {
    border-right: 1px solid #dee2e6!important;
}
</style>
<style>
/* Global styles or styles that might affect Bootstrap components */
.modal-backdrop {
    --bs-backdrop-zindex: 1000;
}
</style>