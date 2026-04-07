# Project Mind Map

This project is a static university website organized around shared page layouts, a single main stylesheet, and a lightweight JavaScript layer.

```text
William Chi-Chi University Website
|
|-- 1. Main Visitor Experience
|   |
|   |-- index.html
|   |   Home page
|   |   - Brand introduction
|   |   - Philosophy snapshot
|   |   - Academics preview
|   |   - Campus preview
|   |   - News preview
|   |
|   |-- about.html
|   |   About page
|   |   - Philosophy
|   |   - Motto
|   |   - Crest language
|   |   - President message
|   |
|   |-- campus.html
|   |   Campus page
|   |   - Environment
|   |   - Daily rhythm
|   |   - Signature spaces
|   |
|   |-- research.html
|   |   Research page
|   |   - Research labs
|   |   - Innovation model
|   |   - Research highlights
|   |
|   `-- news.html
|       News page
|       - University updates
|       - Announcement feed
|
|-- 2. Academics Branch
|   |
|   |-- academics.html
|   |   Main academics hub
|   |   - Overviews
|   |   - Fields of study
|   |   - Colleges & schools
|   |   - Academic resources
|   |   - Policies & support
|   |   - Continue education
|   |
|   `-- School detail pages
|       |-- school-mathematics-and-computer-science.html
|       |-- school-engineering-and-natural-science.html
|       |-- school-interdisciplinary-studies.html
|       |-- school-art-and-literature.html
|       |-- school-business-and-management.html
|       `-- school-humanities-and-social-science.html
|           Shared structure on each school page:
|           hero -> overview -> programs -> labs/opportunities
|
|-- 3. Admissions Branch
|   |
|   |-- admissions.html
|   |   Admissions overview
|   |   - Application process
|   |   - Required materials
|   |   - Important dates
|   |   - Selection principles
|   |   - CTA to apply
|   |
|   `-- apply.html
|       Application intake page
|       - Applicant information form
|       - Program selection
|       - Personal statement
|       - Portfolio/sample link
|       - Frontend-only validation
|
|-- 4. Shared Frontend System
|   |
|   |-- styles.css
|   |   - Global tokens: colors, spacing, width, radius
|   |   - Shared header and footer
|   |   - Hero sections
|   |   - Cards, grids, lists, buttons, forms
|   |   - Page-specific styles for academics, admissions,
|   |     application layout, and school pages
|   |
|   `-- script.js
|       - Loading screen fade-out
|       - Mobile menu toggle
|       - Scroll reveal animation
|       - Apply form validation + success message
|
`-- 5. Important Development Notes
    |
    |-- The site is mostly static HTML
    |   Most content changes happen directly in page files
    |
    |-- styles.css is the main visual control center
    |   Layout fixes usually belong there first
    |
    |-- academics.html has one extra inline script
    |   It highlights the academic sub-navigation on scroll
    |
    `-- apply.html is not connected to a backend
        Submission is simulated in the browser only
```

## Quick Reading Instructions

1. `index.html` to understand the homepage tone and structure.
2. `academics.html` to understand the biggest content hub.
3. The six `school-*.html` pages to see the repeated school-page pattern.
4. `styles.css` to understand the shared design system and layout rules.
5. `script.js` and the inline script in `academics.html` for interaction behavior.

## Current structure
```text
.
|-- index.html
|-- README.md
|-- assets/
|   |-- css/
|   |   `-- styles.css
|   `-- js/
|       `-- script.js
|-- docs/
|   |-- White-Paper.doc
|   `-- mindmap.md
`-- pages/
    |-- about.html
    |-- academics.html
    |-- admissions.html
    |-- apply.html
    |-- campus.html
    |-- news.html
    |-- research.html
    `-- schools/
        |-- school-art-and-literature.html
        |-- school-business-and-management.html
        |-- school-mathematics-and-computer-science.html
        |-- school-engineering-and-natural-science.html
        |-- school-humanities-and-social-science.html
        `-- school-interdisciplinary-studies.html
```

## Notes

- `index.html` remains at the repo root as the main entry page.
- Shared styles and scripts now live under `assets/`.
- General content pages now live under `pages/`.
- School detail pages now live under `pages/schools/`.
- `docs/` stores non-deploy web materials such as planning notes or white papers.
- Internal links and asset paths were updated to match the new layout.

