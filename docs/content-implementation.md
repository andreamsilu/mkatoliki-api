# Catholic content implementation handoff

The source implementation extends the existing Laravel API, Next.js portal and Flutter app. It introduces no new infrastructure. Runtime verification and activation remain pending under the user's no-run instruction.

## User-facing behavior

- Guests can browse eight Catholic categories, search and filter published content, read daily reflections, change text size, share links and play authorized recordings.
- Kiswahili is the default content language; English is available when an editor supplies a translation. Missing translations are not invented.
- Active accounts can save and remove their own bookmarks. Anonymous reports enter the editorial review queue.
- The portal exposes `/faith` publicly and `/content` to authorized editors. Saved web items appear at `/faith?saved=1`.
- The mobile drawer, parish home and parish-selection screen open **Imani na Maisha**. Its public feed supports pagination and a bounded, clearly labeled offline summary cache.
- Editorial records use a table with Lucide action buttons. Creation, editing, previews and workflow actions stay in wide modals on the listing page.

## Editorial rules

Content follows draft → in review → approved → published → archived. Rejection returns it to draft. A different account must approve the author's work. Publishing can happen immediately or through the existing minute scheduler. Saving changes withdraws any published version and clears approval.

Text and media carry source and rights metadata. Unconfirmed rights block publication. Private draft media previews require authorization; anonymous media streaming requires a visible published post. Uploaded images are limited to 5 MiB and 6000×6000 pixels; audio to 20 MiB. Daily reflection reservations are unique per date and language. Deleting a draft releases its reservations.

No sample Catholic articles, Scripture readings, prayers, lyrics or recordings are published by the seeders. Only category definitions and role permissions are added.

## Principal source files

API files are relative to `D:\msilu\mkatoliki-api`:

- `database/migrations/2026_10_15_000001_create_catholic_content_tables.php`: content, translations, categories, tags, media, review history, daily reservations, bookmarks and reports.
- `app/Models/Content*.php`, `app/Models/DailyReflection.php`: relationships and public visibility scope.
- `app/Services/ContentPublishing.php`, `app/Policies/ContentPostPolicy.php`, `app/Http/Requests/SaveContentRequest.php`: workflow, permissions, ownership, validation and cache invalidation.
- `app/Http/Controllers/Api/V1/CatholicContentController.php`, `ContentEditorialController.php`, `app/Http/Resources/ContentPostResource.php`, `routes/content.php`: public and administrative endpoints with explicit public response fields.
- `app/Http/Middleware/RequireActiveContentReader.php`, `ApiRequestContext.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`: existing account checks, privacy-aware logging, policies and rate limits.
- `database/seeders/ContentCategorySeeder.php`, `AccessControlSeeder.php`, `DatabaseSeeder.php`, `routes/console.php`, `config/content.php`, `.env.example`: activation and scheduling.
- `docs/api/content.openapi.json`, `docs/api/openapi.json`, `docs/api/consumer-guide.md`, `routes/web.php`: API contracts and documentation delivery.
- `docker/nginx/default.conf`, `docker/php/production.ini`: multipart upload ceilings.

Portal files are relative to `D:\msilu\mkatolik-portal`:

- `src/app/[locale]/(admin)/content/page.tsx` and `src/components/content/`: table workspace, modal editor, full reviewer preview, taxonomy/media forms, audio player and reader controls.
- `src/app/[locale]/(full-width-pages)/faith/page.tsx`, `faith/[slug]/page.tsx`: anonymous feed, saved list and reading pages.
- `src/modules/content/types.ts`, `actions.ts`: typed server-side integration; bearer credentials stay on the server.
- `src/app/api/content-media/[id]/route.ts`, `src/lib/api/client.ts`: authenticated streaming proxy for draft previews.
- `src/proxy.ts`, `src/layout/AppSidebar.tsx`, `src/messages/en.json`, `next.config.ts`: public route access, navigation, labels and upload request size.

Mobile files are relative to `D:\msilu\mkatolik_app`:

- `lib/features/content/domain/content_repository.dart`, `data/api_content_repository.dart`: API models, pagination, bookmarks and public summary cache.
- `lib/features/content/presentation/screens/content_feed_screen.dart`, `content_detail_screen.dart`, `presentation/widgets/content_card.dart`, `content_audio_player.dart`: feed, reader, playback and sharing.
- Existing app scope, routes, drawer, home and hierarchy selection connect the feature. `lib/core/network/api_client.dart` adds GET pagination and DELETE support.
- `pubspec.yaml` declares the approved `just_audio` and `share_plus` dependencies. Dependency installation and lockfile refresh are pending.

## Verification status

Source files were read and reviewed. No app, build, test, formatter, static-analysis, package-installation, migration or seeder command was executed. No database records were changed. The new automated tests have not passed verification yet because they have not been run.

Test sources added or updated:

- API `tests/Feature/CatholicContentTest.php`: public visibility, filters, translations, pagination, independent review, role/ownership restrictions, daily uniqueness, private bookmarks, scheduling, cache withdrawal, media rights, authenticated previews, reporting and log privacy.
- API `tests/Feature/ApiInfrastructureTest.php`: route coverage includes the referenced content OpenAPI contract; existing infrastructure checks remain.
- API `tests/Feature/TecDirectorySeederTest.php`: updated permission totals and category definitions.
- API `database/factories/ContentPostFactory.php`: test-only content fixtures.
- Mobile `test/content_repository_test.dart`: pagination, public offline fallback and isolation from other locales/private bookmarks.

## Activation still required

Use the existing deployment process to install mobile dependencies, apply the new migration and run `AccessControlSeeder` and `ContentCategorySeeder`. Review pending migrations first. Configure canonical externally reachable HTTPS `APP_URL` and the portal origin in `CONTENT_WEB_URL` (without `/faith`). Refresh cached configuration, keep the existing scheduler active and reload services for the upload ceilings. The public content rate defaults to 240 requests/minute/IP and is configurable with `CONTENT_PUBLIC_RATE_LIMIT`; media and reports have separate limits.

Before release, run the relevant API tests on the dedicated disposable `catholic_api_test` database, PHP formatting/static checks, portal checks/build, mobile dependency resolution/analysis/tests/build, then verify a complete author → independent reviewer → publish flow on a device. Existing core tests should also run to check regressions. The API test suite uses `RefreshDatabase`; it must never target a live database.

Runtime compilation, dependency resolution, device playback, native sharing, deployment and regression behavior remain unverified. The source implementation alone is not a production-readiness claim.
