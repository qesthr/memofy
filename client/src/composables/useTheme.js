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
    const setTheme = (newTheme) => {
        theme.value = newTheme
        localStorage.setItem('theme', theme.value)
        document.documentElement.setAttribute('data-theme', theme.value)
    }

    const toggleTheme = () => {
        const currentIdx = availableThemes.indexOf(theme.value)
        const nextIdx = (currentIdx + 1) % availableThemes.length
        setTheme(availableThemes[nextIdx])
    }

    onMounted(() => {
        document.documentElement.setAttribute('data-theme', theme.value)
    })

    return {
        theme,
        availableThemes,
        setTheme,
        toggleTheme
    }
}
