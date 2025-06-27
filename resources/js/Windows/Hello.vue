<template>
  <div class="desktop-environment" @dragover="onDragOver" @drop="onDrop">
    <!-- Loading Overlay -->
    <Transition name="fade">
      <div v-if="isLoading" class="loading-overlay">
        <div class="loading-content">
          <div class="spinner"></div>
          <p>Hệ thống đang được khởi động...</p>
        </div>
      </div>
    </Transition>

    <!-- Desktop Icons -->
    <div class="desktop">
      <div
        v-for="app in visibleDesktopApps"
        :key="app.id"
        class="desktop-icon"
        :style="{ left: `${iconPositions[app.id]?.x || 0}px`, top: `${iconPositions[app.id]?.y || 0}px` }"
        draggable="true"
        @dragstart="onDragStart($event, app.id)"
        @click="openApp(app.id)"
      >
        <img :src="app.icon" :alt="app.title" />
        <div>{{ app.title }}</div>
      </div>
    </div>

    <!-- Dynamic Windows -->
    <WindowApp
      v-for="app in openApps"
      :key="app.id"
      :component="app.component"
      :title="app.title"
      :z-index="app.zIndex"
      :visible="app.visible"
      :minimized="app.minimized"
      @close="closeApp(app.id)"
      @minimize="minimizeApp(app.id)"
      @focus="focusApp(app.id)"
      @bring-to-front="focusApp(app.id)"
      :open-app="openApp"
    />

    <!-- Taskbar -->
    <div class="taskbar">
      <div class="start-button" @click="toggleStartMenu">Start</div>
      
      <!-- Search Input -->
      <input 
        type="text" 
        v-model="searchQuery" 
        placeholder="Tìm kiếm ứng dụng..." 
        class="search-input"
        @focus="startMenuVisible = true"
        @keyup.enter="openFirstFilteredApp"
      />

      <div class="taskbar-apps">
        <div
          v-for="app in openApps"
          :key="app.id"
          class="taskbar-app"
          :class="{ 
            active: app.visible && !app.minimized,
            hasWindow: true
          }"
          @click="toggleAppVisibility(app.id)"
        >
          <img :src="getAppIcon(app.id)" class="taskbar-icon" />
          <span>{{ app.title }}</span>
        </div>
      </div>
      
      <div class="clock">{{ currentTime }}</div>
    </div>

    <!-- Start Menu -->
    <Transition name="fade">
      <div v-if="startMenuVisible" class="start-menu">
        <ul>
          <li v-for="app in filteredStartMenuApps" :key="app.id" @click="openApp(app.id)">
            <img :src="app.icon" class="start-menu-icon" />
            <span>{{ app.title }}</span>
          </li>
          <!-- Nút Đăng xuất trong Start Menu -->
          <li @click="logout" class="logout-button">
            <i class="fas fa-sign-out-alt start-menu-icon"></i> <!-- Font Awesome icon -->
            <span>Đăng xuất</span>
          </li>
        </ul>
        <div v-if="filteredStartMenuApps.length === 0 && searchQuery" class="no-results">
          Không tìm thấy ứng dụng nào.
        </div>
      </div>
    </Transition>

    <!-- Toast Notification (Basic implementation within this component for quick use) -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
      <div id="appToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body" id="app-toast-body">
            <!-- Message will be injected here -->
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, markRaw, onMounted, onBeforeUnmount, computed, watch } from 'vue' // Import watch
import WindowApp from './WindowApp.vue'
import Sales from '../Apps/Banhang/Sales.vue'
import ql_danhmuc_sanpham from '../Apps/Banhang/ql_danhmuc_sanpham.vue'
import ql_chinhanh from '../Apps/Banhang/ql_chinhanh.vue'
import ql_sanpham from '../Apps/Banhang/ql_sanpham.vue'
import ql_khachhang from '../Apps/Banhang/ql_khachhang.vue'
import ql_donhang from '../Apps/Banhang/ql_donhang.vue'
import ql_nguoidung from '../Apps/Banhang/ql_nguoidung.vue'
import baocaothongke from '../Apps/Banhang/baocaothongke.vue'
import { Toast } from 'bootstrap'; // Import Toast from Bootstrap
import axios from 'axios';


// Định nghĩa props để nhận user và userPermissions từ backend
const props = defineProps({
  user: Object, // Thông tin người dùng đã xác thực (bao gồm cả roles nếu bạn muốn kiểm tra admin bằng roles)
  userPermissions: {
    type: Array,
    default: () => [] // Danh sách tên quyền của người dùng (ví dụ: ['access_app_sales', 'view products'])
  },
});

// App components mapping
const appComponents = {
  sales: markRaw(Sales),
  ql_sanpham: markRaw(ql_sanpham),
  ql_danhmuc_sanpham: markRaw(ql_danhmuc_sanpham),
  ql_chinhanh: markRaw(ql_chinhanh),
  ql_khachhang: markRaw(ql_khachhang),
  ql_donhang: markRaw(ql_donhang),
  ql_nguoidung: markRaw(ql_nguoidung),
  baocaothongke: markRaw(baocaothongke),
  
}

// Full list of desktop apps with all details
const allDesktopApps = [
  {
    id: 'sales',
    title: 'Bán hàng',
    icon: 'https://icons.iconarchive.com/icons/fasticon/shop-cart/128/shop-cart-down-icon.png',
    component: appComponents.sales,
  },
  {
    id: 'ql_sanpham',
    title: 'Quản lý sản phẩm',
    icon: 'https://icons.iconarchive.com/icons/google/noto-emoji-food-drink/128/32399-bento-box-icon.png',
    component: appComponents.ql_sanpham,
  },
  {
    id: 'ql_danhmuc_sanpham',
    title: 'Danh mục sản phẩm',
    icon: 'https://icons.iconarchive.com/icons/guillendesign/variations-3/128/Shop-3-icon.png',
    component: appComponents.ql_danhmuc_sanpham,
  },
  {
    id: 'ql_chinhanh',
    title: 'Chi Nhánh',
    icon: 'https://icons.iconarchive.com/icons/rockettheme/ecommerce/128/shop-icon.png',
    component: appComponents.ql_chinhanh,
  },
  {
    id: 'ql_khachhang',
    title: 'Khách Hàng',
    icon: 'https://icons.iconarchive.com/icons/icons-land/vista-people/128/Groups-Rescuers-Light-icon.png',
    component: appComponents.ql_khachhang,
  },
  {
    id: 'ql_nguoidung',
    title: 'Phân quyền người dùng',
    icon: 'https://icons.iconarchive.com/icons/icons-land/vista-people/128/Groups-Meeting-Dark-icon.png',
    component: appComponents.ql_nguoidung,
  },
  {
    id: 'ql_donhang',
    title: 'Đơn Hàng',
    icon: 'https://icons.iconarchive.com/icons/custom-icon-design/flatastic-5/128/Order-history-icon.png',
    component: appComponents.ql_donhang,
  },
  {
    id: 'baocaothongke',
    title: 'Thống kê',
    icon: 'https://icons.iconarchive.com/icons/antialiasfactory/enterprise-resource-planning/128/Balance-icon.png',
    component: appComponents.baocaothongke,
  },
];


// Mock user data for testing (Replace with actual data fetching in a real app)
const user = ref({
  id: 1,
  name: 'Admin User',
  email: 'admin@example.com',
  roles: [{ id: 1, name: 'admin' }], // Example role
});

// Mock user permissions for testing (Replace with actual data fetching in a real app)
const userPermissions = ref([
  'access_app_sales',
  'access_app_ql_sanpham',
  'access_app_ql_khachhang',
  'access_app_ql_nguoidung', // Add this permission for the user management app
  'access_app_ql_donhang',
]);

// Ref for Toast instance
const appToast = ref(null);

// Computed property for accessible apps
const visibleDesktopApps = computed(() => {
  // Check if user is admin (assuming user object has roles array and one role name is 'admin')
  const isAdmin = props.user && props.user.roles && props.user.roles.some(role => role.name === 'admin');
  console.log('[DEBUG] User Data:', props.user);
  console.log('[DEBUG] User Permissions:', props.userPermissions);
  console.log('[DEBUG] Is Admin:', isAdmin);

  // If admin, show all apps
  if (isAdmin) {
    console.log('[DEBUG] Showing all apps for admin.');
    return allDesktopApps;
  }

  // If userPermissions is explicitly provided and has items, filter based on them
  if (props.userPermissions && props.userPermissions.length > 0) {
    const filteredApps = allDesktopApps.filter(app => props.userPermissions.includes('access_app_' + app.id));
    console.log('[DEBUG] Filtered Apps based on explicit permissions:', filteredApps);
    return filteredApps;
  }
  
  // Fallback: If no explicit permissions are provided (empty array), show all apps
  console.log('[DEBUG] No explicit permissions provided, showing all apps as fallback.');
  return allDesktopApps;
});


// Window management
const openApps = ref([])
const zIndexCounter = ref(1000)
const startMenuVisible = ref(false)
const isLoading = ref(true)

// Icon positioning for drag & drop
const iconPositions = ref({});
let draggedAppId = null;
let offsetX = 0; // Offset from mouse pointer to the top-left of the icon
let offsetY = 0;

// Search functionality
const searchQuery = ref('');

const filteredStartMenuApps = computed(() => {
  let appsToFilter = visibleDesktopApps.value; // Filter from already accessible apps

  if (!searchQuery.value) {
    return appsToFilter; // Return all visible apps if search query is empty
  }
  const query = searchQuery.value.toLowerCase();
  return appsToFilter.filter(app => 
    app.title.toLowerCase().includes(query)
  );
});

// Function to open the first app in the filtered list (on Enter key press)
const openFirstFilteredApp = () => {
  if (filteredStartMenuApps.value.length > 0) {
    openApp(filteredStartMenuApps.value[0].id);
    searchQuery.value = ''; // Clear search query after opening
    startMenuVisible.value = false; // Close start menu
  }
};


// Time management
const currentTime = ref('')
const updateTime = () => {
  currentTime.value = new Date().toLocaleTimeString()
}

// Helper functions
const getAppIcon = (appId) => {
  const app = allDesktopApps.find(a => a.id === appId) // Find from allDesktopApps for icon
  return app?.icon || ''
}

// Icon Drag & Drop functions
const loadIconPositions = () => {
  // Updated icon dimensions
  const iconWidth = 90; // Matches .desktop-icon width
  const iconHeight = 110; // Matches .desktop-icon height

  const desktopPaddingX = 20; // Padding from left edge of desktop
  const desktopPaddingY = 20; // Padding from top edge of desktop
  const colSpacing = 20; // Space between columns
  const rowSpacing = 20; // Space between rows

  let currentX = desktopPaddingX;
  let currentY = desktopPaddingY;
  
  // Calculate max columns based on available width and icon + spacing
  // Ensure a minimum width to avoid division by zero or negative values if window is too small
  const availableWidth = window.innerWidth - desktopPaddingX * 2;
  const maxCols = Math.floor(availableWidth / (iconWidth + colSpacing)) || 1; // Ensure at least 1 column
  let colCount = 0;

  // IMPORTANT: Iterate over visibleDesktopApps, not allDesktopApps for positioning
  // This ensures only accessible apps are positioned.
  visibleDesktopApps.value.forEach((app) => { 
    // Check if moving to next column would exceed maxCols
    if (colCount >= maxCols) {
      colCount = 0; // Reset column count for new row
      currentY += iconHeight + rowSpacing; // Move to next row
      currentX = desktopPaddingX; // Reset X for new row
    }

    iconPositions.value[app.id] = { x: currentX, y: currentY };
    currentX += iconWidth + colSpacing;
    colCount++;
  });
};

const saveIconPositions = () => {
  // Not saving positions to localStorage for now
};

const onDragStart = (event, id) => {
  draggedAppId = id;
  const target = event.target;
  const rect = target.getBoundingClientRect();
  offsetX = event.clientX - rect.left;
  offsetY = event.clientY - rect.top;

  event.dataTransfer.setData('text/plain', id);
  // Set drag image to null to avoid ghost image
  event.dataTransfer.setDragImage(new Image(), 0, 0); // Hide default drag image
};

const onDragOver = (event) => {
  event.preventDefault(); // Allow drop
};

const onDrop = (event) => {
  event.preventDefault();
  if (draggedAppId) {
    const desktopElement = event.currentTarget; // The desktop div itself
    const desktopRect = desktopElement.getBoundingClientRect();
    
    // Calculate new position relative to the desktop container
    let newX = event.clientX - desktopRect.left - offsetX;
    let newY = event.clientY - desktopRect.top - offsetY;

    // Optional: Snap to a grid (e.g., 10px grid)
    const gridSize = 10;
    newX = Math.round(newX / gridSize) * gridSize;
    newY = Math.round(newY / gridSize) * gridSize;

    // Ensure icon stays within desktop bounds (optional, but good UX)
    const iconWidth = 90; // Updated to new width
    const iconHeight = 110; // Updated to new height

    newX = Math.max(0, Math.min(newX, desktopRect.width - iconWidth));
    newY = Math.max(0, Math.min(newY, desktopRect.height - iconHeight));


    iconPositions.value[draggedAppId] = { x: newX, y: newY };
    // saveIconPositions(); // Uncomment if you want to save positions after drag and drop
    draggedAppId = null;
  }
};


// App management functions
const openApp = (id) => {
  // Check if the user has permission to access this app
  const permissionName = 'access_app_' + id;
  const isAdmin = props.user && props.user.roles && props.user.roles.some(role => role.name === 'admin');

  // If not admin AND (permissions are explicitly defined BUT current permission is not included)
  // this is the core logic that prevents opening restricted apps
  if (!isAdmin && props.userPermissions && props.userPermissions.length > 0 && !props.userPermissions.includes(permissionName)) {
    showToast('danger', `Bạn không có quyền truy cập ứng dụng "${allDesktopApps.find(app => app.id === id)?.title || id}".`);
    return;
  }

  const existingApp = openApps.value.find(app => app.id === id)
  
  if (existingApp) {
    existingApp.visible = true
    existingApp.minimized = false
    existingApp.zIndex = ++zIndexCounter.value
    return
  }

  const appConfig = allDesktopApps.find(app => app.id === id) // Find from allDesktopApps for component
  if (!appConfig) return

  openApps.value.push({
    id,
    title: appConfig.title,
    component: appConfig.component,
    visible: true,
    minimized: false,
    zIndex: ++zIndexCounter.value,
  })
  
  startMenuVisible.value = false
}

const closeApp = (id) => {
  openApps.value = openApps.value.filter(app => app.id !== id)
}

const minimizeApp = (id) => {
  const app = openApps.value.find(app => app.id === id)
  if (app) {
    app.minimized = !app.minimized
  }
}

const toggleAppVisibility = (id) => {
  const app = openApps.value.find(app => app.id === id)
  if (app) {
    if (app.minimized || !app.visible) {
      app.visible = true
      app.minimized = false
      app.zIndex = ++zIndexCounter.value
    } else {
      app.minimized = true
    }
  }
}

const focusApp = (id) => {
  const app = openApps.value.find(app => app.id === id)
  if (app) {
    app.zIndex = ++zIndexCounter.value
  }
}

const toggleStartMenu = () => {
  startMenuVisible.value = !startMenuVisible.value
}

// Basic Toast Notification
const showToast = (type, message) => {
  const toastElement = document.getElementById('appToast');
  const toastBody = document.getElementById('app-toast-body');

  if (toastElement && toastBody) {
    // Remove all existing color classes and add the new one
    toastElement.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info');
    toastElement.classList.add(`bg-${type}`);
    toastBody.textContent = message;
    
    // Get or create Toast instance
    if (!appToast.value) {
      appToast.value = new Toast(toastElement, { autohide: true, delay: 3000 });
    }
    appToast.value.show();
  } else {
    console.warn("Bootstrap Toast elements not found. Cannot display toast.");
    alert(message); // Fallback to alert if toast elements are missing
  }
};


// Logout method
const logout = async () => {
  try {
    await axios.post('/logout'); // Call the logout route defined in web.php
    window.location.href = '/login'; // Redirect to login page after logout
  } catch (error) {
    console.error('Đăng xuất thất bại:', error);
    showToast('danger', 'Đăng xuất thất bại. Vui lòng thử lại.');
  }
};

// Initialize clock and handle initial loading state
onMounted(() => {
  updateTime();
  const timer = setInterval(updateTime, 1000);
  onBeforeUnmount(() => clearInterval(timer));

  // Initial load icon positions
  loadIconPositions();
  
  // Simulate loading time (e.g., fetching initial user data or configurations)
  setTimeout(() => {
    isLoading.value = false; // Set loading to false after a delay
  }, 1500); // 1.5 seconds loading time

  // Add resize listener for initial layout recalculation
  window.addEventListener('resize', loadIconPositions);
  onBeforeUnmount(() => {
    window.removeEventListener('resize', loadIconPositions);
  });
});

// Watch for changes in visibleDesktopApps and re-layout icons
watch(visibleDesktopApps, () => {
    console.log('[DEBUG] visibleDesktopApps changed, re-laying out icons.');
    loadIconPositions(); // Recalculate icon positions when visible apps change
}, { immediate: true }); // Run immediately on component mount
</script>

<style scoped>
.desktop-environment {
  width: 100vw;
  height: 100vh;
  background: url('https://source.unsplash.com/random/1920x1080/?abstract,geometric') no-repeat center center fixed; /* Ngẫu nhiên hình nền desktop */
  background-size: cover;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  overflow: hidden; /* Prevent overflow from windows */
  position: relative; /* Needed for loading overlay positioning and icon absolute positioning */
}

.desktop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 40px; /* Space for taskbar */
  user-select: none;
  width: 100%;
  height: calc(100% - 40px);
}

.desktop-icon {
  position: absolute; /* Allows dragging to arbitrary positions */
  width: 90px; /* Tăng chiều rộng để chứa tên dài hơn */
  height: 110px; /* Tăng chiều cao để có không gian cho icon và text */
  text-align: center;
  cursor: grab; /* Indicate draggable */
  padding: 4px; /* Padding nhỏ để tránh tràn */
  border-radius: 4px;
  display: flex;  
  flex-direction: column;
  align-items: center; /* Giữ icon và text ở giữa theo chiều ngang */
  color: white; /* Text color for icons */
  text-shadow: 1px 1px 2px rgba(0,0,0,0.7); /* Text shadow cho dễ đọc */
  justify-content: flex-start; /* Đẩy nội dung lên trên để text ở dưới có đủ không gian */
  padding-top: 10px; /* Đẩy nội dung từ trên xuống một chút */
  font-size: 0.85rem; /* Giảm kích thước font để text ít bị tràn */
  line-height: 1.2; /* Tăng khoảng cách dòng */
  
  /* Tối ưu hiển thị tên dài */
  overflow: hidden; /* Ẩn phần text nếu quá dài */
  text-overflow: ellipsis; /* Thêm ... nếu text bị cắt */
  white-space: normal; /* Cho phép xuống dòng */
  word-break: break-word; /* Cho phép ngắt từ nếu từ quá dài */
  display: -webkit-box; /* Cần cho -webkit-line-clamp */
  -webkit-line-clamp: 2; /* Giới hạn chỉ hiển thị 2 dòng */
  -webkit-box-orient: vertical; /* Cần cho -webkit-box-orient */
}

.desktop-icon:hover {
  background: rgba(255, 255, 255, 0.2);
}

.desktop-icon img {
  display: block;
  margin: 0 auto 10px; /* Tăng khoảng cách giữa icon và text */
  width: 48px;
  height: 48px;
}

.taskbar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 40px;
  background: #333;
  color: white;
  display: flex;
  align-items: center;
  padding: 0 10px;
  gap: 10px;
  z-index: 10000;
}

.start-button {
  padding: 4px 12px;
  cursor: pointer;
  font-weight: bold;
  border-radius: 4px;
}

.start-button:hover {
  background: rgba(255, 255, 255, 0.2);
}

.search-input {
  background-color: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 4px;
  color: white;
  padding: 6px 10px;
  font-size: 0.9rem;
  width: 180px; /* Adjust width as needed */
  margin-left: 10px; /* Space from Start button */
}

.search-input::placeholder {
  color: rgba(255, 255, 255, 0.7);
}

.search-input:focus {
  outline: none;
  background-color: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.5);
}


.taskbar-apps {
  display: flex;
  gap: 4px;
  flex: 1;
  overflow-x: auto;
  padding: 4px 0;
}

.taskbar-app {
  position: relative;
  padding: 4px 12px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 4px;
  cursor: pointer;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 6px;
}

.taskbar-app:hover {
  background: rgba(255, 255, 255, 0.25);
}

.taskbar-app.hasWindow::after {
  content: "";
  position: absolute;
  left: 6px;
  right: 6px;
  bottom: 2px;
  height: 2px;
  background: rgba(255, 255, 255, 0.5);
}

.taskbar-app.active {
  background: rgba(255, 255, 255, 0.35);
}

.taskbar-app.active::after {
  background: white;
}

.taskbar-icon {
  width: 16px;
  height: 16px;
}

.clock {
  padding: 4px 8px;
  font-family: monospace;
}

.start-menu {
  position: fixed;
  bottom: 40px;
  left: 0;
  width: 250px; /* Tăng chiều rộng Start Menu để có không gian cho kết quả tìm kiếm */
  background: white;
  border: 1px solid #ccc;
  box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
  z-index: 10001;
  padding-bottom: 10px; /* Thêm padding dưới để tránh sát nút đăng xuất */
}

.start-menu ul {
  margin: 0;
  padding: 5px 0;
  list-style: none;
  max-height: calc(100vh - 150px); /* Giới hạn chiều cao cho list ứng dụng */
  overflow-y: auto; /* Thêm scroll nếu list quá dài */
}

.start-menu li {
  padding: 8px 15px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}

.start-menu li:hover {
  background-color: #f0f0f0;
}

.start-menu-icon {
  width: 16px;
  height: 16px;
}

/* Kiểu cho nút Đăng xuất trong Start Menu */
.logout-button {
  border-top: 1px solid #eee; /* Đường kẻ phân cách */
  margin-top: 5px;
  padding-top: 10px !important;
}

.logout-button:hover {
  background-color: #f0f0f0;
  color: #dc3545; /* Màu đỏ khi hover */
}

/* No results message in Start Menu */
.no-results {
  padding: 10px 15px;
  color: #666;
  font-style: italic;
}


/* Transitions for fade effect */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Loading Overlay Styles */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.85); /* Darker overlay */
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 100000; /* Ensure it's on top of everything */
  color: white;
  flex-direction: column;
  text-align: center;
}

.loading-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.spinner {
  border: 8px solid rgba(255, 255, 255, 0.3);
  border-top: 8贯px solid #fff; /* White spinner */
  border-radius: 50%;
  width: 60px;
  height: 60px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-content p {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

/* Basic Toast styles (for this component's internal use) */
.toast-container {
    z-index: 1050; /* Ensure toast is above other elements */
}
.toast.bg-danger { background-color: #dc3545 !important; }
.toast.bg-success { background-color: #28a745 !important; }
.toast.bg-warning { background-color: #ffc107 !important; color: #333 !important;}
.toast.bg-info { background-color: #17a2b8 !important; }
</style>
