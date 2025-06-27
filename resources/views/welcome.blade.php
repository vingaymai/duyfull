<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  
  <title>Windows 11 Style UId</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI Variable', 'Segoe UI', sans-serif;
      background: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1920&q=80') no-repeat center center fixed;
      background-size: cover;
      color: #000;
    }
    .desktop-icon {
      position: absolute;
      width: 80px;
      text-align: center;
      cursor: pointer;
      color: white;
      text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.7);
    }
    .desktop-icon img {
      width: 48px;
      height: 48px;
      filter: drop-shadow(0 0 3px rgba(0, 0, 0, 0.4));
    }
    .window {
      position: absolute;
      width: 66vw;
      height: 66vh;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(255, 255, 255, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.5);
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-radius: 16px;
      display: none;
      overflow: hidden;
      z-index: 10;
    }
    .window-header {
      background: rgba(30, 30, 30, 0.4);
      color: white;
      padding: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-weight: 500;
      font-size: 16px;
      cursor: move;
    }
    .window-header .controls button {
      background: none;
      border: none;
      color: white;
      font-weight: bold;
      margin-left: 8px;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.2s ease;
    }
    .window-header .controls button:hover {
      background: rgba(255, 255, 255, 0.2);
      border-radius: 4px;
    }
    .window-content {
      padding: 12px;
      overflow: auto;
      height: calc(100% - 48px);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: rgba(255, 255, 255, 0.7);
      border-radius: 10px;
      overflow: hidden;
    }
    th, td {
      border: 1px solid rgba(200, 200, 200, 0.6);
      padding: 10px;
      text-align: left;
    }
    .taskbar {
      position: fixed;
      bottom: 0;
      width: 100%;
      height: 48px;
      background: rgba(255, 255, 255, 0.3);
      display: flex;
      align-items: center;
      backdrop-filter: blur(12px);
      border-top: 1px solid rgba(255, 255, 255, 0.4);
      box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.15);
    }
    .start-button {
      background: rgba(255, 255, 255, 0.2);
      padding: 6px 16px;
      margin-left: 8px;
      cursor: pointer;
      border-radius: 8px;
      font-weight: 500;
      transition: background 0.3s;
    }
    .start-button:hover {
      background: rgba(255, 255, 255, 0.4);
    }
    .taskbar-apps {
      display: flex;
      flex-grow: 1;
      justify-content: center;
      gap: 8px;
    }
    .taskbar-app {
      background-color: rgba(255, 255, 255, 0.6);
      color: #000;
      padding: 4px 12px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .clock {
      margin-right: 16px;
      font-weight: 500;
      font-size: 14px;
    }
    .start-menu {
      position: absolute;
      bottom: 48px;
      left: 10px;
      background: rgba(255, 255, 255, 0.9);
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.25);
      padding: 12px;
      display: none;
      min-width: 180px;
    }
    .start-menu ul {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .start-menu li {
      padding: 10px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 15px;
    }
    .start-menu li:hover {
      background-color: rgba(0, 0, 0, 0.05);
    }
  </style>
</head>
<body>
  <!-- Desktop Icons -->
  <div class="desktop-icon" style="top: 50px; left: 50px;" onclick="openWindow('sales')">
    <img src="https://icons.iconarchive.com/icons/paomedia/small-n-flat/48/shop-icon.png" alt="Sales">
    <div>Bán hàng</div>
  </div>
  <div class="desktop-icon" style="top: 50px; left: 130px;" onclick="openWindow('news')">
    <img src="https://icons.iconarchive.com/icons/dtafalonso/android-l/48/News-icon.png" alt="News">
    <div>Tin tức</div>
  </div>
  <div class="desktop-icon" style="top: 50px; left: 210px;" onclick="openWindow('inventory')">
    <img src="https://icons.iconarchive.com/icons/graphicloads/colorful-long-shadow/48/Box-icon.png" alt="Inventory">
    <div>Kho hàng</div>
  </div>

  <!-- Sales Window -->
  <div class="window" id="sales">
    <div class="window-header" onmousedown="startDrag(event, 'sales')">
      <span class="title">Bán hàng</span>
      <div class="controls">
        <button onclick="minimizeWindow('sales')">_</button>
        <button onclick="toggleMaximize('sales')">▢</button>
        <button onclick="closeWindow('sales')">X</button>
      </div>
    </div>
    <div class="window-content">
      <h3>Bảng dữ liệu bán hàng</h3>
      <table>
        <thead>
          <tr>
            <th>Sản phẩm</th>
            <th>Số lượng</th>
            <th>Giá</th>
            <th>Thành tiền</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>Bánh mì</td><td>20</td><td>1.500</td><td>30.000</td></tr>
          <tr><td>Sữa tươi</td><td>10</td><td>12.000</td><td>120.000</td></tr>
          <tr><td>Nước ngọt</td><td>5</td><td>8.000</td><td>40.000</td></tr>
        </tbody>
      </table>
      <p><strong>Tổng cộng:</strong> <span id="total"></span> VNĐ</p>
    </div>
  </div>

  <!-- News Window -->
  <div class="window" id="news">
    <div class="window-header" onmousedown="startDrag(event, 'news')">
      <span class="title">Tin tức</span>
      <div class="controls">
        <button onclick="minimizeWindow('news')">_</button>
        <button onclick="toggleMaximize('news')">▢</button>
        <button onclick="closeWindow('news')">X</button>
      </div>
    </div>
    <div class="window-content">
      <h3>Tin tức mới nhất</h3>
      <ul>
        <li>Bộ Giáo dục công bố lịch thi tốt nghiệp THPT năm 2025.</li>
        <li>Giá xăng dầu tiếp tục điều chỉnh tăng mạnh.</li>
        <li>Thời tiết nắng nóng kéo dài tại các tỉnh phía Nam.</li>
      </ul>
    </div>
  </div>

  <!-- Inventory Window -->
  <div class="window" id="inventory">
    <div class="window-header" onmousedown="startDrag(event, 'inventory')">
      <span class="title">Kho hàng</span>
      <div class="controls">
        <button onclick="minimizeWindow('inventory')">_</button>
        <button onclick="toggleMaximize('inventory')">▢</button>
        <button onclick="closeWindow('inventory')">X</button>
      </div>
    </div>
    <div class="window-content">
      <h3>Danh mục hàng tồn kho</h3>
      <table>
        <thead>
          <tr>
            <th>Mã hàng</th>
            <th>Tên hàng</th>
            <th>Tồn kho</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>SP001</td><td>Bánh mì</td><td>200</td></tr>
          <tr><td>SP002</td><td>Sữa tươi</td><td>100</td></tr>
          <tr><td>SP003</td><td>Nước ngọt</td><td>150</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Taskbar and Start Menu -->
  <div class="taskbar">
    <div class="start-button" onclick="toggleStartMenu()">Start</div>
    <div class="taskbar-apps" id="taskbar-apps"></div>
    <div class="clock" id="clock"></div>
  </div>
  <div class="start-menu" id="startMenu">
    <ul>
      <li onclick="openWindow('sales')">Bán hàng</li>
      <li onclick="openWindow('news')">Tin tức</li>
      <li onclick="openWindow('inventory')">Kho hàng</li>
    </ul>
  </div>

  <script>
    let zIndex = 10;
    function openWindow(id) {
      const win = document.getElementById(id);
      if (win) {
        win.style.display = 'block';
        win.style.zIndex = ++zIndex;

        if (!document.getElementById('task-' + id)) {
          const task = document.createElement('div');
          task.className = 'taskbar-app';
          task.id = 'task-' + id;
          task.innerText = document.querySelector(`#${id} .title`).innerText;
          task.onclick = () => toggleWindow(id);
          document.getElementById('taskbar-apps').appendChild(task);
        }
      }
    }

    function toggleWindow(id) {
      const win = document.getElementById(id);
      if (win.style.display === 'none') {
        win.style.display = 'block';
        win.style.zIndex = ++zIndex;
      } else {
        win.style.display = 'none';
      }
    }

    function minimizeWindow(id) {
      document.getElementById(id).style.display = 'none';
    }

    function toggleMaximize(id) {
      const win = document.getElementById(id);
      if (win.classList.contains('maximized')) {
        win.style.width = '66vw';
        win.style.height = '66vh';
        win.style.top = '50%';
        win.style.left = '50%';
        win.style.transform = 'translate(-50%, -50%)';
        win.classList.remove('maximized');
      } else {
        win.style.top = '0';
        win.style.left = '0';
        win.style.transform = 'none';
        win.style.width = '100vw';
        win.style.height = 'calc(100vh - 40px)';
        win.classList.add('maximized');
      }
    }

    function closeWindow(id) {
      document.getElementById(id).style.display = 'none';
      const task = document.getElementById('task-' + id);
      if (task) task.remove();
    }

    let offsetX = 0, offsetY = 0, dragging = null;
    function startDrag(e, id) {
      const win = document.getElementById(id);
      if (win.classList.contains('maximized')) return;
      dragging = win;
      offsetX = e.clientX - win.offsetLeft;
      offsetY = e.clientY - win.offsetTop;
      document.onmousemove = drag;
      document.onmouseup = () => { dragging = null; document.onmousemove = null; };
    }

    function drag(e) {
      if (dragging) {
        dragging.style.left = (e.clientX - offsetX) + 'px';
        dragging.style.top = (e.clientY - offsetY) + 'px';
      }
    }

    function updateClock() {
      const now = new Date();
      const time = now.toLocaleTimeString();
      document.getElementById('clock').innerText = time;
    }

    function calculateTotal() {
      let total = 0;
      document.querySelectorAll('#sales table tbody tr').forEach(row => {
        const cell = row.cells[3];
        const value = parseInt(cell.textContent.replace(/\D/g, ''), 10);
        total += value;
      });
      document.getElementById('total').innerText = total.toLocaleString();
    }

    function toggleStartMenu() {
      const menu = document.getElementById('startMenu');
      menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }

    setInterval(updateClock, 1000);
    updateClock();
    calculateTotal();
  </script>
</body>
</html>
