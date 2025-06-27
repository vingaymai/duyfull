<template>
  <div
    class="window"
    :style="windowStyles"
    v-show="visible && !minimized"
    @mousedown="handleWindowMouseDown"
  >
    <div class="window-header" @mousedown="startDrag">
      <span class="window-title">{{ title }}</span>
      <div class="window-controls">
        <button @click.stop="toggleMinimize" title="Minimize">_</button>
        <button @click.stop="toggleMaximize" title="Maximize">□</button>
        <button @click.stop="handleClose" title="Close">✕</button>
      </div>
    </div>

    <div class="window-body">
      <!-- Kiểm tra quyền trước khi render component -->
      <template v-if="hasPermission">
        <component 
          :is="component" 
          :user="user" 
          :user-permissions="userPermissions" 
        />
      </template>
      <template v-else>
        <div class="text-center text-danger p-4">
          Bạn không có quyền truy cập ứng dụng này.
        </div>
      </template>
    </div>

    <div class="resize-handle" @mousedown.stop="startResize"></div>
  </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'

const props = defineProps({
  title: String,
  component: Object,
  zIndex: Number,
  visible: Boolean,
  minimized: Boolean,
  user: Object, 
  userPermissions: Array 
})

const emit = defineEmits(['close', 'minimize', 'focus', 'bring-to-front'])

// Tính quyền
const componentId = props.component?.name || ''
const permissionName = 'access_app_' + componentId.toLowerCase()

const hasPermission = computed(() => {
  return props.userPermissions?.includes(permissionName)
})

// Window state
const windowState = ref({
  top: 50,
  left: 50,
  width: Math.floor(window.innerWidth * 0.8),
  height: Math.floor(window.innerHeight * 0.8),
  isDragging: false,
  isResizing: false,
  dragOffset: { x: 0, y: 0 },
  resizeOffset: { x: 0, y: 0, width: 0, height: 0 },
  maximized: false,
  originalDimensions: {
    top: 50,
    left: 50,
    width: Math.floor(window.innerWidth * 0.8),
    height: Math.floor(window.innerHeight * 0.6),
  }
})

const windowStyles = computed(() => ({
  top: windowState.value.maximized ? '0' : `${windowState.value.top}px`,
  left: windowState.value.maximized ? '0' : `${windowState.value.left}px`,
  width: windowState.value.maximized ? '100%' : `${windowState.value.width}px`,
  height: windowState.value.maximized ? 'calc(100% - 40px)' : `${windowState.value.height}px`,
  zIndex: props.zIndex,
  display: props.minimized ? 'none' : 'flex'
}))

const bringToFront = () => emit('bring-to-front')
const handleWindowMouseDown = () => bringToFront()
const handleClose = () => emit('close')
const toggleMinimize = () => emit('minimize')

const startDrag = (e) => {
  if (windowState.value.maximized) return
  windowState.value.isDragging = true
  windowState.value.dragOffset = {
    x: e.clientX - windowState.value.left,
    y: e.clientY - windowState.value.top
  }
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', stopDrag)
}

const onDrag = (e) => {
  if (!windowState.value.isDragging) return
  windowState.value.left = e.clientX - windowState.value.dragOffset.x
  windowState.value.top = e.clientY - windowState.value.dragOffset.y
}

const stopDrag = () => {
  windowState.value.isDragging = false
  removeDragListeners()
}

const removeDragListeners = () => {
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDrag)
}

const startResize = (e) => {
  if (windowState.value.maximized) return
  windowState.value.isResizing = true
  windowState.value.resizeOffset = {
    x: e.clientX,
    y: e.clientY,
    width: windowState.value.width,
    height: windowState.value.height
  }
  document.addEventListener('mousemove', onResize)
  document.addEventListener('mouseup', stopResize)
}

const onResize = (e) => {
  if (!windowState.value.isResizing) return
  const minWidth = 300
  const minHeight = 200
  windowState.value.width = Math.max(minWidth, windowState.value.resizeOffset.width + (e.clientX - windowState.value.resizeOffset.x))
  windowState.value.height = Math.max(minHeight, windowState.value.resizeOffset.height + (e.clientY - windowState.value.resizeOffset.y))
}

const stopResize = () => {
  windowState.value.isResizing = false
  removeResizeListeners()
}

const removeResizeListeners = () => {
  document.removeEventListener('mousemove', onResize)
  document.removeEventListener('mouseup', stopResize)
}

const toggleMaximize = () => {
  if (!windowState.value.maximized) {
    windowState.value.originalDimensions = {
      top: windowState.value.top,
      left: windowState.value.left,
      width: windowState.value.width,
      height: windowState.value.height
    }
    windowState.value.top = 0
    windowState.value.left = 0
  } else {
    windowState.value.top = windowState.value.originalDimensions.top
    windowState.value.left = windowState.value.originalDimensions.left
    windowState.value.width = windowState.value.originalDimensions.width
    windowState.value.height = windowState.value.originalDimensions.height
  }
  windowState.value.maximized = !windowState.value.maximized
}

onBeforeUnmount(() => {
  removeDragListeners()
  removeResizeListeners()
})
</script>

<style scoped>
@media (max-width: 768px) {
  .window {
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    border-radius: 0;
  }

  .title-bar {
    font-size: 16px;
  }

  .content {
    padding: 5px;
  }
}

.window {
  position: absolute;
  background: #fff;
  border: 1px solid #888;
  border-radius: 6px 6px 0 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-width: 300px;
  min-height: 200px;
}

.window-header {
  background: linear-gradient(to right, #0078d7, #0066c0);
  color: white;
  padding: 6px 8px;
  cursor: move;
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 32px;
  user-select: none;
}

.window-title {
  flex: 1;
  padding-left: 4px;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.window-controls {
  display: flex;
  gap: 4px;
}

.window-controls button {
  width: 24px;
  height: 22px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  border-radius: 3px;
  color: white;
  cursor: pointer;
  font-size: 12px;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.window-controls button:hover {
  background: rgba(255, 255, 255, 0.2);
}

.window-body {
  flex: 1;
  overflow: auto;
  padding: 10px;
  background: #f5f5f5;
}

.resize-handle {
  position: absolute;
  width: 12px;
  height: 12px;
  bottom: 0;
  right: 0;
  cursor: nwse-resize;
  background: linear-gradient(135deg, #ddd 45%, transparent 45%);
}
</style>
