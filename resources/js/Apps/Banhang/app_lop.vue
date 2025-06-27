<template>
  <div class="student-management">
    <!-- Thanh công cụ -->
    <div class="toolbar">
      <div class="toolbar-left">
        <h2>Danh sách học sinh: 1A</h2>
      </div>
      <div class="toolbar-right">
        <button class="toolbar-btn" @click="exportData">
          <i class="fas fa-download"></i> Xuất Excel
        </button>
        <button class="toolbar-btn" @click="addStudent">
          <i class="fas fa-plus"></i> Thêm HS
        </button>
        <div class="search-box">
          <input type="text" placeholder="Tìm kiếm..." v-model="searchQuery">
          <i class="fas fa-search"></i>
        </div>
      </div>
    </div>

    <!-- Bảng dữ liệu -->
    <div class="table-container">
      <table class="student-table">
        <thead>
          <tr>
            <th width="50px">
              <input type="checkbox" v-model="selectAll" @change="toggleSelectAll">
            </th>
            <th width="80px" @click="sortBy('stt')">
              STT <i :class="sortIcon('stt')"></i>
            </th>
            <th @click="sortBy('maHS')">
              Mã HS <i :class="sortIcon('maHS')"></i>
            </th>
            <th @click="sortBy('hoTen')">
              Họ và tên <i :class="sortIcon('hoTen')"></i>
            </th>
            <th @click="sortBy('gioiTinh')">
              Giới tính <i :class="sortIcon('gioiTinh')"></i>
            </th>
            <th @click="sortBy('danToc')">
              Dân tộc <i :class="sortIcon('danToc')"></i>
            </th>
            <th @click="sortBy('ngaySinh')">
              Ngày sinh <i :class="sortIcon('ngaySinh')"></i>
            </th>
            <th @click="sortBy('diaChi')">
              Địa chỉ <i :class="sortIcon('diaChi')"></i>
            </th>
            <th width="120px">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="(student, index) in filteredStudents" 
            :key="student.maHS"
            @mouseover="hoveredRow = index"
            @mouseleave="hoveredRow = null"
            :class="{ 'hovered': hoveredRow === index, 'selected': selectedStudents.includes(student.maHS) }"
          >
            <td>
              <input 
                type="checkbox" 
                v-model="selectedStudents" 
                :value="student.maHS"
              >
            </td>
            <td>{{ index + 1 }}</td>
            <td>{{ student.maHS }}</td>
            <td class="student-name">{{ student.hoTen }}</td>
            <td>{{ student.gioiTinh }}</td>
            <td>{{ student.danToc }}</td>
            <td>{{ formatDate(student.ngaySinh) }}</td>
            <td>{{ student.diaChi }}</td>
            <td class="actions">
              <button class="edit-btn" @click="editStudent(student)">
                <i class="fas fa-edit"></i>
              </button>
              <button class="delete-btn" @click="deleteStudent(student.maHS)">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Phân trang -->
    <div class="pagination">
      <div class="pagination-info">
        Hiển thị {{ currentPage * itemsPerPage - itemsPerPage + 1 }} - 
        {{ Math.min(currentPage * itemsPerPage, students.length) }} của {{ students.length }}
      </div>
      <div class="pagination-controls">
        <button 
          @click="prevPage" 
          :disabled="currentPage === 1"
          class="page-btn"
        >
          <i class="fas fa-chevron-left"></i>
        </button>
        <span class="page-number">{{ currentPage }}</span>
        <button 
          @click="nextPage" 
          :disabled="currentPage * itemsPerPage >= students.length"
          class="page-btn"
        >
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      students: [
        {
          maHS: "2404617829",
          hoTen: "ĐẢNG NGUYÊN ANH",
          gioiTinh: "Nữ",
          danToc: "Kinh",
          ngaySinh: "08/08/2018",
          diaChi: "Thôn 3, Xã Tân Tiểu..."
        },
        {
          maHS: "066318014352",
          hoTen: "LƯƠNG HỒNG ANH",
          gioiTinh: "Nữ",
          danToc: "Kinh",
          ngaySinh: "22/02/2018",
          diaChi: "Thôn 5 xã tân tiểu..."
        },
        // Thêm các học sinh khác từ dữ liệu của bạn
      ],
      searchQuery: "",
      selectedStudents: [],
      selectAll: false,
      hoveredRow: null,
      currentPage: 1,
      itemsPerPage: 10,
      sortField: "",
      sortDirection: "asc"
    }
  },
  computed: {
    filteredStudents() {
      let filtered = this.students;
      
      // Lọc theo từ khóa tìm kiếm
      if (this.searchQuery) {
        const query = this.searchQuery.toLowerCase();
        filtered = filtered.filter(student => 
          student.hoTen.toLowerCase().includes(query) ||
          student.maHS.toLowerCase().includes(query) ||
          student.diaChi.toLowerCase().includes(query)
        );
      }
      
      // Sắp xếp
      if (this.sortField) {
        filtered = filtered.sort((a, b) => {
          let modifier = 1;
          if (this.sortDirection === 'desc') modifier = -1;
          
          if (a[this.sortField] < b[this.sortField]) return -1 * modifier;
          if (a[this.sortField] > b[this.sortField]) return 1 * modifier;
          return 0;
        });
      }
      
      // Phân trang
      const start = (this.currentPage - 1) * this.itemsPerPage;
      const end = start + this.itemsPerPage;
      return filtered.slice(start, end);
    }
  },
  methods: {
    sortBy(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortField = field;
        this.sortDirection = 'asc';
      }
    },
    sortIcon(field) {
      if (this.sortField !== field) return 'fas fa-sort';
      return this.sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
    },
    toggleSelectAll() {
      if (this.selectAll) {
        this.selectedStudents = this.filteredStudents.map(student => student.maHS);
      } else {
        this.selectedStudents = [];
      }
    },
    formatDate(date) {
      return date; // Có thể thêm logic định dạng ngày tháng nếu cần
    },
    addStudent() {
      // Logic thêm học sinh mới
      console.log("Thêm học sinh mới");
    },
    editStudent(student) {
      // Logic sửa thông tin học sinh
      console.log("Sửa thông tin:", student);
    },
    deleteStudent(maHS) {
      // Logic xóa học sinh
      console.log("Xóa học sinh có mã:", maHS);
    },
    exportData() {
      // Logic xuất dữ liệu
      console.log("Xuất dữ liệu Excel");
    },
    prevPage() {
      if (this.currentPage > 1) this.currentPage--;
    },
    nextPage() {
      if (this.currentPage * this.itemsPerPage < this.students.length) {
        this.currentPage++;
      }
    }
  }
}
</script>

<style scoped>
.student-management {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f5f7fa;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid #e0e0e0;
}

.toolbar h2 {
  color: #2c3e50;
  margin: 0;
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 15px;
}

.toolbar-btn {
  background-color: #3498db;
  color: white;
  border: none;
  padding: 8px 15px;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
  transition: background-color 0.3s;
}

.toolbar-btn:hover {
  background-color: #2980b9;
}

.search-box {
  position: relative;
}

.search-box input {
  padding: 8px 15px 8px 35px;
  border: 1px solid #ddd;
  border-radius: 4px;
  width: 200px;
}

.search-box i {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #7f8c8d;
}

.table-container {
  overflow-x: auto;
  background-color: white;
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.student-table {
  width: 100%;
  border-collapse: collapse;
}

.student-table th, .student-table td {
  padding: 12px 15px;
  text-align: left;
  border-bottom: 1px solid #e0e0e0;
}

.student-table th {
  background-color: #f8f9fa;
  color: #2c3e50;
  font-weight: 600;
  cursor: pointer;
  user-select: none;
}

.student-table th:hover {
  background-color: #e9ecef;
}

.student-table tbody tr.hovered {
  background-color: #f8f9fa;
}

.student-table tbody tr.selected {
  background-color: #e3f2fd;
}

.student-table input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.student-name {
  font-weight: 500;
  color: #2c3e50;
}

.actions {
  display: flex;
  gap: 8px;
}

.edit-btn, .delete-btn {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.3s;
}

.edit-btn {
  background-color: #f39c12;
  color: white;
}

.edit-btn:hover {
  background-color: #e67e22;
}

.delete-btn {
  background-color: #e74c3c;
  color: white;
}

.delete-btn:hover {
  background-color: #c0392b;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px solid #e0e0e0;
}

.pagination-info {
  color: #7f8c8d;
  font-size: 14px;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-btn {
  width: 32px;
  height: 32px;
  border-radius: 4px;
  border: 1px solid #ddd;
  background-color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-btn:hover:not(:disabled) {
  background-color: #f8f9fa;
}

.page-number {
  font-weight: 500;
  min-width: 20px;
  text-align: center;
}
</style>