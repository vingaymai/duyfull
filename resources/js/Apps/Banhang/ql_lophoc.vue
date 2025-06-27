<template>
  <div class="classroom-management">
    <!-- Fixed Header -->
    <div class="fixed-header">
      <h1>[2024-2025] Quản lý lớp học</h1>
      <div class="action-buttons">
        <button class="btn btn-add" @click="showAddDialog">
          <i class="fas fa-plus"></i> Thêm
        </button>
        <button class="btn btn-edit" :disabled="!selectedClass" @click="showEditDialog">
          <i class="fas fa-edit"></i> Sửa
        </button>
        <button class="btn btn-delete" :disabled="!selectedClass" @click="showDeleteConfirm">
          <i class="fas fa-trash"></i> Xoá
        </button>
        
        <!-- Right-side import/export menu -->
        <div class="import-export-menu">
          <button class="btn btn-export" @click="exportTemplate">
            <i class="fas fa-file-export"></i> Xuất mẫu 1
          </button>
          <button class="btn btn-import" @click="importFromExcel">
            <i class="fas fa-file-import"></i> Nhập từ Excel
          </button>
          <button class="btn btn-reset" @click="resetData">
            <i class="fas fa-sync-alt"></i> Reset
          </button>
        </div>
      </div>
      <!-- Right-side dropdown menu -->
      <div class="import-export-menu">
        <button class="btn btn-export" @click="toggleExportMenu">
          <i class="fas fa-file-export"></i> Nhập/Xuất
        </button>
        <div v-if="showExportMenu" class="export-dropdown">
          <button @click="exportTemplate(1)">
            <i class="fas fa-file-excel"></i> Xuất mẫu 1
          </button>
          <button @click="importFromExcel">
            <i class="fas fa-file-import"></i> Nhập từ Excel
          </button>
          <button @click="resetData">
            <i class="fas fa-sync-alt"></i> Reset
          </button>
        </div>
        
      </div>
    </div>

    <!-- Scrollable Table Content -->
    <div class="table-content">
      <div class="table-container">
        <table class="classroom-table">
          <thead>
            <tr>
              <th>Số TT</th>
              <th>Tên khối</th>
              <th>Tên lớp</th>
              <th>Các lớp ghép với ...</th>
              <th>Sỹ số</th>
              <th>Giáo viên chủ nhiệm</th>
              <th>Mô hình</th>
              <th>KH chủ nhiệm</th>
              <th>Học sinh</th>
              <th>Môn học</th>
              <th>ĐK môn chuyển</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="classroom in displayedClassrooms" :key="classroom.id">
              <td>{{ classroom.id }}</td>
              <td>{{ classroom.gradeName }}</td>
              <td>{{ classroom.className }}</td>
              <td>{{ classroom.combinedClasses || '' }}</td>
              <td>{{ classroom.studentCount }}</td>
              <td>{{ classroom.homeroomTeacher }}</td>
              <td>{{ classroom.model || '' }}</td>
              <td>{{ classroom.khHomeroom || '' }}</td>
              <td>{{ classroom.students || '' }}</td>
              <td>{{ classroom.subjects || '' }}</td>
              <td>{{ classroom.subjectTransferCondition || '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Fixed Pagination Footer -->
    <div class="fixed-footer">
      <div class="page-info">
        <strong>Từ người:</strong>
        <span>Trang {{ currentPage }}</span>
        <span>Của {{ totalPages }}</span>
        <span>Số dòng: {{ totalRecords }}</span>
      </div>
      <div class="record-info">
        Hiển thị {{ startRecord }}-{{ endRecord }} của {{ totalRecords }}
      </div>
    </div>

    <!-- Hidden file input for import -->
    <input 
      type="file" 
      ref="fileInput" 
      style="display: none" 
      accept=".xlsx,.xls,.csv"
      @change="handleFileImport"
    >
  </div>
</template>

<script>
export default {
  name: 'ClassroomManagement',
  data() {
    return {
      currentPage: 1,
      pageSize: 22,
      showExportMenu: false,
      classrooms: [
        { id: 1, gradeName: 'Khối 1', className: '1A', studentCount: 25, homeroomTeacher: 'Bùi Thị Nga' },
        { id: 2, gradeName: 'Khối 1', className: '1B', studentCount: 24, homeroomTeacher: 'Nguyễn Lê Thanh Trang' },
        { id: 3, gradeName: 'Khối 1', className: '1C', studentCount: 24, homeroomTeacher: 'Nguyễn Thị Thanh Nguyệt' },
        { id: 4, gradeName: 'Khối 1', className: '1D', studentCount: 21, homeroomTeacher: 'Phạm Thị Mỹ Dung' },
        { id: 5, gradeName: 'Khối 2', className: '2A', studentCount: 23, homeroomTeacher: 'Bùi Thị Cấm Ly' },
        { id: 6, gradeName: 'Khối 2', className: '2B', studentCount: 26, homeroomTeacher: 'Nguyễn Thị Hằng' },
        { id: 7, gradeName: 'Khối 2', className: '2C', studentCount: 25, homeroomTeacher: 'Đặng Thị Hiếu Hạnh' },
        { id: 8, gradeName: 'Khối 2', className: '2D', studentCount: 27, homeroomTeacher: 'Nguyễn Thị Ngọc Trâm' },
        { id: 9, gradeName: 'Khối 2', className: '2E', studentCount: 18, homeroomTeacher: 'Trần Thị Linh' },
        { id: 10, gradeName: 'Khối 3', className: '3A', studentCount: 28, homeroomTeacher: 'Lê Thị Hạnh' },
        { id: 11, gradeName: 'Khối 3', className: '3B', studentCount: 30, homeroomTeacher: 'Ngô Thị Mỹ Ngọc' },
        { id: 12, gradeName: 'Khối 3', className: '3C', studentCount: 18, homeroomTeacher: 'Nguyễn Thị Rà' },
        { id: 13, gradeName: 'Khối 3', className: '3D', studentCount: 25, homeroomTeacher: 'Vũ Thị Thăm' },
        { id: 14, gradeName: 'Khối 4', className: '4A', studentCount: 27, homeroomTeacher: 'Đinh Danh Phương' },
        { id: 15, gradeName: 'Khối 4', className: '4B', studentCount: 25, homeroomTeacher: 'Mai Thị Thu Thảo' },
        { id: 16, gradeName: 'Khối 4', className: '4C', studentCount: 25, homeroomTeacher: 'Nguyễn Thị Hải' }
      ]
    }
  },
  computed: {
    totalRecords() {
      return this.classrooms.length
    },
    totalPages() {
      return Math.ceil(this.totalRecords / this.pageSize)
    },
    displayedClassrooms() {
      const start = (this.currentPage - 1) * this.pageSize
      const end = start + this.pageSize
      return this.classrooms.slice(start, end)
    },
    startRecord() {
      return (this.currentPage - 1) * this.pageSize + 1
    },
    endRecord() {
      const end = this.currentPage * this.pageSize
      return end > this.totalRecords ? this.totalRecords : end
    }
  },
  methods: {
    toggleExportMenu() {
      this.showExportMenu = !this.showExportMenu
    },
    exportTemplate(type) {
      this.showExportMenu = false
      alert(`Xuất mẫu ${type} thành công!`)
    },
    importFromExcel() {
      this.showExportMenu = false
      this.$refs.fileInput.click()
    },
    handleFileImport(event) {
      const file = event.target.files[0]
      if (file) {
        alert(`Đã chọn file: ${file.name}`)
        event.target.value = ''
      }
    },
    resetData() {
      this.showExportMenu = false
      if (confirm('Bạn có chắc chắn muốn reset dữ liệu về mặc định?')) {
        // In a real app, you would reset to original data
        alert('Dữ liệu đã được reset')
      }
    }
  },
  mounted() {
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!this.$el.contains(e.target)) {
        this.showExportMenu = false
      }
    })
  }
}
</script>

<style scoped>
.classroom-management {
  font-family: Arial, sans-serif;
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}

/* Fixed Header */
.fixed-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  background-color: white;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  z-index: 100;
  position: sticky;
  top: 0;
}

h1 {
  font-size: 18px;
  margin: 0;
  color: #333;
}

/* Table Content Area (Scrollable) */
.table-content {
  flex: 1;
  overflow-y: auto;
  padding: 0 20px;
}

.table-container {
  width: 100%;
  overflow-x: auto;
}

.classroom-table {
  width: 100%;
  border-collapse: collapse;
  margin: 10px 0;
}

.classroom-table th,
.classroom-table td {
  border: 1px solid #ddd;
  padding: 8px;
  text-align: left;
}

.classroom-table th {
  background-color: #f2f2f2;
  font-weight: bold;
  position: sticky;
  top: 0;
}

.classroom-table tr:nth-child(even) {
  background-color: #f9f9f9;
}

.classroom-table tr:hover {
  background-color: #f1f1f1;
}

/* Fixed Footer */
.fixed-footer {
  display: flex;
  justify-content: space-between;
  padding: 10px 20px;
  background-color: white;
  box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
  position: sticky;
  bottom: 0;
  font-size: 14px;
}

.page-info {
  display: flex;
  gap: 10px;
}

.record-info {
  font-style: italic;
}

/* Import/Export Menu */
.import-export-menu {
  position: relative;
}

.btn {
  padding: 8px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
}

.btn-export {
  background-color: #2196F3;
  color: white;
}

.export-dropdown {
  position: absolute;
  right: 0;
  top: 100%;
  background-color: white;
  border: 1px solid #ddd;
  border-radius: 4px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
  z-index: 200;
  min-width: 180px;
}

.export-dropdown button {
  width: 100%;
  padding: 8px 12px;
  text-align: left;
  background: none;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}

.export-dropdown button:hover {
  background-color: #f5f5f5;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .fixed-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
  
  .import-export-menu {
    align-self: flex-end;
  }
  
  .fixed-footer {
    flex-direction: column;
    gap: 5px;
  }
  
  .page-info {
    flex-wrap: wrap;
  }
}


.action-buttons {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.import-export-menu {
  display: flex;
  gap: 10px;
  margin-left: auto;
}

.btn {
  padding: 8px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
}

.btn-add {
  background-color: #4CAF50;
  color: white;
}

.btn-edit {
  background-color: #2196F3;
  color: white;
}

.btn-delete {
  background-color: #f44336;
  color: white;
}

.btn-export {
  background-color: #FF9800;
  color: white;
}

.btn-import {
  background-color: #9C27B0;
  color: white;
}

.btn-reset {
  background-color: #607D8B;
  color: white;
}

.btn:disabled {
  background-color: #cccccc;
  cursor: not-allowed;
}
</style>