import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

/**
 * Docs are ordered by an explicit `order` field rather than by filename,
 * so pages can be reordered without renaming files and breaking URLs.
 */
const docs = defineCollection({
  loader: glob({ pattern: '**/*.{md,mdx}', base: './src/content/docs' }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
    order: z.number(),
  }),
});

const blog = defineCollection({
  loader: glob({ pattern: '**/*.{md,mdx}', base: './src/content/blog' }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
    date: z.coerce.date(),
    author: z.string().default('Olmbox'),
    // Lets a post be committed and reviewed before it is published,
    // which is the content gate the workflow needs (PRD GR3).
    draft: z.boolean().default(false),
  }),
});

export const collections = { docs, blog };
