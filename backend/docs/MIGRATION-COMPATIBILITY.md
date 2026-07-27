# Migration Compatibility

Backend deploys run `doctrine:migrations:migrate` against the **new** image
before the **old** containers stop serving traffic (migrate-before-cutover,
see `.github/workflows/backend-deploy.yml`). This means every migration
must be safe to apply while the previous release's code is still running.

**Rule**: migrations in a given release must be additive/backward-compatible
with the code from the previous release. Never make a migration destructive
(drop/rename a column, tighten a constraint) in the same release that stops
reading/writing it.

- Good: add a nullable column in this release, backfill and start using it
  in a later release, drop the old column only once nothing reads it.
- Bad: rename a column in one migration — the still-running old code breaks
  the moment the migration applies, before cutover even happens.

## Rollback

Neither `backend-deploy.yml` nor `frontend-deploy.yml` auto-rolls-back. If
`up -d` succeeds but the post-cutover healthcheck fails, the new containers
are left running and the job just reports red. Recover manually:

```
docker compose -f docker-compose.prod.yaml down
IMAGE_TAG=<previous-good-tag> docker compose -f docker-compose.prod.yaml up -d
```
