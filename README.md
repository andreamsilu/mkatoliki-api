# Catholic Tanzania Core API

Laravel 13 / PHP 8.3 API implementing [documentation.md](documentation.md), backed by MySQL 8 and Redis. The hierarchy is **Jimbo Kuu → Jimbo → Dekania → Parokia**, with optional Kigango associations and **Kanda → Jumuiya → Familia → Waumini** community relationships.

## Run locally

Install PHP 8.3+ with `pdo_mysql`, `mbstring`, `xml`, `intl`, `zip`, and `redis`, plus Composer, MySQL 8 and Redis.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set the MySQL and Redis credentials in `.env`, create the database with `utf8mb4_unicode_ci`, then run:

```bash
php artisan migrate --seed
php artisan core:create-admin admin@example.org --name="TEC Administrator" --role=super_admin
php artisan serve
```

The administrator command prompts for a password without exposing it in shell history. No default accounts are seeded. Scoped accounts use `--role=diocesan_admin|deanery_admin|parish_admin --scope=ID`.

`php artisan db:seed` loads and publishes the TEC 2020 baseline: 7 ecclesiastical provinces, 34 dioceses (including 7 archdioceses), 45 explicitly named deaneries, and 304 explicitly listed parishes. Parishes without a documented deanery assignment are loaded with `deanery_id: null` and can be assigned later. Reseeding preserves existing IDs, corrections, and transfers.

The [versioned seed data](database/seeders/tec-directory-2020.json) retains printed page references, source URLs, and coverage gaps. Parish mappings cover Arusha, Kahama, Moshi, Sumbawanga, Tabora, and Tanga. The remaining 28 dioceses need reviewed parish-to-deanery mappings before their parishes can be loaded. Dar es Salaam's TEC section lists deaneries and religious communities, not a complete parish mapping. No kigango is created automatically, and chaplaincies, institutions, and quasi parishes are excluded. This is a historical baseline, not a claim that the 2020 hierarchy remains current.

- API: `http://localhost:8000/api/v1`
- Swagger UI: `http://localhost:8000/swagger` (use **Authorize** with a bearer token to try protected endpoints).
- Consumer documentation: `http://localhost:8000/docs` ([web page](docs/api/index.html), [Markdown guide](docs/api/consumer-guide.md), and [publishing instructions](docs/api/README.md)).
- OpenAPI: `http://localhost:8000/api/openapi.json` ([contract](docs/api/openapi.json)); import into Swagger UI, Postman, or an OpenAPI client generator.
- Readiness: `http://localhost:8000/health` checks MySQL and the configured cache; `/up` is Laravel's liveness check.

## Authentication and access

`POST /api/v1/auth/login` with `email`, `password`, and `device_name` returns an eight-hour bearer token. Send `Authorization: Bearer <token>` on protected requests. `GET /auth/me` returns the current administrator and `POST /auth/logout` revokes the current token.

Public listings, detail, search, parish context, and parish structure return **active** organizational records whose primary ancestors are also active. Personal contact information and administrative descriptions are excluded. Public responses never include families or members, even when the caller supplies a token.

`POST /families/search` and `POST /members/search` provide authenticated filtered lists; their collection URLs create records, and their ID URLs support POST reads plus PUT and PATCH updates. All twelve entity types follow the same pattern under `/api/v1/admin`. National administrators manage the full directory; diocesan, deanery and parish administrators are limited to their assigned organizations. The role, permission, token ability, and organization must all permit the action. Accounts without a required scope have no record access.

Use status changes (`inactive`, `suppressed`, etc.) to retire records. DELETE is intentionally unavailable. PUT and PATCH both accept partial updates. Codes are uppercase and globally unique within each entity type. Pagination defaults to 25, with a maximum of 100. Send `page`, `per_page`, `q`, status, and ancestor ID filters in JSON to the applicable POST search endpoint. Structure responses contain at most 100 records per type and include totals plus a POST search request for larger structures.

## Provenance and imports

Create source records under `/api/v1/admin/data-sources` to retain where information came from. Active records publish immediately, and authorized administrators can correct them later without a separate verification step. Family and member data remains private.

Parish moves must use `POST /admin/parishes/{id}/transfer` with `new_deanery_id`, `source_id`, `effective_date`, and `reason`. Moves are atomic and retain the previous deanery and diocese. Read `/admin/parishes/{id}/history` for transfer history. Other organizations with dependent records cannot be reparented through ordinary updates.

Imports accept normalized JSON in batches of up to 500 records. They validate existing parent IDs, normalize strings and codes, report invalid rows, and preserve source provenance. See [the import workflow](docs/data/import.md). The original Tanzania Catholic Directory PDF is not bundled; the reviewed seed transcription includes only organizational names and documented hierarchy links. Additional imports require source extraction and review before staging.

## Tests and quality checks

```bash
php artisan test
vendor/bin/pint
composer validate --strict
composer audit --locked
```

The default suite uses MySQL and the dedicated `catholic_api_test` database configured in `phpunit.xml`. Create this **empty, dedicated test database** and configure its connection credentials in `.env.testing` or pass them when running the tests:

```bash
DB_USERNAME=catholic_api_test DB_PASSWORD=your-test-password php vendor/bin/phpunit
```

`RefreshDatabase` recreates tables in the selected test database. Never point tests at development or production data. GitLab CI uses MySQL 8.

## Deployment and operations

See [Docker deployment](docs/deployment/production.md), [architecture and security](docs/architecture/overview.md), and [operations](docs/operations/runbook.md). Docker Compose includes PHP-FPM, Nginx, MySQL, Redis, a queue worker and the scheduler. GitLab CI validates code, runs MySQL tests, audits dependencies, builds an image, and provides manual deployment jobs. Production needs host configuration, TLS, secret provisioning, an off-host backup destination, and monitoring before going live.
