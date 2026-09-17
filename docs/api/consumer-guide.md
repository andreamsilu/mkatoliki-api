# Catholic Tanzania Core API

API v1 · Consumer guide · Contract version 1.0.0

Build applications that browse Tanzania's Catholic directory and, with administrator access, manage organizational and community records. Swahili names are primary; selected resources also provide an optional English name.

The API uses JSON over HTTP. Public directory reads need no credentials. Family, member, and administrative endpoints require a bearer token and the appropriate organizational access.

## Quick start

Obtain the API origin from your provider. The versioned base URL is **`https://YOUR_API_HOST/api/v1`**. For a local installation it is `http://localhost:8000/api/v1`. The host below is a placeholder; example IDs, codes, names, credentials, and timestamps are illustrative.

```bash
export API_BASE_URL='https://YOUR_API_HOST/api/v1'

curl --fail-with-body --silent --show-error \
  "$API_BASE_URL/provinces/search" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data '{"per_page":25}'
```

A successful empty directory is a valid response:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 0,
    "last_page": 1
  }
}
```

Next, use the returned IDs to browse `/provinces/{id}/dioceses`, `/dioceses/{id}/deaneries`, and `/deaneries/{id}/parishes`. Fetch `/parishes/{id}/context` for a parish's ancestors or `/parishes/{id}/structure` for its organizations.

All endpoint paths in this guide are relative to `/api/v1`, except where an origin-level path is explicitly stated. The [OpenAPI specification](openapi.json) describes individual operations and schemas. The running API also serves it at the origin-level path `/api/openapi.json`.

## Directory concepts

| Resource path | Meaning | Swahili term | Access to reads |
| --- | --- | --- | --- |
| `/provinces` | Ecclesiastical provinces | Jimbo Kuu | Public |
| `/dioceses` | Dioceses and archdioceses | Jimbo | Public |
| `/deaneries` | Deaneries | Dekania | Public |
| `/parishes` | Parishes | Parokia | Public |
| `/outstations` | Outstations | Kigango | Public |
| `/zones` | Parish zones | Kanda | Public |
| `/jumuiyas` | Small Christian communities | Jumuiya | Public |
| `/families` | Families | Familia | Authenticated |
| `/members` | Individual members | Waumini | Authenticated |
| `/associations` | Parish associations | — | Public |
| `/choirs` | Parish choirs | — | Public |
| `/ministries` | Parish ministries | — | Public |

The main directory follows **province → diocese → deanery → parish**. Outstations and zones belong to a parish. A jumuiya requires both a parish and a zone. Zones and jumuiyas may also link to an outstation. Families and members require a parish; their other community links are optional. Associations, choirs, and ministries belong to a parish and may link to an outstation.

Use numeric IDs from API responses for relationships and detail URLs. Codes are unique within each resource type, not across all types. A parish can have no assigned deanery in administrative data; such a parish is absent from the public directory until its hierarchy is assigned.

### What becomes public

A public organization must have `status: "active"`. Its primary ancestors must also be active. For jumuiyas, visibility follows the zone and its parish; for other parish organizations, visibility follows the parish. Optional outstation links do not independently control visibility.

Public responses omit `phone`, `email`, `address`, and `description`. Families and members never appear in public lists, search, or parish structure. Supplying a token to a public endpoint does not change its fields or visibility; use `/admin/{entity}` for administrative reads.

An empty list does not prove that the institution has no records. Records may be inactive, outside a filter, or hidden by an ancestor. A hidden detail resource returns `404`. Historical source data is not a guarantee of current coverage; use source metadata and update records when newer information is available.

## Requests and responses

Send `Accept: application/json`. For JSON request bodies, also send `Content-Type: application/json`. Protected endpoints require `Authorization: Bearer YOUR_TOKEN`.

Success responses have `success`, `data`, and `meta`. `data` is an object for details and an array for collections. Non-paginated responses normally use `meta: {}`; parish structure uses additional metadata.

Example `POST /parishes/42` response (`200 OK`):

```json
{
  "success": true,
  "data": {
    "id": 42,
    "deanery_id": 7,
    "code": "DEMO-PARISH",
    "name": "Parokia ya Mfano",
    "name_en": "Example Parish",
    "latitude": null,
    "longitude": null,
    "status": "active",
    "source_id": 3,
    "created_at": "2026-09-15T07:00:00.000000Z",
    "updated_at": "2026-09-15T08:00:00.000000Z"
  },
  "meta": {}
}
```

Dates such as `date_of_birth`, `established_at`, and `effective_date` use `YYYY-MM-DD`. Timestamps use ISO 8601 date-time strings; parse their timezone offsets. Nullable values can be JSON `null`. Coordinates are numbers when present. Relationships normally appear as foreign-key IDs, not expanded objects.

The versioned routes return a server-generated `X-Request-ID` for tracing. Capture it when reporting errors; an incoming request ID is not echoed. Responses carry `Cache-Control: no-store, private`. Public reads may be cached internally for 300 seconds by default, and directory writes invalidate that cache.

### HTTP methods

| Method | Meaning | Normal success status |
| --- | --- | --- |
| `GET` | Read the parameter-free current-user view | `200` |
| `POST` to `/search`, an ID URL, `/{entity}/search`, or a nested collection | Read parameterized data | `200` |
| `POST` to a collection | Create a record or stage an import | `201` |
| `PUT`, `PATCH` | Partially update a record | `200` |
| `POST` to login, logout, transfer, or commit | Execute the named action | `200` |

Both `PUT` and `PATCH` accept partial updates: omitted fields retain their values. Send `null` only for nullable fields. There are no `DELETE` operations; retire directory records by changing `status`.

## Pagination, filters, and search

Directory search endpoints and the nested collections below accept these fields in a JSON request body:

| Parameter | Type and limits | Behavior |
| --- | --- | --- |
| `page` | Integer, 1–100000; default 1 | Page number |
| `per_page` | Integer, 1–100; default 25 | Page size |
| `q` | String, 2–100 characters | Case-insensitive substring search |
| `status` | A directory status | Exact status; public visibility still applies |
| Ancestor ID | Positive integer | Restrict to records belonging to that ancestor |

Accepted ancestor field names are `ecclesiastical_province_id`, `diocese_id`, `deanery_id`, `parish_id`, `outstation_id`, `zone_id`, `jumuiya_id`, and `family_id`. Use only ancestors of the resource being queried: `POST /parishes/search` with `{"diocese_id":5}` follows the deanery's diocese. A filter that is not an ancestor of that resource is ignored. These are relationship filters, not filters on the resource's own ID. Use `POST /parishes/42` to select parish 42.

```bash
curl --fail-with-body --silent --show-error \
  "$API_BASE_URL/parishes/search" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data '{"diocese_id":5,"q":"Mfano","per_page":50,"page":1}'
```

Collections are ordered by `name`, then `id`. Families use `family_name`; members use `last_name`. There are no sort, include, or field-selection parameters. Search matches names, English names when available, and codes. Family search matches `family_name` and `family_code`; member search matches name components and `member_code`. `%` and `_` are treated literally, not as search wildcards.

Pagination metadata contains `current_page`, `per_page`, `total`, and `last_page`. There are no `next` or `previous` links in ordinary list responses. Increment `page` until `current_page >= last_page`. An out-of-range page can have an empty `data` array. Pages are not a snapshot: records can change while you browse.

### Search across the directory

`POST /search` with `{"q":"Mfano"}` requires `q` and returns paginated matches from provinces, dioceses, deaneries, parishes, outstations, zones, and jumuiyas. Associations, choirs, and ministries have their own search endpoints. Families and members are excluded from global search.

Each search item contains only `id`, `code`, `name`, `name_en`, and `entity_type`:

```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "code": "DEMO-PARISH",
      "name": "Parokia ya Mfano",
      "name_en": "Example Parish",
      "entity_type": "parishes"
    }
  ],
  "meta": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 }
}
```

Fetch `/{entity_type}/{id}` for the full public record. Global search orders results by name, entity type, and ID. Ancestor filters apply separately to each searched type; types for which the filter is not an ancestor are not restricted by that filter. Prefer a resource-specific list when you need strict filtering.

## Public endpoints

For each of the ten public resource types in the directory table, use `POST /{entity}/search` with a JSON body for a paginated list and `POST /{entity}/{id}` for a single record. An empty JSON object returns the first page without filters.

### Browse children

| Endpoint | Result |
| --- | --- |
| `POST /provinces/{id}/dioceses` | Dioceses in a province |
| `POST /dioceses/{id}/deaneries` | Deaneries in a diocese |
| `POST /deaneries/{id}/parishes` | Parishes in a deanery |
| `POST /parishes/{id}/outstations` | Outstations in a parish |
| `POST /parishes/{id}/zones` | Zones in a parish |
| `POST /parishes/{id}/jumuiyas` | Jumuiyas in a parish |
| `POST /zones/{id}/jumuiyas` | Jumuiyas in a zone |

Each accepts pagination fields in a JSON body and returns the ordinary paginated envelope. The parent must be publicly visible; otherwise the result is `404`. Conflicting parent body fields produce no matches. For associations, choirs, and ministries, use their search endpoint with `parish_id`, for example `POST /choirs/search` with `{"parish_id":42}`.

### Parish context

`POST /parishes/{id}/context` returns an object with `parish`, `deanery`, `diocese`, and `ecclesiastical_province`. Each is a full public record of that type. It has the standard success envelope and `meta: {}`.

### Parish structure

`POST /parishes/{id}/structure` returns `data.parish` and six arrays: `outstations`, `zones`, `jumuiyas`, `associations`, `choirs`, and `ministries`. Each array contains at most 100 public records. Its corresponding `meta` entry contains:

| Field | Meaning |
| --- | --- |
| `total` | Number of visible records of this type in the parish |
| `truncated` | `true` when there are more than 100 |
| `method` | HTTP method for the complete collection; always `POST` |
| `url` | Absolute URL of the search endpoint, e.g. `https://YOUR_API_HOST/api/v1/zones/search` |
| `body` | JSON filters for the request, e.g. `{"parish_id":42}` |

If `truncated` is true, fetch the linked collection from page 1 and paginate it independently. Treat that collection as the complete result rather than appending it to the first 100 records. Structure does not accept pagination parameters and never includes families or members.

## Authentication and access

Request an administrator account and organizational scope from your API provider. There is no public registration endpoint, token refresh endpoint, or account-management API.

### Sign in

`POST /auth/login` accepts:

| Field | Required | Constraints |
| --- | --- | --- |
| `email` | Yes | Valid email, at most 255 characters |
| `password` | Yes | String, at most 255 characters |
| `device_name` | Yes | Descriptive token/device label, at most 100 characters |

```bash
curl --fail-with-body --silent --show-error \
  "$API_BASE_URL/auth/login" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data '{"email":"admin@example.org","password":"YOUR_PASSWORD","device_name":"consumer-app"}'
```

Example response:

```json
{
  "success": true,
  "data": {
    "token": "TOKEN_ID|TOKEN_SECRET",
    "token_type": "Bearer",
    "expires_at": "2026-09-15T16:00:00+00:00",
    "user": {
      "id": 12,
      "name": "Example Administrator",
      "email": "admin@example.org",
      "role": "parish_admin",
      "diocese_id": null,
      "deanery_id": null,
      "parish_id": 42
    }
  },
  "meta": {}
}
```

Use the complete `token` value, including the separator. Tokens expire after eight hours by default; use the returned `expires_at` rather than assuming a fixed lifetime. Sign in again after expiry. Treat tokens as credentials and avoid putting them in URLs, logs, or public application source.

```bash
export API_TOKEN='TOKEN_ID|TOKEN_SECRET'

curl --fail-with-body --silent --show-error \
  "$API_BASE_URL/auth/me" \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer $API_TOKEN"
```

`GET /auth/me` returns the user profile directly under `data`. `POST /auth/logout` revokes the token used for that request and returns `data: {"message": "Signed out successfully."}`. It does not revoke tokens for other devices.

### Organizational scope

| Default role | Directory scope | Sources, imports, and audit logs |
| --- | --- | --- |
| `super_admin`, `tec_admin` | National directory | Allowed |
| `diocesan_admin` | Assigned diocese and descendants | Not allowed |
| `deanery_admin` | Assigned deanery and descendants | Not allowed |
| `parish_admin` | Assigned parish and descendants | Not allowed |

Access also depends on active-account status, permissions, and token abilities. Read operations require `directory:read`; writes require `directory:write`. Transfers require an additional permission. A scoped account without its assigned scope has no directory record access. Administrative lists filter to the caller's scope; an out-of-scope detail read can return `404`, while a forbidden write or governance action can return `403`.

Scoped administrators cannot read ancestors above their assigned scope through `/admin`; use public endpoints for published ancestor data. Parish transfers check access to both the parish and the destination deanery, so a parish-scoped administrator cannot perform a transfer under the default scope rules.

## Create and update records

All twelve resource types support these protected administrative operations:

| Endpoint | Behavior |
| --- | --- |
| `POST /admin/{entity}/search` | Paginated records within scope, including inactive records; filters are sent as JSON |
| `POST /admin/{entity}/{id}` | Full record within scope |
| `POST /admin/{entity}` | Create a record |
| `PUT /admin/{entity}/{id}` | Partial update |
| `PATCH /admin/{entity}/{id}` | Partial update |

Families and members additionally support the same operations without `/admin`, such as `POST /families` and `PATCH /members/{id}`. These shorter routes remain protected. Public organization paths do not accept writes.

Parish administrators can update their own parish profile and enter its outstations, zones, jumuiyas, families, members, associations, choirs, and ministries. On creation, they may omit `parish_id`; the server supplies the parish assigned to their account. An explicit `null` is invalid, and another parish's ID is forbidden. Other administrator roles must supply `parish_id`. Updates retain existing relationships unless explicitly changed and authorized.

Use `POST /admin/parishes/{id}/structure` for the private parish workspace. It includes inactive records, parish contacts, families, and members, and works even before the parish has a deanery assignment. Each collection contains at most 100 records; its `meta` entry provides the total, a truncation flag, and the protected POST search method, URL, and JSON body for pagination. Access remains limited to the administrator's organizational scope. The public `/parishes/{id}/structure` endpoint continues to exclude personal and inactive data, even with a bearer token.

### Required creation fields

| Entity | Required fields | Optional relationship IDs |
| --- | --- | --- |
| `provinces` | `code`, `name` | None |
| `dioceses` | `code`, `name`, `type`, `ecclesiastical_province_id` | None |
| `deaneries` | `code`, `name`, `diocese_id` | None |
| `parishes` | `code`, `name` | `deanery_id` |
| `outstations` | `code`, `name`, `parish_id` | None |
| `zones` | `code`, `name`, `parish_id` | `outstation_id` |
| `jumuiyas` | `code`, `name`, `parish_id`, `zone_id` | `outstation_id` |
| `families` | `family_code`, `family_name`, `parish_id` | `outstation_id`, `zone_id`, `jumuiya_id` |
| `members` | `member_code`, `first_name`, `last_name`, `gender`, `parish_id` | `family_id`, `outstation_id`, `zone_id`, `jumuiya_id` |
| `associations`, `choirs`, `ministries` | `code`, `name`, `parish_id` | `outstation_id` |

The table lists the general requirements; assigned parish administrators can omit `parish_id` as described above. Parent IDs must already exist. All supplied relationships, including indirectly linked family and community ancestors, must be consistent within the same parish. Optional links do not replace required links; for example, `family_id` does not determine a member's parish.

### Optional fields by resource

All directory records accept `status`. Public organization types also accept nullable `source_id` referring to an existing source. Additional nullable fields are:

| Entity | Additional fields |
| --- | --- |
| `provinces` | `name_en`, `description` |
| `dioceses` | `name_en`, `established_at` |
| `deaneries` | `name_en` |
| `parishes` | `name_en`, `address`, `phone`, `email`, `latitude`, `longitude` |
| `outstations` | `name_en`, `address`, `latitude`, `longitude` |
| `zones`, `jumuiyas` | `name_en`, `description` |
| `families` | `address`, `phone` |
| `members` | `middle_name`, `date_of_birth`, `phone`, `email` |
| `associations`, `choirs`, `ministries` | `description` |

Names are at most 255 characters. Codes are at most 64 characters, must be unique per type, and match `^[A-Z0-9][A-Z0-9._-]*$`. Submit uppercase codes for ordinary writes; only the import workflow uppercases codes for you. Descriptions and addresses allow 5000 characters, phones 32, and emails 255. Latitude is between -90 and 90; longitude between -180 and 180.

Diocese `type` is `archdiocese` or `diocese`. Member `gender` is `male`, `female`, or `unspecified`. Birth and establishment dates cannot be in the future. Directory `status` is one of `active`, `inactive`, `pending`, `transferred`, `merged`, or `suppressed`; it defaults to `active` on ordinary creation.

`id`, `created_at`, and `updated_at` are response fields. Use only documented writable fields.

### Create a parish

```bash
curl --fail-with-body --silent --show-error \
  "$API_BASE_URL/admin/parishes" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $API_TOKEN" \
  --data '{"code":"DEMO-PARISH","name":"Parokia ya Mfano","deanery_id":7,"source_id":3}'
```

The `201` response contains the full administrative parish record, including contact fields, under `data`. Active organizations publish immediately when their required ancestry is also active.

### Create a private member

```json
{
  "member_code": "DEMO-MEMBER-001",
  "first_name": "Example",
  "last_name": "Member",
  "gender": "unspecified",
  "parish_id": 42,
  "family_id": null
}
```

Send this body to `POST /members` or `POST /admin/members` with your bearer token. The `201` response returns the member record in the standard success envelope. Family creation follows the same pattern using `family_code`, `family_name`, and `parish_id`.

### Update or retire a record

```bash
curl --fail-with-body --silent --show-error --request PATCH \
  "$API_BASE_URL/admin/parishes/42" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $API_TOKEN" \
  --data '{"status":"inactive"}'
```

Authorized corrections to names, coordinates, relationships, or source take effect immediately. Public visibility requires `status: "active"` and active ancestry.

Changing a parish's deanery, including assigning a previously unassigned parish, requires the transfer endpoint. Reparenting other entities with dependent records is rejected. Do not use ordinary updates or imports to bypass these rules.

## Sources and corrections

### Register provenance

National administrators can list with `POST /admin/data-sources/search` and create with `POST /admin/data-sources`. The search body supports `page` and `per_page`; directory search and ancestor filters do not filter this collection.

| Source field | Requirement |
| --- | --- |
| `name` | Required string, at most 255 characters; unique together with `version` |
| `type` | Required string, at most 60 characters; no fixed enumeration |
| `version` | Required string, at most 40 characters |
| `publisher` | Optional nullable string, at most 255 characters |
| `reference`, `description` | Optional nullable strings, at most 5000 characters each |
| `publication_date` | Optional nullable `YYYY-MM-DD`, no later than today |

Example creation body:

```json
{
  "name": "Example diocesan directory",
  "type": "directory",
  "version": "2026-09",
  "publisher": "Example Diocese",
  "reference": "Reviewed directory, page 12",
  "publication_date": "2026-09-01"
}
```

The `201` response returns the source, including its `id`, under `data`. Retain that ID for creation, corrections, transfers, and imports. Source records do not have update, delete, or detail endpoints. Correct an organization with its normal `PUT` or `PATCH` endpoint and supply `source_id` when the source has changed.

## Parish transfers and history

`POST /admin/parishes/{id}/transfer` moves a parish immediately and preserves its previous hierarchy. All fields below are required:

```json
{
  "new_deanery_id": 9,
  "source_id": 3,
  "effective_date": "2026-09-15",
  "reason": "Approved reassignment recorded in the source."
}
```

The destination deanery must exist, differ from the current deanery, and be within the caller's permitted scope. `effective_date` cannot be in the future or earlier than the latest recorded transfer. `reason` is a string up to 5000 characters. The date records the effective date; it does not schedule a future move.

The `200` response contains the updated parish. The move is visible immediately when the parish and its new ancestry are active. Its descendants remain linked to the same parish ID.

`POST /admin/parishes/{id}/history` returns a paginated history ordered newest first by ID. Send `page` and `per_page` in the JSON body. Each item includes `parish_id`, old/new deanery and diocese IDs, `effective_date`, `reason`, `source_id`, and `created_at`. Previous hierarchy IDs may be null when the parish was previously unassigned.

## Import workflow

Imports require national import permissions and accept the ten public organization types. Each batch contains **1–500 rows of one entity type**. Parent IDs must refer to existing records, so import and commit parents before their children. Input is normalized JSON; PDF and CSV uploads are not accepted by these endpoints.

1. Register or select a source.
2. Stage a batch with `POST /admin/imports`.
3. Inspect its `status` and row report with `POST /admin/imports/{id}`.
4. Correct invalid data and stage a new batch.
5. After review, commit a valid batch with `POST /admin/imports/{id}/commit` and `{"reviewed": true}`.
6. Committed active organizations publish immediately when their ancestry is active.

Example staging body:

```json
{
  "entity_type": "parishes",
  "source_id": 3,
  "rows": [
    { "code": "DEMO-PARISH", "name": "Parokia ya Mfano", "deanery_id": 7 }
  ]
}
```

Staging returns `201` with a batch under `data`, even when row validation finds errors. **HTTP success means the batch was staged for inspection, not that its rows are valid or committed.** Check `data.status` (`staged`, `invalid`, or `committed`) and `data.report.invalid`.

Example `data.report` for a valid batch:

```json
{
  "total": 1,
  "valid": 1,
  "invalid": 0,
  "rows": [
    { "row": 1, "valid": true, "operation": "create" }
  ]
}
```

Invalid row reports contain `row`, `valid: false`, and an `errors` object mapping field names to message arrays. Row numbers start at 1. The batch detail also includes normalized `rows`, `source_id`, `entity_type`, `checksum`, creator/reviewer IDs, review time, and timestamps. `POST /admin/imports/search` returns paginated summaries without the rows, report, or checksum.

Every row must contain all required creation fields, including for updates. Omit `status` and `source_id` from rows; the batch controls provenance. Strings are trimmed and codes uppercased. Duplicate codes within a batch are invalid. An existing code causes an update when committed; a new code creates a record.

The same source, entity type, and normalized payload returns the existing batch. Committing an already committed batch is a no-op. Commit revalidates the entire batch and applies all rows atomically; a failure applies none. An invalid batch cannot be committed (`409`); a formerly valid batch that has become invalid returns `422` on commit. Parish transfers still require the transfer endpoint.

## Audit logs

`POST /admin/audit-logs/search` requires national audit permission and returns a paginated list ordered newest first by ID. Send `page` and `per_page` in the JSON body; there are no action, entity, date, or user filters on this endpoint.

Items contain `id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `request_id`, and `created_at`. Values can be null where no actor, request, or previous state exists. The audit record's `request_id` can be matched to a versioned API response's `X-Request-ID`.

## Errors and rate limits

Application errors use this envelope. `details` is optional and is normally present for field validation failures:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The submitted data is invalid.",
    "details": {
      "code": ["The code has already been taken."]
    }
  }
}
```

| HTTP status | Typical error code | Consumer action |
| --- | --- | --- |
| `400` | `BAD_REQUEST` | Check the request format |
| `401` | `INVALID_CREDENTIALS`, `UNAUTHENTICATED` | Correct credentials or sign in again |
| `403` | `FORBIDDEN` | Check role, scope, account status, and token permissions |
| `404` | `NOT_FOUND`, resource-specific codes such as `PARISH_NOT_FOUND` | Check ID and visibility/scope |
| `405` | `METHOD_NOT_ALLOWED` | Check the route and supported method |
| `409` | `CONFLICT`, `DATA_CONFLICT` | Reload current state and resolve the conflict |
| `413` | `PAYLOAD_TOO_LARGE` | Reduce the request size |
| `422` | `VALIDATION_ERROR` | Correct fields listed in `error.details` |
| `429` | `RATE_LIMIT_EXCEEDED` | Wait for `Retry-After` seconds before retrying |
| `500` | `INTERNAL_ERROR` | Retain the request ID and report the failure |
| `503` | `SERVICE_UNAVAILABLE` | Retry reads after a delay |

Missing organization errors can be resource-specific; do not assume every `404` uses `NOT_FOUND`. Duplicate-code validation normally returns `422`; database-level relationship or uniqueness conflicts may return `409`. Errors generated by a reverse proxy or hosting layer can use a different body, so handle non-JSON responses too.

### Default limits

| Traffic | Limit |
| --- | --- |
| Public versioned endpoints | 60 requests/minute per IP, shared across public routes |
| Protected versioned endpoints | 120 requests/minute per user, shared across tokens/routes |
| Login | 5 attempts/minute per IP and email pair; also 20 attempts/minute per IP |

Public and authenticated limits are configurable by the provider. A token on a public endpoint still uses the public limit. Read `Retry-After` on `429` and use bounded retry delays with jitter. Avoid automatically repeating writes after a timeout: the server may have applied the first request. Inspect current state before retrying. General writes have no idempotency-key support; imports have the specific deduplication behavior described above.

## JavaScript integration

This example uses browser `fetch` and returns the whole envelope so callers retain pagination metadata. Supply a token only for protected calls. It checks HTTP errors, application errors, and non-JSON responses, and exposes the request ID and retry delay.

```javascript
const apiBaseUrl = 'https://YOUR_API_HOST/api/v1';

async function apiRequest(path, { token, body, ...options } = {}) {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  if (token) headers.set('Authorization', `Bearer ${token}`);
  if (body !== undefined) headers.set('Content-Type', 'application/json');

  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...options,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const payload = await response.json().catch(() => null);
  if (!response.ok || payload?.success !== true) {
    const error = new Error(payload?.error?.message ?? `HTTP ${response.status}`);
    error.status = response.status;
    error.code = payload?.error?.code;
    error.details = payload?.error?.details;
    error.requestId = response.headers.get('X-Request-ID');
    error.retryAfter = response.headers.get('Retry-After');
    throw error;
  }
  return payload;
}

async function loadParishPage(dioceseId, page = 1) {
  const query = new URLSearchParams({
    diocese_id: String(dioceseId),
    page: String(page),
    per_page: '25',
  });
  const { data, meta } = await apiRequest(`/parishes?${query}`);
  return { parishes: data, nextPage: meta.current_page < meta.last_page ? page + 1 : null };
}

// Load additional pages on demand, respecting rate limits.
const firstPage = await loadParishPage(5);
console.log(firstPage.parishes);
```

### Browser access

For a browser application on a different origin, ask the provider to allow your application's origin through CORS. The default origin allowlist is empty. The API allows `Accept`, `Authorization`, `Content-Type`, and `X-Request-ID` request headers, and exposes `X-Request-ID` and `Retry-After` response headers. Cookie credentials are not enabled; use bearer authorization for this integration.

If cURL works but a browser reports a CORS failure, check the allowed origin and preflight response with the provider. Do not use `mode: "no-cors"`; it prevents your application from reading the response.

## Integration checklist

- Configure the provider's actual API base URL; do not hard-code example IDs or credentials.
- Handle empty collections, nullable fields, hidden records, and all pagination pages.
- Use public endpoints for directory browsing and protected endpoints for administrative/private data.
- Honor token expiry, organizational scope, and rate limits.
- Validate create/update fields and use the transfer workflow when required.
- Capture HTTP status, error code, and `X-Request-ID` for troubleshooting without logging credentials.
- Use the [OpenAPI contract](openapi.json) for schema-aware tools; its server URL `/api/v1` is relative to the API origin. When importing the file from a separate documentation host, configure your client's server to the actual API base URL.

Provider health checks are available at the origin-level paths `/health` (database/cache readiness) and `/up` (application liveness). They are outside the versioned API and do not use its standard success envelope.
