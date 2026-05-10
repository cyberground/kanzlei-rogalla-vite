import { defineConfig } from 'astro/config';

export default defineConfig({
  site: 'https://kanzlei-rogalla.de',
  output: 'static',
  build: {
    format: 'directory'
  }
});
