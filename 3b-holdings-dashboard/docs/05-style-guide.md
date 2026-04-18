# Style Guide

## Direction

**No strong visual preference.** Claude Design should make the call based on what feels appropriate for a professional property-management tool.

Things to optimize for:

- **Legibility.** Sans-serif body, clear size hierarchy, enough line-height to not feel cramped.
- **Data-friendly.** Numbers need to be easy to scan. Consider tabular-number fonts (e.g., `font-variant-numeric: tabular-nums`) for columns of money.
- **Desktop-first, responsive.** Primary use is desktop; phone must still work but doesn't need to look great on every widget.
- **Calm.** Minimal decoration, no stock photography, no heavy shadows. This is a working tool, not a marketing site.

## Constraints

- **Colors.** Neutral palette (white/gray/black or a muted warm alternative) with **one** accent color. Use shadcn/ui's CSS variable system (already configured in `src/index.css`). If you change the theme, change the variables — don't hard-code colors.
- **Typography.** Tailwind defaults are fine (`font-sans`). If a custom font is chosen, explain why.
- **Dark mode.** Ship both modes. CSS variables for light + dark are already stubbed in `src/index.css`.
- **Icons.** Use `lucide-react` (the shadcn default). Keep icon sizing consistent (16px inline, 20–24px for headers).
- **Spacing.** Tailwind spacing scale. Default padding on cards 4–6, default gap 4.

## Anti-patterns to avoid

- Hero sections with big empty backgrounds. This is a dashboard, not a homepage.
- Gradients, glows, or other decorative effects.
- Mixing multiple fonts. One sans-serif for body + optional monospace for code/numbers is plenty.
- Emojis in UI chrome.
- Aggressive colors (neon, saturated) — the exception is a single accent for primary CTAs.

## What Claude Design has freedom on

- Exact color palette (within neutral + one accent).
- Exact typography choice.
- Exact layout for the home dashboard widgets (grid, split, hero — whatever reads best).
- Component density (compact vs. comfortable).
- Whether tabs on the property detail page are top tabs, side tabs, or a different pattern entirely.
