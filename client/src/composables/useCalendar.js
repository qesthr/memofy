import { ref, computed } from 'vue'
import api from '@/services/api'

// Global state using closure pattern for shared state across components
const selectedDate = ref(new Date())
const currentView = ref('WEEK') // DAY, WEEK, MONTH, YEAR
const timezone = ref('GMT+08')
const weekStart = ref(0) // 0 = Sunday

// Modal State
const showEventModal = ref(false)
const activeEvent = ref(null)

// Google Status
const isGoogleConnected = ref(false)

// Priority Filters
const priorityFilters = ref({
    low: true,
    medium: true,
    high: true
})

// Load filters from localStorage
const savedFilters = localStorage.getItem('calendar_priority_filters')
if (savedFilters) {
    try {
        priorityFilters.value = JSON.parse(savedFilters)
    } catch (e) {
        console.error('Failed to parse saved calendar filters', e)
    }
}

export function useCalendar() {

    const togglePriorityFilter = (priority) => {
        priorityFilters.value[priority] = !priorityFilters.value[priority]
        localStorage.setItem('calendar_priority_filters', JSON.stringify(priorityFilters.value))
    }

    const checkGoogleStatus = async () => {
        try {
            // Assuming 'api' is imported or available globally
            // For this example, we'll mock it or assume it's defined elsewhere
            const { data } = await api.get('/current-user')
            isGoogleConnected.value = !!data.user.google_calendar_token
            // console.warn("API call for checkGoogleStatus is commented out. Please define 'api' and uncomment.")
            // isGoogleConnected.value = false // Placeholder
        } catch (err) {
            console.error('Failed to check Google status:', err)
            isGoogleConnected.value = false
        }
    }

    const openEventModal = (event = null) => {
        activeEvent.value = event
        showEventModal.value = true
    }

    const closeEventModal = () => {
        activeEvent.value = null
        showEventModal.value = false
    }

    const setSelectedDate = (date) => {
        if (typeof date === 'string') {
            selectedDate.value = new Date(date)
        } else {
            selectedDate.value = date
        }
    }

    const setView = (view) => {
        const validViews = ['DAY', 'WEEK', 'MONTH', 'YEAR']
        if (validViews.includes(view.toUpperCase())) {
            currentView.value = view.toUpperCase()
        }
    }

    const next = () => {
        const d = new Date(selectedDate.value)
        if (currentView.value === 'DAY') {
            d.setDate(d.getDate() + 1)
        } else if (currentView.value === 'WEEK') {
            d.setDate(d.getDate() + 7)
        } else if (currentView.value === 'MONTH') {
            d.setMonth(d.getMonth() + 1)
        } else if (currentView.value === 'YEAR') {
            d.setFullYear(d.getFullYear() + 1)
        }
        selectedDate.value = d
    }

    const prev = () => {
        const d = new Date(selectedDate.value)
        if (currentView.value === 'DAY') {
            d.setDate(d.getDate() - 1)
        } else if (currentView.value === 'WEEK') {
            d.setDate(d.getDate() - 7)
        } else if (currentView.value === 'MONTH') {
            d.setMonth(d.getMonth() - 1)
        } else if (currentView.value === 'YEAR') {
            d.setFullYear(d.getFullYear() - 1)
        }
        selectedDate.value = d
    }

    const today = () => {
        selectedDate.value = new Date()
    }

    const formattedDate = (date) => {
        const d = new Date(date)
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    }

    const formattedSelectedDate = computed(() => {
        return formattedDate(selectedDate.value)
    })

    // Helper to get week range [start, end]
    const weekRange = computed(() => {
        const curr = new Date(selectedDate.value)
        const first = curr.getDate() - curr.getDay() // Sunday

        const firstday = new Date(new Date(selectedDate.value).setDate(first))
        const lastday = new Date(new Date(firstday).setDate(firstday.getDate() + 6))

        return {
            start: formattedDate(firstday),
            end: formattedDate(lastday)
        }
    })

    return {
        selectedDate,
        currentView,
        timezone,
        weekStart,
        showEventModal,
        activeEvent,
        openEventModal,
        closeEventModal,
        setSelectedDate,
        setView,
        next,
        prev,
        today,
        formattedSelectedDate,
        formattedDate,
        weekRange,
        isGoogleConnected,
        checkGoogleStatus,
        priorityFilters,
        togglePriorityFilter
    }
}
