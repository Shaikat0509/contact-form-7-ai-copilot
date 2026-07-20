/**
 * Site-wide constants.
 *
 * Facts about the plugin live here rather than being retyped into each
 * page, because they have to stay true to readme.txt on `main`. When the
 * plugin's requirements or provider list change, this is the one place
 * the site needs updating.
 */

export const SITE_TITLE = 'Olmbox';
export const SITE_TAGLINE = 'AI Inbox for Contact Form 7';
export const SITE_DESCRIPTION =
  'Every Contact Form 7 submission gets an AI-drafted summary, suggested reply, category and priority — in an inbox you review. Nothing is ever emailed to a visitor automatically.';

export const PLUGIN_SLUG = 'olmbox-ai-inbox-for-contact-form-7';
export const GITHUB_URL = 'https://github.com/Shaikat0509/contact-form-7-ai-copilot';
export const WP_ORG_URL = `https://wordpress.org/plugins/${PLUGIN_SLUG}/`;

/** Mirrors the headers in the plugin's readme.txt. */
export const REQUIREMENTS = {
  wordpress: '6.8',
  php: '8.1',
  testedUpTo: '7.0',
  dependency: 'Contact Form 7',
} as const;

export const PROVIDERS = [
  { name: 'OpenAI', note: 'GPT models' },
  { name: 'Anthropic', note: 'Claude models' },
  { name: 'Google Gemini', note: 'Gemini models' },
  { name: 'OpenRouter', note: 'Routes to many models' },
] as const;

export const NAV = [
  { label: 'Features', href: '/#features' },
  { label: 'Docs', href: '/docs' },
  { label: 'Blog', href: '/blog' },
] as const;
