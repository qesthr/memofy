import { ref, onMounted } from 'vue'

const theme = ref(localStorage.getItem('theme') || 'light')

export const availableThemes = [
    'light', 'dark', 'cupcake', 'bumblebee', 'emerald', 'corporate',
    'synthwave', 'retro', 'cyberpunk', 'valentine', 'halloween',
    'garden', 'forest', 'aqua', 'lofi', 'pastel', 'fantasy',
    'wireframe', 'black', 'luxury', 'dracula', 'cmyk', 'autumn',
    'business', 'acid', 'lemonade', 'night', 'coffee', 'winter',
    'dim', 'nord', 'sunset'
]

export function useTheme() {
    const setTheme = async (newTheme, sync = true) => {
        theme.value = newTheme
        localStorage.setItem('theme', theme.value)
        document.documentElement.setAttribute('data-theme', theme.value)

        // Sync with backend if logged in
        const token = localStorage.getItem('token')
        if (sync && token) {
            try {
                const api = (await import('@/services/api')).default
                await api.put('/me/theme', { theme: newTheme })
            } catch (err) {
                console.error('Failed to sync theme to backend:', err)
            }
        }
    }

    const toggleTheme = () => {
        const currentIdx = availableThemes.indexOf(theme.value)
        const nextIdx = (currentIdx + 1) % availableThemes.length
        setTheme(availableThemes[nextIdx])
    }

    // Initialize theme from user data if possible
    const initTheme = () => {
        const userStr = localStorage.getItem('user')
        if (userStr) {
            try {
                const user = JSON.parse(userStr)
                if (user.theme && user.theme !== theme.value) {
                    setTheme(user.theme, false) // Initial set, don't sync back
                }
            } catch (err) {
                console.error('Failed to parse user for theme init:', err)
            }
        }
        document.documentElement.setAttribute('data-theme', theme.value)
    }

    onMounted(() => {
        initTheme()
    })

    return {
        theme,
        availableThemes,
        setTheme,
        toggleTheme,
        initTheme
    }
}
