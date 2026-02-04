import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import './utils/axios' // Global axios interceptors

import canDirective from './directives/can'

const app = createApp(App)
app.directive('can', canDirective)
app.use(router)
app.mount('#app')
