import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
  build: {
    outDir: 'dist',
    manifest: true,
    rollupOptions: {
      input: {
        app: 'src/js/app.js',
      },
    },
  },
  server: {
    origin: 'http://localhost:5173',
  },
});
