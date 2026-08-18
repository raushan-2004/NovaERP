import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(), // Tailwind v4 — no tailwind.config.ts needed
  ],
  server: {
    port: 5173,
    strictPort: true,
  },
});
