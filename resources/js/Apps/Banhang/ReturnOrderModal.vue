<template>
  <div v-if="isVisible" class="modal-overlay">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Tạo Phiếu Trả Hàng cho Đơn hàng #{{ order.id }}</h5>
          <button type="button" class="btn-close" @click="$emit('close')"></button>
        </div>
        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>
          
          <div class="mb-3">
            <label for="returnReason" class="form-label">Lý do trả hàng (tùy chọn):</label>
            <textarea id="returnReason" v-model="returnReason" class="form-control" rows="2"></textarea>
          </div>

          <h6>Sản phẩm trong đơn hàng:</h6>
          <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
            <table class="table table-bordered table-striped table-sm">
              <thead>
                <tr>
                  <th>Sản phẩm</th>
                  <th>SL đã bán</th>
                  <th>SL trả về</th>
                  <th>Đơn giá</th>
                  <th>Hoàn tiền</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in order.order_items" :key="item.id">
                  <td>{{ item.product ? item.product.name : 'Sản phẩm không rõ' }}</td>
                  <td>{{ item.quantity }}</td>
                  <td>
                    <input 
                      type="number" 
                      v-model.number="returnQuantities[item.id]" 
                      min="0" 
                      :max="item.quantity" 
                      class="form-control form-control-sm"
                      @input="updateRefundAmount"
                    >
                  </td>
                  <td>{{ formatCurrency(item.unit_price) }}</td>
                  <td>{{ formatCurrency(item.unit_price * (returnQuantities[item.id] || 0)) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end mt-3">
            <strong>Tổng tiền hoàn trả dự kiến: {{ formatCurrency(estimatedRefund) }}</strong>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="$emit('close')">Hủy</button>
          <button type="button" class="btn btn-primary" @click="submitReturn" :disabled="isSubmitting">
            <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            {{ isSubmitting ? 'Đang tạo...' : 'Tạo Phiếu Trả Hàng' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  isVisible: {
    type: Boolean,
    required: true
  },
  order: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['close', 'return-success']);

const returnReason = ref('');
const returnQuantities = ref({}); // { order_item_id: quantity_to_return }
const estimatedRefund = ref(0);
const isSubmitting = ref(false);
const errorMessage = ref('');

// Initialize return quantities based on order items
watch(() => props.order, (newOrder) => {
  if (newOrder && newOrder.order_items) {
    const initialQuantities = {};
    newOrder.order_items.forEach(item => {
      initialQuantities[item.id] = 0; // Default to 0 for all items
    });
    returnQuantities.value = initialQuantities;
    updateRefundAmount(); // Calculate initial refund amount
  }
}, { immediate: true, deep: true }); // Watch deeply for order changes

const updateRefundAmount = () => {
  let total = 0;
  if (props.order && props.order.order_items) {
    props.order.order_items.forEach(item => {
      const quantity = returnQuantities.value[item.id] || 0;
      total += quantity * item.unit_price;
    });
  }
  estimatedRefund.value = total;
};

const submitReturn = async () => {
  errorMessage.value = '';
  isSubmitting.value = true;

  const itemsToReturn = [];
  if (props.order && props.order.order_items) {
    props.order.order_items.forEach(item => {
      const quantity = returnQuantities.value[item.id] || 0;
      if (quantity > 0) {
        itemsToReturn.push({
          product_id: item.product_id,
          order_item_id: item.id,
          quantity: quantity,
        });
      }
    });
  }

  if (itemsToReturn.length === 0) {
    errorMessage.value = 'Vui lòng chọn ít nhất một sản phẩm để trả hàng.';
    isSubmitting.value = false;
    return;
  }

  try {
    const response = await axios.post('/api/returns', {
      order_id: props.order.id,
      reason: returnReason.value,
      returned_items: itemsToReturn,
    });
    
    emit('return-success', response.data.return); // Gửi sự kiện thành công về component cha
    emit('close'); // Đóng modal
    // Có thể hiển thị thông báo toast thành công ở đây
    alert('Phiếu trả hàng đã được tạo thành công!'); // Tạm thời dùng alert
  } catch (error) {
    console.error('Lỗi khi tạo phiếu trả hàng:', error);
    if (error.response && error.response.data && error.response.data.message) {
      errorMessage.value = error.response.data.message;
    } else if (error.response && error.response.data && error.response.data.errors) {
        // Handle validation errors
        errorMessage.value = Object.values(error.response.data.errors).flat().join('\n');
    }
    else {
      errorMessage.value = 'Đã xảy ra lỗi khi tạo phiếu trả hàng. Vui lòng thử lại.';
    }
  } finally {
    isSubmitting.value = false;
  }
};

const formatCurrency = (value) => {
  if (typeof value !== 'number') return '0 VNĐ';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

// Reset form when modal opens/closes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    returnReason.value = '';
    errorMessage.value = '';
    // Reset quantities and recalculate when modal opens
    if (props.order && props.order.order_items) {
      const initialQuantities = {};
      props.order.order_items.forEach(item => {
        initialQuantities[item.id] = 0;
      });
      returnQuantities.value = initialQuantities;
    } else {
        returnQuantities.value = {};
    }
    updateRefundAmount();
  }
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10000; /* Ensure it's above other content */
}

.modal-dialog {
  background: #fff;
  border-radius: 0.5rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  width: 90%; /* Responsive width */
  max-width: 600px; /* Max width for larger screens */
}

.modal-content {
  display: flex;
  flex-direction: column;
  height: 100%; /* Take full height of dialog */
}

.modal-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-title {
  margin-bottom: 0;
  font-size: 1.25rem;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto; /* Allow scrolling for long content */
  flex-grow: 1; /* Allow body to take available space */
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #dee2e6;
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.form-control-sm {
    max-width: 80px; /* Adjust input width for quantity */
}

.table th, .table td {
    vertical-align: middle;
}
</style>
