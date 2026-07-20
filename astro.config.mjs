// @ts-check
import { defineConfig } from 'astro/config';
import mdx from '@astrojs/mdx';
import sitemap from '@astrojs/sitemap';
import tailwindcss from '@tailwindcss/vite';

// The canonical origin, used for sitemap and canonical URLs. Change this
// (and nothing else) when a custom domain is attached in Cloudflare —
// keeping the host out of the components is what makes NFR5's "swappable
// deploy target" true rather than aspirational.
const SITE = process.env.SITE_URL ?? 'https://olmbox.pages.dev';

export default defineConfig({
  site: SITE,
  integrations: [mdx(), sitemap()],
  vite: {
    plugins: [tailwindcss()],
  },
  build: {
    // Emit /docs/installation.html rather than /docs/installation/index.html.
    // Cloudflare Pages serves both, but flat files keep the deployed tree
    // readable when debugging what actually shipped.
    format: 'file',
  },
});
