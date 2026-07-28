import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import AppPortal from './AppPortal.vue'
import router from './router/portal'

const app = createApp(AppPortal)

app.use(createPinia())
app.use(router)

app.mount('#app')
