# William Chichi University

## Welcome to William CHi-CHi University

William Chi-Chi University is an innovative institution dedicated to cultivating independent thinkers and real-world problem solvers. The university believes that education should go beyond exams and focus on developing the ability to apply knowledge meaningfully.

At William Chi-Chi University, learning extends far beyond the classroom. Through project-based learning, interdisciplinary collaboration, and continuous exploration, students are encouraged to question assumptions, challenge conventional answers, and build their own understanding through experience.

Guided by the motto, "ultra examina," the university does not reject evaluation, but rather redefines its purpose. Grades are not the ultimate measure of success. What truly matters is critical thinking, creativity, and the ability to make an impact.

William Chi-Chi University strives to create an open, dynamic, and intellectually challenging environment where students are empowered not just to fit into the world, but to shape it.

## Project Structure

```text
.
|-- index.html
|-- assets/
|   |-- css/
|   |   `-- styles.css
|   `-- js/
|       `-- script.js
|-- docs/
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

## Reference Docs

- [Go to the White paper](docs/White-Paper.doc)
- Further management in improving the website and adding new features is tracked in the white paper.
- [Project Mind Map](docs/mindmap.md)

## Notes

- `index.html` remains at the repo root as the main entry page.
- Shared styles and scripts now live under `assets/`.
- General content pages now live under `pages/`.
- School detail pages now live under `pages/schools/`.
- `docs/` stores non-deploy materials such as planning notes and white papers.
- Internal links and asset paths were updated to match the new layout.

## Deployment

- For `Cloudflare Pages`, run `npm run build` and publish the `dist/` directory.
- The static build includes `index.html`, `assets/`, and HTML pages only. Legacy PHP files are excluded from the Cloudflare output.
- The admissions backend for Oracle VM now lives under `server/`.
- Set the frontend API origin in `assets/js/site-config.js` before publishing the application form.
- The main public entry remains `index.html`.
