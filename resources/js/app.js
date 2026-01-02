import './bootstrap'
import { createApp } from 'vue'
import App from './components/app.vue'
import router from './router'
import axios from 'axios'
import VueApexCharts from 'vue3-apexcharts'

// Axios setup
axios.defaults.baseURL = 'http://127.0.0.1:8000'
axios.defaults.withCredentials = true
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// ✅ Create app FIRST
const app = createApp(App)

// ✅ Register plugins
app.use(router)
app.use(VueApexCharts)

// ✅ Mount LAST
app.mount('#app')
