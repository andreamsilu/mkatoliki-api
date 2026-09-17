# Publishing the API documentation

The consumer documentation is available as a [web page](index.html), a [Markdown guide](consumer-guide.md), and an [OpenAPI 3.0.3 contract](openapi.json).

## Preview

In the running application, visit `/docs` (locally, `http://localhost:8000/docs`). It redirects to `/docs/index.html`, with working Markdown and OpenAPI download links. The API home response also exposes this URL as `consumer_documentation`.

Open `docs/api/index.html` in a browser, or serve the documentation folder from the repository root:

```bash
python3 -m http.server 8080 --directory docs/api
```

Visit `http://localhost:8080`. The page includes the guide, searchable endpoint reference, request/response schemas, and copy buttons. The guide and reference remain readable without JavaScript. Printing uses a simplified layout; expand any endpoint details you want to print first.

## Publish

Upload these three files together to any static web host, preserving their filenames:

- `index.html`
- `consumer-guide.md`
- `openapi.json`

The generated page has no external fonts, CDN scripts, runtime dependencies, analytics, or live API calls. It works at the root or in a subdirectory. Use the documentation host's URL when sharing it with consumers. You can serve the same bundle through the application's `/docs` route or publish it separately.

The guide deliberately uses `https://YOUR_API_HOST/api/v1` because no production origin has been supplied. Before publication, replace that placeholder in `consumer-guide.md` with the actual API base URL and rebuild. The OpenAPI server `/api/v1` resolves relative to the API origin; set it to the actual absolute API base URL if distributing the contract from a separate documentation domain and you want tools to infer the host automatically. Do not publish real credentials or replace illustrative records with private member data.

## Update and rebuild

Edit `consumer-guide.md` for prose and examples, and `openapi.json` for the operation contract. Run from the repository root:

```bash
node docs/api/build.mjs
```

The builder uses Node.js and the project's installed PHP/Composer `league/commonmark` dependency to render Markdown. Run `composer install` first if project dependencies are absent. It generates `index.html` directly from the guide and OpenAPI, so the web page and downloadable sources stay aligned. No npm dependency installation or Vite build is needed.

Rebuild after either source changes. Check API routes, request validators, resources, and authorization behavior when changing the contract. Public visibility, nullable relationships, and partial `PUT` updates are part of the consumer contract.
