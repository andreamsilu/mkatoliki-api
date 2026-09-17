# Operations

## Monitoring

Probe `/health` every minute and alert on non-200 responses. It checks both database connectivity and cache read/write. Use `/up` for application liveness. Collect Nginx errors, structured Laravel request logs, failed jobs, authentication failure counts, 5xx/429 rates, latency percentiles, CPU/RAM/disk, MySQL connections and slow queries, Redis memory/evictions, queue age, and backup age. Correlate API incidents with `X-Request-ID`; do not ask clients to send bearer tokens or private payloads into an incident channel.

Log retention and access need organizational policy. Audit records contain redacted modification snapshots, actor and request identity. Public source provenance and private operational auditing have separate access controls. Avoid enabling verbose SQL binding logs or request-body capture in monitoring agents.

## Encrypted backups

`scripts/backup.sh` streams a consistent InnoDB dump through gzip and OpenSSL CMS encryption. No plaintext dump is written to disk. It writes an encrypted file and SHA-256 checksum only after all pipeline stages succeed. Requirements: MySQL 8 `mysqldump`, `gzip`, `openssl` and a PEM recipient certificate. Keep the corresponding private key offline or in a recovery secret store, never beside the backups.

Use a dedicated least-privilege backup database account and a MySQL client option file with mode `0600`:

```ini
[client]
host=127.0.0.1
user=backup_operator
password=YOUR_SECRET
```

Run with `MYSQL_CNF`, `BACKUP_PUBLIC_KEY`, `BACKUP_DIR`, and `DB_DATABASE` set in the scheduler's protected environment:

```bash
scripts/backup.sh
```

Schedule a full backup at least daily. Copy the encrypted artifact and checksum to a separate, access-controlled off-host storage account; enable versioning or object lock where available. Retain daily, weekly and monthly copies according to the organization's approved retention policy. The script deliberately does not delete backups or embed cloud credentials. Alert on failed jobs and backups older than 24 hours. Files stored only on the database host do not meet disaster-recovery requirements.

## Recovery drills

Verify the checksum, decrypt with the private key and certificate, and import into an isolated MySQL database:

```bash
sha256sum --check catholic_tanzania-TIMESTAMP.sql.gz.enc.sha256
openssl cms -decrypt -binary -inform DER -in catholic_tanzania-TIMESTAMP.sql.gz.enc -recip recovery-cert.pem -inkey recovery-key.pem | gzip -dc | mysql --defaults-extra-file=recovery-client.cnf
```

Use Bash with `set -euo pipefail` for recovery pipelines. The dump contains the database name; restore on an isolated server rather than an existing application instance. Reapply the recorded application image and environment secrets, validate foreign keys and record counts, then run health and scoped-access smoke tests. Time the exercise against RPO ≤24h and RTO ≤4h; these are targets until demonstrated.

## Maintenance cadence

Daily: health, errors, failed jobs, disk and backup age. Weekly: performance, access alerts, backup integrity and dependency advisories. Monthly: update supported dependencies, inspect indexes/slow queries and capacity. Quarterly: security review, restore rehearsal and load testing. The scheduler prunes expired tokens and old failed jobs; it does not silently delete audit/history records.
