import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import { createGtag } from 'vue-gtag'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler } from 'chart.js'

import canDirective from './directives/can'

// Register Chart.js components globally
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
)

const app = createApp(App)
app.directive('can', canDirective)
app.use(router)

app.use(createGtag({
    config: { id: 'G-507754558' },
    pageTracker: {
        router
    }
}))

app.mount('#app')
