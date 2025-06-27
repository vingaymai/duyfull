<template>
  <div class="reports-and-statistics p-4">
    <div class="card mb-4">
      <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Báo cáo & Thống kê</h5>
        <div class="d-flex align-items-center">
          <label for="startDate" class="form-label mb-0 me-2">Từ ngày:</label>
          <input type="date" id="startDate" v-model="startDate" class="form-control form-control-sm me-2" @change="fetchReports">
          <label for="endDate" class="form-label mb-0 me-2">Đến ngày:</label>
          <input type="date" id="endDate" v-model="endDate" class="form-control form-control-sm" @change="fetchReports">
        </div>
      </div>
      <div class="card-body">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-info me-2" role="status">
            <span class="visually-hidden">Đang tải báo cáo...</span>
          </div>
          <strong>Đang tải báo cáo...</strong>
        </div>
        <div v-else>
          <div class="row mb-4">
            <!-- Tổng doanh thu -->
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-primary text-white h-100 shadow-sm rounded-3">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="text-uppercase text-light mb-0">Tổng doanh thu</h6>
                      <h4 class="mb-0">{{ formatCurrency(reports.sales_summary.total_sales) }}</h4>
                    </div>
                    <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                  </div>
                </div>
              </div>
            </div>
            <!-- Tổng đơn hàng -->
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-success text-white h-100 shadow-sm rounded-3">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="text-uppercase text-light mb-0">Tổng đơn hàng</h6>
                      <h4 class="mb-0">{{ reports.sales_summary.total_orders }}</h4>
                    </div>
                    <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
                  </div>
                </div>
              </div>
            </div>
            <!-- Tổng lợi nhuận -->
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-warning text-white h-100 shadow-sm rounded-3">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="text-uppercase text-light mb-0">Tổng lợi nhuận</h6>
                      <h4 class="mb-0">{{ formatCurrency(reports.profit_summary.total_profit) }}</h4>
                    </div>
                    <i class="fas fa-coins fa-2x opacity-75"></i>
                  </div>
                </div>
              </div>
            </div>
            <!-- Tồn kho -->
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-danger text-white h-100 shadow-sm rounded-3">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="text-uppercase text-light mb-0">Sản phẩm tồn kho</h6>
                      <h4 class="mb-0">{{ reports.stock_report.in_stock }} / {{ reports.stock_report.total_products }}</h4>
                    </div>
                    <i class="fas fa-boxes fa-2x opacity-75"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Biểu đồ doanh thu hàng ngày -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="card shadow-sm rounded-3">
                <div class="card-header bg-light">
                  <h6 class="mb-0">Doanh thu hàng ngày ({{ reports.sales_summary.start_date }} - {{ reports.sales_summary.end_date }})</h6>
                </div>
                <div class="card-body">
                  <canvas ref="dailySalesChartCanvas"></canvas>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <!-- Sản phẩm bán chạy nhất -->
            <div class="col-md-6 mb-3">
              <div class="card shadow-sm rounded-3 h-100">
                <div class="card-header bg-light">
                  <h6 class="mb-0">Sản phẩm bán chạy nhất</h6>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th>STT</th>
                          <th>Tên sản phẩm</th>
                          <th>Mã SKU</th>
                          <th>Số lượng bán</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="reports.best_selling_products.length === 0">
                          <td colspan="4" class="text-center text-muted">Không có sản phẩm bán chạy trong giai đoạn này.</td>
                        </tr>
                        <tr v-for="(product, index) in reports.best_selling_products" :key="index">
                          <td>{{ index + 1 }}</td>
                          <td>{{ product.name }}</td>
                          <td>{{ product.sku }}</td>
                          <td>{{ product.total_quantity_sold }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Báo cáo tồn kho chi tiết (sản phẩm sắp hết/hết hàng) -->
            <div class="col-md-6 mb-3">
              <div class="card shadow-sm rounded-3 h-100">
                <div class="card-header bg-light">
                  <h6 class="mb-0">Chi tiết tồn kho</h6>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th>Tên sản phẩm</th>
                          <th>Tồn kho</th>
                          <th>Ngưỡng thấp</th>
                          <th>Trạng thái</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="reports.stock_report.products.length === 0">
                          <td colspan="4" class="text-center text-muted">Không có dữ liệu tồn kho.</td>
                        </tr>
                        <tr v-for="(product, index) in reports.stock_report.products" :key="index">
                          <td>{{ product.name }}</td>
                          <td>{{ product.total_stock_quantity }}</td>
                          <td>{{ product.effective_low_stock_threshold }}</td>
                          <td>
                            <span class="badge" :class="getStockStatusClass(product)">
                              {{ getStockStatusText(product) }}
                            </span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { Chart, registerables } from 'chart.js';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

Chart.register(...registerables); // Register all necessary components for Chart.js

export default {
  name: 'BaoCaoThongKe',
  data() {
    return {
      loading: false,
      startDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
      endDate: new Date().toISOString().slice(0, 10),
      reports: {
        sales_summary: {
          total_sales: 0,
          total_orders: 0,
          start_date: '',
          end_date: '',
          daily_sales: [],
        },
        profit_summary: {
          total_profit: 0,
          start_date: '',
          end_date: '',
        },
        best_selling_products: [],
        stock_report: {
          total_products: 0,
          in_stock: 0,
          low_stock: 0,
          out_of_stock: 0,
          products: [],
        },
      },
      dailySalesChart: null, // To store the Chart.js instance
    };
  },
  mounted() {
    this.fetchReports(); // Initial fetch on component mount
  },
  watch: {
    // Watch for changes in reports.sales_summary.daily_sales to re-render chart
    // Only render chart if not loading and canvas ref is available
    'reports.sales_summary.daily_sales': {
      handler() {
        if (!this.loading && this.$refs.dailySalesChartCanvas) {
          this.renderDailySalesChart();
        }
      },
      deep: true // Watch for deep changes in the array
    }
  },
  methods: {
    async fetchReports() {
      this.loading = true;
      try {
        const response = await axios.get('/api/reports/overview', {
          params: {
            start_date: this.startDate,
            end_date: this.endDate,
          },
        });
        // Chuyển đổi các giá trị số từ chuỗi sang kiểu number
        this.reports = {
          ...response.data,
          sales_summary: {
            ...response.data.sales_summary,
            total_sales: parseFloat(response.data.sales_summary.total_sales),
            total_orders: parseInt(response.data.sales_summary.total_orders),
            daily_sales: response.data.sales_summary.daily_sales.map(item => ({
              ...item,
              daily_sales: parseFloat(item.daily_sales)
            }))
          },
          profit_summary: {
            ...response.data.profit_summary,
            total_profit: parseFloat(response.data.profit_summary.total_profit),
          },
          // Stock report already handles numbers or cast to int in backend
        };

        // Call renderDailySalesChart after data is fetched and DOM is potentially updated
        // using nextTick to ensure canvas element is available
        this.$nextTick(() => {
          this.renderDailySalesChart();
        });

      } catch (error) {
        console.error('Error fetching reports:', error);
        if (error.response) {
          if (error.response.status === 401) {
            this.showToast('Lỗi: Bạn chưa đăng nhập hoặc phiên đã hết hạn. Vui lòng đăng nhập lại.', 'danger');
          } else if (error.response.status === 403) {
            this.showToast('Lỗi: Bạn không có quyền xem báo cáo này.', 'danger');
          } else if (error.response.data && error.response.data.message) {
            this.showToast(`Lỗi: ${error.response.data.message}`, 'danger');
          } else {
            this.showToast('Lỗi khi tải báo cáo. Vui lòng kiểm tra lại.', 'danger');
          }
        } else if (error.request) {
          this.showToast('Lỗi: Không nhận được phản hồi từ máy chủ. Vui lòng kiểm tra kết nối mạng hoặc trạng thái máy chủ.', 'danger');
        } else {
          this.showToast('Lỗi: Đã xảy ra lỗi không xác định khi tải báo cáo. Vui lòng thử lại.', 'danger');
        }
        // Reset reports to default or clear them on error
        this.reports = {
          sales_summary: { total_sales: 0, total_orders: 0, start_date: this.startDate, end_date: this.endDate, daily_sales: [] },
          profit_summary: { total_profit: 0, start_date: this.startDate, end_date: this.endDate },
          best_selling_products: [],
          stock_report: { total_products: 0, in_stock: 0, low_stock: 0, out_of_stock: 0, products: [] },
        };
      } finally {
        this.loading = false;
      }
    },
    renderDailySalesChart() {
      // Ensure canvas element exists before trying to get context
      if (!this.$refs.dailySalesChartCanvas) {
        console.warn("Canvas element not found for chart rendering.");
        return;
      }
      if (this.dailySalesChart) {
        this.dailySalesChart.destroy(); // Destroy previous chart instance
      }

      const ctx = this.$refs.dailySalesChartCanvas.getContext('2d');
      const labels = this.reports.sales_summary.daily_sales.map(item => item.date);
      const data = this.reports.sales_summary.daily_sales.map(item => item.daily_sales);

      this.dailySalesChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Doanh thu hàng ngày',
              data: data,
              borderColor: '#007bff',
              backgroundColor: 'rgba(0, 123, 255, 0.2)',
              fill: true,
              tension: 0.1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              title: {
                display: true,
                text: 'Doanh thu (VNĐ)',
              },
            },
            x: {
              title: {
                display: true,
                text: 'Ngày',
              },
            },
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: function (context) {
                  return 'Doanh thu: ' + new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.raw);
                },
              },
            },
          },
        },
      });
    },
    formatCurrency(value) {
      if (typeof value !== 'number') return '0 VNĐ';
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
    },
    getStockStatusText(product) {
      // Sử dụng tên cột 'total_stock_quantity' và 'effective_low_stock_threshold' từ ReportController
      if (product.total_stock_quantity <= 0) {
        return 'Hết hàng';
      } else if (product.total_stock_quantity <= product.effective_low_stock_threshold) {
        return 'Sắp hết hàng';
      } else {
        return 'Còn hàng';
      }
    },
    getStockStatusClass(product) {
      // Sử dụng tên cột 'total_stock_quantity' và 'effective_low_stock_threshold' từ ReportController
      if (product.total_stock_quantity <= 0) {
        return 'bg-danger';
      } else if (product.total_stock_quantity <= product.effective_low_stock_threshold) {
        return 'bg-warning';
      } else {
        return 'bg-success';
      }
    },
    showToast(message, variant = 'success') {
      let toastContainer = document.querySelector('.toast-container');
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
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
  },
  beforeUnmount() {
    if (this.dailySalesChart) {
      this.dailySalesChart.destroy(); // Clean up chart instance before component is unmounted
    }
  },
};
</script>

<style scoped>
.reports-and-statistics {
  font-family: 'Inter', sans-serif;
  background-color: #f0f2f5;
  min-height: 100vh;
}

.card {
  border-radius: 0.75rem;
  overflow: hidden;
}

.card-header {
  border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.card-body {
  padding: 1.5rem;
}

.table-responsive {
  max-height: 400px; /* Adjust as needed */
  overflow-y: auto;
}

.table thead th {
  background-color: #e9ecef;
  position: sticky;
  top: 0;
  z-index: 1;
}

.badge {
  font-size: 0.8em;
  padding: 0.4em 0.7em;
  border-radius: 0.5rem;
}

/* Custom card styles for overview */
.card.bg-primary, .card.bg-success, .card.bg-warning, .card.bg-danger {
  border: none;
}

.card.bg-primary { background-color: #0d6efd !important; }
.card.bg-success { background-color: #198754 !important; }
.card.bg-warning { background-color: #ffc107 !important; }
.card.bg-danger { background-color: #dc3545 !important; }

/* Adjust height for canvas */
canvas {
  max-height: 300px; /* Adjust height as needed for charts */
  width: 100% !important;
  height: auto !important;
}
</style>
