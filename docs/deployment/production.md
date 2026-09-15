# Production deployment

## First installation

Prepare a Linux host with Docker Engine and Compose. Copy `.env.example` to `.env`, generate `APP_KEY` once, and set independent strong `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, and `REDIS_PASSWORD` values. Keep these values in a secret manager or a root-managed environment file, outside version control. Set the public `APP_URL` and explicit `CORS_ALLOWED_ORIGINS` for your clients. Set `APP_ENV=production` and `APP_DEBUG=false`.

```bash
docker compose build
docker compose up -d mysql redis
docker compose run --rm api php artisan migrate --seed --force
docker compose run --rm -it api php artisan core:create-admin admin@example.org --name="TEC Administrator" --role=super_admin
docker compose up -d
curl --fail http://127.0.0.1:8080/health
```

The application image runs as `www-data`. MySQL and Redis are internal to the Compose network. Nginx binds to loopback by default; place a host reverse proxy with a valid TLS certificate in front of it. Redirect HTTP to HTTPS and add HSTS at that proxy after confirming HTTPS works. Configure trusted proxy addresses deliberately if forwarding client IPs; otherwise rate limiting uses the visible proxy IP. Never trust arbitrary forwarded headers from the internet.

Database and Redis data, and Laravel storage, use persistent named volumes. Retain `APP_KEY` across deployments. The queue worker and scheduler run independently. Redis uses authentication and AOF persistence. Run `docker compose logs` for application and error logs; API logs go to stderr. No application containers expose database or Redis ports to the host.

## GitLab pipeline

The validation job runs formatting, Composer validation, dependency security audit, MySQL feature tests, and route/config cache checks. The build job publishes a commit-tagged application image to GitLab's registry. The runner must support Docker-in-Docker for image builds.

Staging (`develop`) and production (`main`) deployment jobs are manual. Configure protected, environment-scoped variables:

- `DEPLOY_HOST`, `DEPLOY_USER`: the target host and dedicated deployment account.
- `DEPLOY_SSH_KEY`: a GitLab **file** variable containing the deployment SSH key.
- `DEPLOY_KNOWN_HOSTS`: a **file** variable containing a host key verified out of band.
- `DEPLOY_SCRIPT`: the absolute path to a host-managed deployment wrapper.

The wrapper should export `DEPLOY_DIR` and invoke this repository's `scripts/deploy.sh` with the image argument. Provision the deployment checkout, Compose file, environment file and registry read credentials beforehand. Keep the checkout's deployment files aligned with the release; the application code comes from the immutable image. The deploy script pulls the image, runs noninteractive migrations, recreates application services, and checks `/health`. Protect production deployments with GitLab environment approvals.

Take a backup before schema changes. Use backward-compatible migrations so old and new application versions can coexist during rollout. Keep the previous image tag for rollback. A failed health check stops the pipeline but does not automatically reverse migrations; investigate, then redeploy the previous compatible image. Destructive schema rollback requires a reviewed recovery procedure.

## Release verification

Check `/health`, sign-in and token revocation, public directory reads, one scoped private request, Redis connectivity, queue operation, and the scheduler. Confirm off-host encrypted backups and alert delivery. Run representative load tests before claiming the target response times or 100+ requests/second. TLS provisioning, actual server deployment, real directory ingestion, load capacity and disaster-recovery timing require the target infrastructure and source data.
