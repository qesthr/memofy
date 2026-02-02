import { ref, watch } from 'vue'
import api from '@/services/api'
import { useCalendar } from './useCalendar'

export function useEvents() {
    const { selectedDate, currentView, weekRange } = useCalendar()
    const events = ref([])
    const isLoading = ref(false)
    const error = ref(null)

    const fetchEvents = async () => {
        isLoading.value = true
        error.value = null

        try {
            let start, end

            const toDateStr = (date) => {
                const d = new Date(date)
                return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
            }

            if (currentView.value === 'DAY') {
                start = toDateStr(selectedDate.value)
                end = start
            } else if (currentView.value === 'WEEK') {
                start = weekRange.value.start
                end = weekRange.value.end
            } else if (currentView.value === 'MONTH') {
                const d = new Date(selectedDate.value)
                start = toDateStr(new Date(d.getFullYear(), d.getMonth(), 1))
                end = toDateStr(new Date(d.getFullYear(), d.getMonth() + 1, 0))
            } else {
                start = `${selectedDate.value.getFullYear()}-01-01`
                end = `${selectedDate.value.getFullYear()}-12-31`
            }

            const response = await api.get('/calendar/events', {
                params: { start, end }
            })

            events.value = response.data.events || []
        } catch (err) {
            console.error('Failed to fetch events:', err)
            error.value = 'Failed to load events'
        } finally {
            isLoading.value = false
        }
    }

    // Refetch when date, view, or Google status changes
    const { isGoogleConnected } = useCalendar()
    watch([selectedDate, currentView, isGoogleConnected], () => {
        fetchEvents()
    }, { immediate: true })

    return {
        events,
        isLoading,
        error,
        fetchEvents
    }
}
