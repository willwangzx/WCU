# UI Design Patterns

This document records the shared user interface and visual design patterns for
the William Chichi University website. Keep it updated when the homepage,
content pages, forum entry points, or reusable visual system changes.

## Design Intent

The WCU website should feel academic, clear, modern, and project-based. It
should communicate a living university system rather than a generic marketing
site. The visual language should be calm enough for admissions and institutional
trust, but active enough to support the "Way Beyond Exams" idea.

Use this direction as the default:

- Lead with the university name, real learning activity, and a clear next step.
- Prefer open layouts, section bands, rails, and lists over heavy card stacks.
- Use imagery to show students, campus, projects, collaboration, or outcomes.
- Keep copy specific, concise, and institutional.
- Avoid decorative filler, fake interface chrome, and repeated marketing blocks.

## Core Tokens

The canonical tokens live in `assets/css/styles.css`.

```css
:root {
  --bg: #f7f8fb;
  --surface: #ffffff;
  --text: #0b1f3a;
  --muted: #44556d;
  --line: #d6dde8;
  --accent: #1f5dff;
  --wcu-gold: #b88a2d;
  --header-bg: rgba(247, 248, 251, 0.88);
  --radius: 14px;
  --max: 1180px;
}
```

Use `--surface` as the main page background. Use `--bg` for quiet alternate
areas and admin or form surfaces. Use `--accent` for primary actions and links.
Use `--wcu-gold` as a restrained institutional accent, not as the main theme.

New homepage-style repeated cards should use an 8px radius unless they are
matching an older page pattern that already uses `--radius`.

## Typography

- Use `Inter` for body text, navigation, buttons, headings, forms, and dense UI.
- Use `Playfair Display` only for heritage marks or special brand moments, such
  as the circular `W` mark.
- Do not use negative letter spacing. Keep `letter-spacing: 0` for homepage
  headings and section copy unless a small navigation label needs slight
  spacing.
- Hero headings may be large and tight. Compact panels, cards, form sections,
  and forum rows should use smaller headings that fit their container.
- Body copy should usually sit around `1rem` with line height between `1.55`
  and `1.7`.

## Page Structure

The root homepage pattern is:

1. Sticky header with brand, primary navigation, and one main action.
2. Image-led hero with the university name, core promise, and primary actions.
3. Philosophy section using open rows and small icon support.
4. Dark academic rail for program discovery.
5. Forum band that introduces the WordPress/wpForo community hub.
6. Admissions process strip with compact numbered steps.
7. Research and news preview list.
8. Simple footer with key links.

Content pages should reuse the same header, footer, color tokens, spacing, and
button behavior. They may use lighter page hero treatments, but should still
feel related to the homepage.

## Layout Rules

- Use `--max` for the main content width and the existing responsive side
  padding pattern:

```css
padding-left: max(24px, calc((100vw - var(--max)) / 2 + 24px));
padding-right: max(24px, calc((100vw - var(--max)) / 2 + 24px));
```

- Prefer full-width section bands with constrained inner content.
- Do not put page sections inside large floating cards.
- Do not place cards inside cards.
- Use grid rails for repeated information that should scan horizontally on
  desktop and collapse cleanly on mobile.
- Maintain stable dimensions for toolbars, numbered steps, cards, media frames,
  stats, and icon blocks so hover states and text do not resize the layout.

## Hero Pattern

The homepage hero should remain a first-viewport brand signal:

- The H1 is the university name.
- The core value line is supporting text, not an eyebrow or badge.
- The primary media is a real or generated bitmap image in `assets/img/`.
- The hero image may use edge fades to keep text readable, but should not be
  washed out by a colored overlay.
- The next section should be hinted at on common desktop and mobile viewports.

Do not use a split text-and-card hero or an abstract SVG/gradient hero for the
main page.

## Navigation And Actions

- Keep the header quiet and predictable.
- Primary navigation belongs in `.site-nav`.
- Keep one clear header CTA, currently `Apply Now`.
- Use `.btn.btn-solid` for the primary action.
- Use `.btn.btn-outline` for secondary actions.
- Use text links only for lower-emphasis navigation or contextual actions.
- Button text must stay short enough to fit on mobile.

## Section Patterns

### Philosophy Rows

Use open rows with small icons and short text. Icons should be simple stroke SVG
symbols with consistent weight and alignment.

### Academic Rail

Use a dark blue full-width rail for program discovery. Program links should read
as compact academic options, not product cards.

### Forum Band

The forum band introduces the WordPress/wpForo forum and links to
the static `pages/forum.html` entrance page. That page links onward to
`https://forum.wcuedu.net/community/`. Position it as a hybrid community hub
for student projects, academic Q&A, shared notes, tools, activities, and
finding collaborators.

The production WordPress/wpForo forum uses the `server/wordpress/themes/wcu-forum/`
child theme. Keep its header, footer, type, buttons, forum rows, login form, and
registration form aligned with the main site tokens above. The theme should load
late wpForo overrides instead of editing plugin files.

Forum numbers must be real production data or real seeded demo data. Prefer
non-numeric focus areas over pre-launch counts, and do not invent public
metrics.

### Admissions Path

Use a compact high-contrast strip for process steps. Each step should have a
number, a short label, and a short support line.

### News Preview

Use simple cards only for repeated article previews. Keep dates, titles, and
links readable. Avoid making the entire page a card grid.

## Imagery

- Store shared images in `assets/img/`.
- Prefer images that show the actual subject: campus, students, learning,
  projects, events, research, or the forum/community experience.
- Generated images are acceptable for design development if they are polished,
  relevant, and committed as project assets.
- Do not reference local-only files, external hotlinked images, or temporary QA
  screenshots in production HTML.
- Avoid dark, blurred, overly cropped, stock-like, or purely atmospheric images
  when users need to understand the university offer.

## Forms, Admin, And Operational UI

Admissions forms, admin screens, and future forum management views should be
more utilitarian than the homepage:

- Use clear labels, predictable field order, and restrained spacing.
- Use tables or dense lists where comparison matters.
- Keep destructive actions visibly distinct and confirmable.
- Preserve readable focus states and validation messages.
- Avoid oversized hero typography inside operational tools.

## Responsive Behavior

- Desktop rails may use 3 or 4 columns.
- Tablet views should collapse to 2 columns when content starts to feel tight.
- Mobile views should collapse to 1 column unless the item is very small.
- Do not allow horizontal overflow.
- Header navigation should use the existing menu toggle pattern on small screens.
- Text should wrap naturally and never overlap buttons, media, or neighboring
  cards.

## Accessibility

- Use semantic landmarks: `header`, `main`, `section`, `nav`, and `footer`.
- Connect sections to headings with `aria-labelledby` when the section needs a
  named region.
- Use `aria-label` for icon-only or visually compact interaction groups.
- Maintain visible keyboard focus states.
- Do not hide essential content behind hover-only behavior.
- Keep color contrast strong, especially on blue rails and action strips.
- Decorative images and icons should use `aria-hidden="true"` when appropriate.

## Copy Rules

- Use direct institutional language.
- Keep headings short and concrete.
- Avoid lorem ipsum, vague marketing claims, and fake rankings.
- Do not expose internal implementation details in visible page copy.
- Public statistics, forum counts, dates, and claims must be verifiable or
  clearly treated as pre-launch/sample content during development.

## Do And Do Not

Do:

- Use the existing tokens and shared CSS patterns.
- Keep the homepage image-led and brand-forward.
- Use section bands and rails for strong page rhythm.
- Keep cards reserved for repeated items, not page sections.
- Verify desktop and mobile screenshots after visual changes.

Do not:

- Create nested cards or floating page-section wrappers.
- Build hero sections from abstract gradients or SVG decorations.
- Add decorative badges, pills, or eyebrow labels above the homepage H1.
- Let text scale directly with viewport width outside controlled `clamp()`
  ranges already used in the CSS.
- Add new palettes that turn the site into a one-color theme.

## Before Shipping Visual Changes

Run this checklist before merging UI or visual work:

- `npm run build`
- `.\scripts\run-tests.ps1`
- Check desktop and mobile renderings in a browser.
- Confirm referenced assets load from committed paths.
- Check for horizontal overflow, overlapping text, and clipped buttons.
- Confirm public counts, dates, and claims are real or marked as sample content.
- Update this document when a reusable UI pattern changes.
