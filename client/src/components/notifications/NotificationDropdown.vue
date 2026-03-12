<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Bell, Check, Trash2, FileText, Calendar, User, CheckCheck } from 'lucide-vue-next'
import api from '@/services/api'

const props = defineProps({
  maxItems: {
    type: Number,
    default: 5
  }
})

const router = useRouter()
const notifications = ref([])
const loading = ref(false)
const unreadCount = ref(0)
const filter = ref('all') // 'all' or 'unread'

const fetchNotifications = async () => {
  loading.value = true
  try {
    const params = {}
    if (filter.value === 'unread') {
      params.unread_only = true
    }
    
    const response = await api.get('/notifications', { params })
    // Handle different API response structures (paginated or flat)
    const notificationsData = response.data.data?.data || response.data.data || response.data || []
    
    // Ensure we have an array
    if (Array.isArray(notificationsData)) {
      notifications.value = notificationsData.slice(0, props.maxItems)
      unreadCount.value = response.data.unread_count || notificationsData.filter(n => !n.read_at).length
    } else {
      console.warn('Unexpected notifications response format:', response.data)
      notifications.value = []
      unreadCount.value = 0
    }
  } catch (error) {
    console.error('Failed to fetch notifications:', error)
    notifications.value = []
    unreadCount.value = 0
  } finally {
    loading.value = false
  }
}

const setFilter = (newFilter) => {
  filter.value = newFilter
  fetchNotifications()
}

const fetchUnreadCount = async () => {
  try {
    const response = await api.get('/notifications/unread-count')
    unreadCount.value = response.data.unread_count || response.data.count
  } catch (error) {
    console.error('Failed to fetch unread count:', error)
  }
}

const markAsRead = async (notification) => {
  try {
    await api.post(`/notifications/${notification.id}/read`)
    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch (error) {
    console.error('Failed to mark notification as read:', error)
  }
}

const markAllAsRead = async () => {
  try {
    await api.post('/notifications/mark-all-read')
    notifications.value.forEach(n => n.read_at = new Date().toISOString())
    unreadCount.value = 0
  } catch (error) {
    console.error('Failed to mark all notifications as read:', error)
  }
}

const markAllAsUnread = async () => {
  try {
    const response = await api.post('/notifications/mark-all-unread')
    notifications.value.forEach(n => n.read_at = null)
    unreadCount.value = response.data.unread_count || notifications.value.length
  } catch (error) {
    console.error('Failed to mark all notifications as unread:', error)
  }
}

const deleteNotification = async (id) => {
  try {
    await api.delete(`/notifications/${id}`)
    notifications.value = notifications.value.filter(n => n.id !== id)
  } catch (error) {
    console.error('Failed to delete notification:', error)
  }
}

const getNotificationIcon = (type) => {
  if (type.startsWith('memo')) return FileText
  if (type.startsWith('calendar')) return Calendar
  return Bell
}

const getNotificationColor = (type) => {
  if (type === 'memo.approved') return 'text-success'
  if (type === 'memo.rejected') return 'text-error'
  if (type === 'memo.received') return 'text-info'
  return 'text-base-content'
}

const formatTime = (date) => {
  const d = new Date(date)
  const now = new Date()
  const diff = now - d
  
  if (diff < 60000) return 'Just now'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`
  return d.toLocaleDateString()
}

const handleNotificationClick = async (notification) => {
  // Mark as read first
  await markAsRead(notification)
  
  // Navigate based on notification type
  const data = notification.data || {}
  const role = localStorage.getItem('role') || 'faculty'
  
  if (notification.type.startsWith('memo') && data.memo_id) {
    // Navigate to memos page with memoId query parameter to open the modal
    router.push(`/${role}/memos?memoId=${data.memo_id}`)
  } else if (notification.type.startsWith('calendar') && data.event_id) {
    router.push(`/${role}/calendar?event=${data.event_id}`)
  } else if (notification.link) {
    router.push(notification.link)
  } else if (data.link) {
    router.push(data.link)
  }
}

onMounted(() => {
  fetchNotifications()
})
</script>

<template>
  <div class="dropdown dropdown-end">
    <button tabindex="0" class="btn btn-ghost btn-circle" @click="fetchNotifications">
      <div class="indicator">
        <Bell :size="20" />
        <span 
          v-if="unreadCount > 0"
          class="badge badge-xs badge-error indicator-item animate-pulse"
        >
          {{ unreadCount > 9 ? '9+' : unreadCount }}
        </span>
      </div>
    </button>
    <div 
      tabindex="0" 
      class="dropdown-content z-[100] mt-3 w-80 card card-compact bg-base-100 shadow-xl border border-base-300"
    >
      <div class="card-body">
        <div class="flex flex-col gap-3">
          <div class="flex justify-between items-center">
            <h3 class="font-bold text-lg">Notifications</h3>
              <div class="flex gap-1 items-center">
              <button 
                @click="markAllAsRead"
                class="btn btn-ghost btn-xs text-success"
                title="Mark all as read"
              >
                <CheckCheck :size="14" />
                Read All
              </button>
              <button 
                @click="markAllAsUnread"
                class="btn btn-ghost btn-xs text-warning"
                title="Mark all as unread"
              >
                <Bell :size="14" />
                Unread All
              </button>
            </div>
          </div>

          <!-- Filters -->
          <div class="tabs tabs-boxed tabs-sm bg-base-200 p-0.5">
            <button 
              :class="['tab flex-1 h-8 rounded-md transition-all', filter === 'all' ? 'tab-active bg-primary text-primary-content shadow-sm' : 'hover:bg-base-300']"
              @click="setFilter('all')"
            >
              All
            </button>
            <button 
              :class="['tab flex-1 h-8 rounded-md transition-all', filter === 'unread' ? 'tab-active bg-primary text-primary-content shadow-sm' : 'hover:bg-base-300']"
              @click="setFilter('unread')"
            >
              Unread
              <span v-if="unreadCount > 0" class="badge badge-xs badge-error ml-1">{{ unreadCount }}</span>
            </button>
          </div>
        </div>
        
        <div class="divider my-1"></div>
        
        <!-- Loading State -->
        <div v-if="loading" class="py-8 text-center">
          <span class="loading loading-spinner loading-md"></span>
        </div>
        
        <!-- Empty State -->
        <div 
          v-else-if="notifications.length === 0" 
          class="py-8 text-center"
        >
          <Bell :size="32" class="mx-auto opacity-30 mb-2" />
          <p class="text-sm text-base-content/60">No notifications yet</p>
        </div>
        
        <!-- Notification List -->
        <div 
          v-else 
          class="max-h-96 overflow-y-auto custom-scrollbar"
        >
          <div 
            v-for="notification in notifications" 
            :key="notification.id"
            :class="[
              'notification-item p-3 rounded-lg mb-2 cursor-pointer transition-all hover:bg-base-200',
              !notification.read_at ? 'bg-base-200/50 border-l-4 border-primary' : 'border-l-4 border-transparent'
            ]"
            @click="handleNotificationClick(notification)"
          >
            <div class="flex gap-3">
              <!-- Icon -->
              <div :class="['p-2 rounded-full h-fit self-start flex items-center justify-center', getNotificationColor(notification.type)]">
                <component :is="getNotificationIcon(notification.type)" :size="18" />
              </div>
              
              <!-- Content -->
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase opacity-50 mb-0.5">
                  {{ notification.type.split('.')[0] }}
                </p>
                <p class="text-sm font-medium line-clamp-2 leading-relaxed">
                  {{ notification.data?.message || 'New notification' }}
                </p>
                <p class="text-[10px] text-base-content/50 mt-1 flex items-center gap-1">
                  <span>{{ formatTime(notification.created_at) }}</span>
                  <span v-if="!notification.read_at" class="w-1 h-1 bg-primary rounded-full"></span>
                </p>
              </div>
              
              <!-- Actions -->
              <div class="flex flex-col gap-1 self-center">
                <button 
                  v-if="!notification.read_at"
                  class="btn btn-ghost btn-square btn-xs text-success bg-success/10 hover:bg-success/20"
                  title="Mark as read"
                  @click.stop="markAsRead(notification)"
                >
                  <Check :size="14" />
                </button>
                <button 
                  class="btn btn-ghost btn-square btn-xs text-error bg-error/10 hover:bg-error/20"
                  title="Delete"
                  @click.stop="deleteNotification(notification.id)"
                >
                  <Trash2 :size="14" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notification-item {
  transition: all 0.2s ease;
}

.notification-item:hover {
  transform: translateX(2px);
}

.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: oklch(var(--bc) / 0.2);
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: oklch(var(--bc) / 0.3);
}
</style>
