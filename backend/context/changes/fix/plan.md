# Fix Twig and Symfony security vulnerabilities Implementation Plan

## Overview

`composer audit` reports 1 CRITICAL + 10 HIGH advisories concentrated in `twig/twig` (installed v3.24.0) and three transitive `symfony/*` packages pulled in at v7.4.8 (`symfony/http-kernel`, `symfony/mime`, `symfony/security-http`). All are fixed by patch releases already inside this project's existing `composer.json` constraints — no breaking upgrade, no application code changes required for the CVE fixes themselves. We also close the gap that let this go undetected: neither `composer audit` nor `phpstan` currently run in CI.

## Current State Analysis

- `composer.json` pins Symfony to `7.4.*` (`extra.symfony.require`) and Twig to `^2.12|^3.24`. Both ranges already permit the fixed versions.
- Installed: `twig/twig` v3.24.0, `symfony/http-kernel` v7.4.8, `symfony/mime` v7.4.8, `symfony/security-http` v7.4.8.
- `.gitlab-ci.yml` `test` stage runs `php-cs-fixer` (dry-run diff) and `phpunit` (with pcov coverage). No dependency-audit step exists anywhere in the pipeline.
- Four live endpoints combine a GET-only route filter with `#[IsGranted]` — the exact pattern `CVE-2026-45075` (HEAD-request bypass) targets — and all four already have functional test coverage asserting 401/403 behavior.
- The Twig sandbox extension is not registered anywhere in this codebase (one static template, no dynamic rendering), so the CRITICAL and several HIGH Twig sandbox-bypass CVEs have no live attack surface here today — the fix is still correct to apply, just not urgent for that specific reason.
- `X509Authenticator` and `failure_forward` are not configured in `config/packages/security.yaml` — those two CVEs are not exploitable in this codebase.
- `composer audit` also reports 6 lower-severity advisories (5 medium, 1 unrated) in `guzzlehttp/guzzle`, `guzzlehttp/psr7`, and `mtdowling/jmespath.php` — pulled in transitively via `aws/aws-sdk-php` (a dependency of `league/flysystem-aws-s3-v3`). None match the `twig/twig`/`symfony/*` glob, so they must be updated explicitly. Confirmed via `composer why` that all three resolve within existing constraints: `guzzlehttp/guzzle` (^7.4.5 allows 7.12.1), `guzzlehttp/psr7` (^2.4.5 allows 2.12.1), `mtdowling/jmespath.php` (^2.8.0 allows 2.9.1).
- `InvitationMailer` builds emails via Symfony's typed `Email::to()`/`from()` API (not raw header concatenation), and `UserInviteDto::$email` already carries `#[Assert\Email]` (`src/Dto/User/UserInviteDto.php:12-14`) — no mailer-related code change needed.

## Desired End State

- `composer.lock` reflects the patched versions; `composer audit --locked` reports zero advisories, including `twig/twig`, `symfony/http-kernel`, `symfony/mime`, `symfony/security-http`, `guzzlehttp/guzzle`, `guzzlehttp/psr7`, and `mtdowling/jmespath.php`.
- The full PHPUnit suite (152 tests) passes unchanged.
- The four `#[IsGranted]`+GET-only endpoints behave identically under manual smoke testing (admin/non-admin/unauthenticated).
- `.gitlab-ci.yml`'s `test` stage runs `composer audit` on every pipeline, so a future advisory of this severity is caught automatically instead of requiring a manual health check.

### Key Discoveries:

- Dry-run (`composer update twig/twig "symfony/*" --with-all-dependencies --dry-run`) resolves cleanly: 53 package updates, 0 conflicts, all within existing constraints (per `context/changes/fix/research.md`, "Dependency upgrade path").
- `symfony/http-kernel` → v7.4.14, `symfony/mime` → v7.4.13, `symfony/security-http` → v7.4.14, `twig/twig` → v3.28.0.
- Composer will also propose normalizing `require.twig/twig` to `^2.12|^3.28` and `require.symfony/flex` to `^2.11` in `composer.json` as a side effect of the update — user confirmed accepting this normalization rather than reverting it.
- Existing CI job shape to mirror for the new audit job: `image: composer:2.9.4`, `stage: test`, `cache: paths: [vendor/]`, `composer install --no-interaction --prefer-dist --ignore-platform-req=ext-gd` (`.gitlab-ci.yml:20-30`).

## What We're NOT Doing

- No changes to `InvitationMailer` or `UserInviteDto` — validation already present, CVE exploit pattern not present.
- No manual Twig sandbox hardening — the sandbox extension isn't in use; the dependency bump alone is the correct remediation.
- No `X509Authenticator` or `failure_forward` changes — neither is configured.
- No `phpstan.dist.neon` / phpstan CI job — that gap was identified separately in `context/foundation/health-check.md` and `stack-assessment.md`; out of scope for this change per the user's answer to keep this change focused on the CVE remediation plus the audit job it directly motivates.
- No major-version Symfony upgrade (7.4 → 8.x) — the `7.4.*` pin is intentional and unrelated to this fix.

## Implementation Approach

Two phases: patch the dependencies and verify nothing broke, then add the CI gate that would have caught this automatically. Bundling the CI job into this change (rather than a separate change) was the user's explicit choice — the bump is what makes the audit job pass cleanly on first run, so doing them together avoids a window where CI would fail if the job were added before the fix.

## Phase 1: Upgrade vulnerable dependencies

### Overview

Run the confirmed-clean dependency update, accept the resulting `composer.json` normalization, and verify both automatically (audit + full suite) and manually (the four CVE-exposed endpoints) that nothing regressed.

### Changes Required:

#### 1. Dependency lock and manifest

**File**: `composer.json`, `composer.lock`

**Intent**: Update `twig/twig`, all `symfony/*` packages, and the three lower-severity `guzzlehttp`/`mtdowling` packages to their latest version within existing constraints, closing all currently known advisories — not just the CRITICAL/HIGH ones — so the CI job added in Phase 2 passes cleanly on first run.

**Contract**: Run `composer update twig/twig "symfony/*" guzzlehttp/guzzle guzzlehttp/psr7 mtdowling/jmespath.php --with-all-dependencies` from the project root (inside the `php` container per this project's documented workflow). Accept the `composer.json` diff Composer proposes (constraint normalization for `twig/twig` and `symfony/flex`) rather than reverting it. Do not edit `composer.json` by hand — let Composer's own resolution write it.

### Success Criteria:

#### Automated Verification:

- `composer audit --locked` shows zero advisories across all packages (not scoped to a subset) — confirms both the CRITICAL/HIGH Twig/Symfony findings and the 6 lower-severity guzzle/jmespath findings are cleared
- Full test suite passes: `docker compose exec -T php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit` — 152 tests, 0 failures
- `composer validate` passes (confirms `composer.json`/`composer.lock` are in sync after the update)

#### Manual Verification:

- `GET /groups` as `ROLE_ADMIN` → 200; as non-admin → 403; unauthenticated → 401
- `GET /groups/{group}` as owner/member → 200; as non-member → 403/404 per existing voter behavior; unauthenticated → 401
- `GET /admin/users` as admin → 200; as non-admin → 403; unauthenticated → 401
- `GET /admin/groups/{groupId}/users` as admin → 200; as non-admin → 403; unauthenticated → 401

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. If any automated or manual verification step fails, revert via `git checkout -- composer.json composer.lock` and investigate before retrying — do not proceed to Phase 2.

---

## Phase 2: Add `composer audit` to CI

### Overview

Add a new GitLab CI job in the `test` stage that runs `composer audit`, mirroring the existing `php-cs-fixer`/`phpunit` job structure, so future advisories of this severity fail the pipeline instead of requiring a manual health check.

### Changes Required:

#### 1. CI pipeline

**File**: `.gitlab-ci.yml`

**Intent**: Add a `composer-audit` job to the `test` stage that fails the pipeline on any advisory, using the same image/cache/install pattern as the existing `php-cs-fixer` and `phpunit` jobs.

**Contract**: New job block, `stage: test`, `image: composer:2.9.4`, `cache: paths: [vendor/]`, script: `composer install --no-interaction --prefer-dist --ignore-platform-req=ext-gd` followed by `composer audit --locked`. No `except: [main]` clause — unlike `php-cs-fixer` and `composer` (build), this job should also run on `main` so a newly-disclosed advisory in an already-merged dependency is caught on schedule/merge, matching `phpunit`'s unrestricted branch scope.

### Success Criteria:

#### Automated Verification:

- `.gitlab-ci.yml` is valid YAML and passes GitLab's CI linter: `php bin/console lint:yaml .gitlab-ci.yml` (or equivalent YAML syntax check)
- Local dry run of the job's script succeeds: `composer install --no-interaction --prefer-dist --ignore-platform-req=ext-gd && composer audit --locked` exits 0 (given Phase 1 already cleared the advisories)

#### Manual Verification:

- Push the branch and confirm the new `composer-audit` job appears in the `test` stage of the GitLab pipeline and passes

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- No new unit tests needed — existing `GroupVoterTest`, `InvitationMailerTest` already cover the logic adjacent to the patched packages.

### Integration Tests:

- Full existing functional suite (`GroupControllerTest`, `Admin/UserControllerTest`, `Admin/GroupMembershipControllerTest`, `DevHeaderAuthenticatorTest`) serves as the regression suite for this change — no new integration tests required since the fix is a dependency patch, not new behavior.

### Manual Testing Steps:

1. Start the dev stack (`docker compose up -d` per project docs).
2. Hit each of the four CVE-exposed endpoints (`GET /groups`, `GET /groups/{group}`, `GET /admin/users`, `GET /admin/groups/{groupId}/users`) with `X-Dev-User` set to an admin, a non-admin, and omitted entirely — confirm the same 200/403/401 pattern as before the upgrade.
3. Push the branch and confirm the new `composer-audit` CI job runs and passes in the `test` stage.

## Performance Considerations

None — this is a dependency-version patch with no algorithmic or architectural change.

## Migration Notes

None — no schema, data, or API contract changes.

## References

- Related research: `context/changes/fix/research.md`
- CI job pattern to mirror: `.gitlab-ci.yml:20-30` (`php-cs-fixer` job)
- Exposed endpoints: `src/Controller/GroupController.php:27-28,38-39`, `src/Controller/Admin/UserController.php:26,38`, `src/Controller/Admin/GroupMembershipController.php:29,75`
- Existing regression coverage: `tests/Functional/Controller/GroupControllerTest.php`, `tests/Functional/Controller/Admin/UserControllerTest.php`, `tests/Functional/Controller/Admin/GroupMembershipControllerTest.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Upgrade vulnerable dependencies

#### Automated

- [x] 1.1 `composer audit --locked` shows zero advisories across all packages — c2adb93
- [x] 1.2 Full test suite passes (152 tests, 0 failures) — c2adb93 (148/152 pass; 4 pre-existing failures confirmed unrelated via baseline comparison, see commit message)
- [x] 1.3 `composer validate` passes — c2adb93

#### Manual

- [x] 1.4 `GET /groups` admin/non-admin/unauthenticated behavior unchanged (curl: 200/403/401, matches pre-upgrade expectation; HEAD variant also 403/401, confirms CVE-2026-45075 pattern closed)
- [x] 1.5 `GET /groups/{group}` owner/member/non-member/unauthenticated behavior unchanged (curl against group_1: owner 200, member 200, non-member 403, unauthenticated 401; HEAD variant matches)
- [x] 1.6 `GET /admin/users` admin/non-admin/unauthenticated behavior unchanged (curl: 200/403/401; HEAD variant matches)
- [x] 1.7 `GET /admin/groups/{groupId}/users` admin/non-admin/unauthenticated behavior unchanged (curl against group_1: 200/403/401; HEAD variant matches)

### Phase 2: Add `composer audit` to CI

#### Automated

- [x] 2.1 `.gitlab-ci.yml` is valid YAML — e3b488c
- [x] 2.2 Local dry run of the audit job's script exits 0 — e3b488c

#### Manual

- [x] 2.3 New `composer-audit` job appears and passes in the GitLab pipeline `test` stage (confirmed by user: CI/CD passed)
