import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/scss/app.scss',
        'resources/js/app.js',
        'resources/js/main.ts',
      ],
      refresh: true,
    }),
    vue(),
  ],
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    sourcemap: process.env.VITE_BUILD_SOURCEMAP === 'true',
    minify: 'esbuild',
    target: 'es2020',
    cssMinify: true,
    esbuild: {
      drop: process.env.VITE_DROP_CONSOLE === 'true' ? ['console', 'debugger'] : [],
    },
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return;
          }

          if (id.includes('vue') || id.includes('pinia') || id.includes('vue-router')) {
            return 'vendor-vue';
          }

          if (id.includes('vue-i18n')) {
            return 'vendor-i18n';
          }

          if (id.includes('chart.js') || id.includes('vue-chartjs')) {
            return 'vendor-charts';
          }

          if (id.includes('laravel-echo') || id.includes('pusher-js')) {
            return 'vendor-realtime';
          }
        },
      },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    watch: {
      usePolling: true,
      interval: 1500,
      ignored: [
        '**/node_modules/**',
        '**/vendor/**',
        '**/storage/**',
        '**/public/build/**',
      ],
    },
    hmr: {
      host: 'localhost',
      port: 5173,
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['resources/js/**/*.spec.ts'],
  },
});
