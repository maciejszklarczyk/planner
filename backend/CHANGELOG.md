# Changelog

## 2026-05-03

- [89b7188] feat(events): add endDate/location fields and extend test fixtures

## 2026-04-28

- [1c75917] test: refactor auth guard tests to data providers and fix SQLite warning
- [75fca6b] docs: update README and sync codebase knowledge docs with current state

## 2026-04-25

- [f01151a] test: add coverage for DevHeaderAuthenticator, User DTOs and fix SQLite test config

## 2026-04-23

- [a60d803] feat(event): implement full CRUD with mapper pattern and separate request DTOs

## 2026-04-22

- [78f057f] test: add unit and functional test coverage

## 2026-04-21

- [16f65cc] test(event): use X-Dev-User header auth in EventControllerTest
- [72ceea6] feat(dev-auth): add X-Dev-User header authenticator for local development
- [38a92d9] feat(event): add Event entity, controller, migration and fixtures
- [2313887] build: add .dockerignore to exclude docs and dev files from prod image

## 2026-04-15

- [7591b44] docs(codebase): update knowledge docs to reflect activity log Phase 1 state
- [c53693f] feat(activity-log): add UserActivityLog entity and UserActivityTypeEnum enum
- [181d2a0] security: hash invitation tokens and add coverage tooling

## 2026-04-14 — docs: add user activity log implementation plan

- [8a7022b] docs: add user activity log implementation plan

## 2026-04-14 — docs: add user activity log design spec

- [97fbcdd] docs: add user activity log design spec

## 2026-04-14 — docs: remove redundant git-commit rule from CLAUDE.md

- [6f274f7] docs: remove redundant git-commit rule from CLAUDE.md

## 2026-04-13 — 08b275a

- Remove --strict flag from composer validate in lint CI stage
- 4e18c4e Update OpenAPI version example to 0.0.4 in GET /version response

## 2026-04-13 — f0e47b8

- Add OpenAPI response schemas for GET /health and GET /version endpoints

## 2026-04-13 — 3626ced

- Regenerate composer.lock after version bump

## 2026-04-13 — a12dd48

- Bump version to 0.0.3

## 2026-04-13 — 6efd525

- Add `git-commit` skill as default commit message generator (CLAUDE.md)

## 2026-04-12 — c62457d

- Add `GET /version` endpoint returning app version from `composer.json`
- Add `version` field (`0.0.2`) to `composer.json`
- Expose `/version` as public route in `security.yaml`

## 2026-04-12 — PLA-15

- Add `league/flysystem-bundle` and `league/flysystem-aws-s3-v3` dependencies
- Configure `uploads.storage` with S3 adapter (RustFS-compatible) in `config/packages/flysystem.yaml`
- Define `aws_s3_client` as Symfony service in `services.yaml` with path-style endpoint support
- Refactor `UserAvatarController`: upload/delete via Flysystem, in-memory WebP encoding with output buffering, avatar URL is now full public S3 URL
- Add S3 env vars (`S3_ENDPOINT`, `S3_PUBLIC_URL`, `S3_KEY`, `S3_SECRET`, `S3_BUCKET`, `S3_REGION`) to `.env`, `.env.dev`, `.env.example`
- Add RustFS service and init container (creates public `uploads` bucket) to `compose.override.yaml`
- Remove `uploads_data` volume from `docker-compose.prod.yaml` (files now stored in RustFS)

## 2026-04-11 — strict-types

- Add `declare_strict_types` rule to `.php-cs-fixer.dist.php`
- Add `declare(strict_types=1)` to all files missing it (CS Fixer)
- Fix GD functions in `UserAvatarController` — add `GdImage` instance checks after `imagecreatefromX()` and `imagecreatetruecolor()`
- Fix `parse_url()` nullable chain in `UserAvatarController::delete()`
- Fix nullable `getId()` in `UserAvatarController::upload()`
- Fix nullable→non-nullable mismatches in `fromEntity()` methods: `UserListItemDto`, `EditUserDto`, `GroupListItemDto`, `GroupMembershipDto`
- Fix `UserGroupService::addUserToGroup()` — replace missing `$this->isMember()` with `$user->isMemberOf()`
- Fix `UserHasGroup::getRole()` return type — remove unnecessary `?`

## 2026-04-11 — 13114d0

- Add `avatar` field to `User` entity with getter/setter
- Add Doctrine migration adding `avatar` column to `user` table
- Add `UserAvatarController` with `POST /user/avatar` (upload + resize/crop to 256x256 WebP) and `DELETE /user/avatar`
- Register `UserAvatarController` in `services.yaml` with `avatarsDir` and `avatarsPublicPath` parameters
- Add `avatar` field to `UserListItemDto` response
- Expose `avatar` in `AuthController` `/me` response
- Add GD extension (`gd` with JPEG/WebP support) to Dockerfile
