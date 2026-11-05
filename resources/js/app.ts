import '@fontsource-variable/inter'
import 'vue-sonner/style.css'
import './css/app.css'
import { createApp } from 'vue'
import { VueQueryPlugin, type VueQueryPluginOptions } from '@tanstack/vue-query'
import App from './App.vue'
import { router } from './router'
import { ApiError } from './lib/api'

const query: VueQueryPluginOptions = {
  queryClientConfig: {
    defaultOptions: {
      queries: {
        // Only network hiccups are retried; bd errors are shown as they are.
        retry: (count, error) => error instanceof ApiError && error.status === 0 && count < 2,
        refetchOnWindowFocus: false,
      },
    },
  },
}

createApp(App).use(router).use(VueQueryPlugin, query).mount('#beads-app')
