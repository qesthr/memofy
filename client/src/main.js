import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import './utils/axios' // Global axios interceptors
import { createGtag } from 'vue-gtag'

import canDirective from './directives/can'

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
