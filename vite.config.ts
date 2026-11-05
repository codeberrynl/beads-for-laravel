import { fileURLToPath, URL } from 'node:url'
import { writeFileSync, rmSync } from 'node:fs'
import { resolve } from 'node:path'
import { defineConfig, type Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

const hotFile = resolve(__dirname, 'hot')

/**
 * Writes the dev server URL to ./hot while `npm run dev` runs, so the
 * Blade shell loads the app from Vite (HMR) instead of the published build.
 */
function hotFilePlugin(): Plugin {
  return {
    name: 'beads-hot-file',
    apply: 'serve',
    configureServer(server) {
      server.httpServer?.once('listening', () => {
        const address = server.httpServer?.address()
        const port = typeof address === 'object' && address ? address.port : server.config.server.port
        writeFileSync(hotFile, `http://localhost:${port}`)
      })
      const clean = () => {
        rmSync(hotFile, { force: true })
        process.exit()
      }
      process.once('SIGINT', clean)
      process.once('SIGTERM', clean)
      server.httpServer?.once('close', () => rmSync(hotFile, { force: true }))
    },
  }
}

export default defineConfig({
  // Relative URLs: the build works wherever public/vendor/beads is served.
  base: './',
  plugins: [vue(), tailwindcss(), hotFilePlugin()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  publicDir: false,
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    assetsDir: 'assets',
    rollupOptions: {
      input: 'resources/js/app.ts',
    },
  },
  server: {
    port: 5183,
    strictPort: true,
    origin: 'http://localhost:5183',
    cors: {
      origin: /^https?:\/\/(?:(?:[^:]+\.)?localhost|127\.0\.0\.1|\[::1\]|[^:]+\.test)(?::\d+)?$/,
    },
  },
})
