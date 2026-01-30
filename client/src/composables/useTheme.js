import { ref, onMounted } from 'vue'

const theme = ref(localStorage.getItem('theme') || 'light')

export function useTheme() {
    const toggleTheme = () => {
        theme.value = theme.value === 'light' ? 'dark' : 'light'
        localStorage.setItem('theme', theme.value)
        document.documentElement.setAttribute('data-theme', theme.value)
    }

    onMounted(() => {
        document.documentElement.setAttribute('data-theme', theme.value)
    })

    return {
        theme,
        toggleTheme
    }
}
