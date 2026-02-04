import { useAuth } from '../composables/useAuth'

export default {
    mounted(el, binding) {
        const { can } = useAuth()
        if (!can(binding.value)) {
            // Store original display style
            el.dataset.originalDisplay = el.style.display
            el.style.display = 'none'
        }
    },
    updated(el, binding) {
        const { can } = useAuth()
        if (!can(binding.value)) {
            el.style.display = 'none'
        } else {
            el.style.display = el.dataset.originalDisplay || ''
        }
    }
}
