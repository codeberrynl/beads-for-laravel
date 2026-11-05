import '@fontsource-variable/inter'
import 'vue-sonner/style.css'
import './css/app.css'
import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router'

createApp(App).use(router).mount('#beads-app')
