import { defineConfig } from "vite";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    proxy: {
      "/api": {
        target: "http://ordem-de-servico.test",
        changeOrigin: true,
      },
      "/docs": {
        target: "http://ordem-de-servico.test",
        changeOrigin: true,
      },
    },
  },
});
