# Directory import workflow

1. Extract the supplied directory into a raw dataset outside this API, retaining page references and the original source artifact.
2. Normalize and review spelling, codes and organizational relationships. Register the source and edition through `/api/v1/admin/data-sources`.
3. Import in dependency order: provinces → dioceses → deaneries → parishes → outstations/zones → jumuiyas → associations/choirs/ministries. Parent fields contain existing database IDs; resolve them by the directory's unique codes first.
4. Stage each entity type with `POST /api/v1/admin/imports`. Nothing enters the directory yet.
5. Inspect `POST /api/v1/admin/imports/{id}`. It returns normalized rows and a per-row report. Correct invalid batches and stage them again.
6. After human review, commit a valid batch with `POST /api/v1/admin/imports/{id}/commit` and `{"reviewed": true}`. Validation runs again inside the commit transaction. A failure rolls back the entire batch.
7. Committed records publish immediately when their status and ancestry are active. Correct them later through the normal administrative endpoints when newer information becomes available.

Example staging body:

```json
{
  "entity_type": "parishes",
  "source_id": 1,
  "rows": [
    {"deanery_id": 15, "code": "ARU-KIJ", "name": "Parokia ya Kijenge"}
  ]
}
```

The example ID and code are illustrative; resolve actual IDs by code before importing. The TEC seed code for Kijenge is `ARU-KIJENGE`. Records must contain all required creation fields. Do not submit `status` or `source_id` inside rows. The batch supplies provenance. Codes are trimmed and uppercased; names and other strings are trimmed. Duplicate codes within one batch are invalid. Existing codes are treated as updates and remain subject to hierarchy/reparenting rules.

Identical source/entity/normalized-payload combinations return the existing batch. Committing an already committed batch is a no-op. Changed payloads produce a new batch; all imported updates return to review. A reviewed source can supersede earlier data only within the normal domain rules. Parish transfers must use the transfer endpoint so their history is retained.

A CLI can stage the same normalized JSON array:

```bash
php artisan core:import parishes /path/to/parishes.json --source=1 --user=1
```

The user must have national import permission. The command prints the batch ID and validation report, exits unsuccessfully for invalid data, and never publishes automatically. Input is limited to 2 MiB and 500 records.

The initial TEC 2020 baseline is available through `php artisan db:seed --class=TecDirectorySeeder`. Its versioned transcription lives in `database/seeders/tec-directory-2020.json`, with source URLs, printed page references, and explicit coverage gaps. It supplies all seven provinces and 34 dioceses from that edition, 45 named deaneries, and 229 parishes whose deanery assignments are documented. Parish coverage spans six dioceses; missing assignments must be supplied and reviewed before importing additional parishes. Congregation addresses and outstation lists must not be interpreted as parish lists. The seeders publish active records and preserve later corrections. No PDF extraction tool or original PDF is bundled.
