---
date: 2026-07-12T13:15:28+0000
researcher: Maciej Szklarczyk
git_commit: 5f367933c63750024e6a8e575f0743f85202d7a9
branch: main
repository: backend
topic: "Fix Twig and Symfony security vulnerabilities"
tags: [research, codebase, security, dependencies, twig, symfony, composer-audit]
status: complete
last_updated: 2026-07-12
last_updated_by: Maciej Szklarczyk
---

# Research: Fix Twig and Symfony security vulnerabilities

**Date**: 2026-07-12T13:15:28+0000
**Researcher**: Maciej Szklarczyk
**Git Commit**: 5f367933c63750024e6a8e575f0743f85202d7a9
**Branch**: main
**Repository**: backend

## Research Question

`context/foundation/health-check.md` flagged 1 CRITICAL + 10 HIGH `composer audit` advisories concentrated in `twig/twig` and three transitive `symfony/*` packages. Before writing a fix plan: confirm the exact, safe upgrade path (does it stay within existing `composer.json` constraints, does it resolve cleanly); determine whether this codebase actually exercises any of the vulnerable code paths (real exposure vs. theoretical); map existing test coverage that would catch a regression; and check for any relevant prior context.

## Summary

The fix is a same-constraint `composer update twig/twig "symfony/*" --with-all-dependencies` — dry-run confirms it resolves cleanly with no conflicts, staying within the `7.4.*` Symfony pin and the `^2.12|^3.24` Twig constraint in `composer.json`. No composer.json edits needed.

Real exposure is mixed: the Twig sandbox CVEs are **not exploitable here** (sandbox extension isn't registered, no dynamic template rendering), and neither `X509Authenticator` nor `failure_forward` are configured (not exploitable). But the `#[IsGranted]` + GET-only-route HEAD-bypass (`CVE-2026-45075`) **is concretely exposed** — four routes in this codebase combine a GET-only route filter with `#[IsGranted]` authorization, meaning the vulnerable pattern is live in production code, not theoretical. The mailer CRLF-injection surface (`symfony/mime`, `CVE-2026-45067`) uses Symfony's safe typed `Email::to()`/`from()` API rather than raw header concatenation, so it's not directly exploitable via the known pattern, though the recipient string isn't independently re-validated in the mailer itself.

Test coverage is solid for the exposed area: all four `#[IsGranted]`+GET routes already have functional tests asserting 401/403 behavior, so a post-upgrade regression run of the existing suite will meaningfully validate the fix. Coverage gaps exist for header-injection-style inputs and Twig rendering, but neither gap blocks the upgrade — they're pre-existing and orthogonal.

No prior context exists beyond `context/foundation/health-check.md` and `stack-assessment.md`, which already scoped this exact fix and separately flagged that `composer audit` isn't wired into CI.

## Detailed Findings

### Dependency upgrade path

- `symfony/http-kernel` is pulled in by `symfony/framework-bundle` (`^7.4|^8.0`), also referenced by `symfony/security-bundle`, `symfony/twig-bundle`, `symfony/web-profiler-bundle`. No constraint caps it below 7.5.
- `symfony/mime` is pulled in by `symfony/mailer` (`^7.2|^8.0`); `symfony/framework-bundle` and `symfony/twig-bridge` only impose already-satisfied floor constraints.
- `symfony/security-http` is pulled in by `symfony/security-bundle` (`^7.4|^8.0`).
- `composer.json`'s `extra.symfony.require` is pinned to `"7.4.*"` — this is the governing constraint for all Symfony packages project-wide.
- `composer update twig/twig "symfony/*" --with-all-dependencies --dry-run` resolves cleanly: 53 package updates, 0 installs/removals, no conflicts. Specifically proposes:
  - `symfony/http-kernel` v7.4.8 → **v7.4.14** (fixes `CVE-2026-45075`)
  - `symfony/mime` v7.4.8 → **v7.4.13** (fixes `CVE-2026-45067`)
  - `symfony/security-http` v7.4.8 → **v7.4.14** (fixes `CVE-2026-48489`, `CVE-2026-45063`, `CVE-2026-45075`)
  - `twig/twig` v3.24.0 → **v3.28.0** (satisfies existing `^2.12|^3.24` constraint, meets the `>=3.26.0` fix requirement)
  - Plus 49 other `symfony/*` and transitive packages, all staying within `7.4.*`
- Composer also proposes normalizing `require.twig/twig` to `^2.12|^3.28` and bumping `require.symfony/flex` to `^2.11` in `composer.json` — cosmetic, from Flex's auto-bump behavior; can be accepted or left as-is.
- Re-running `composer audit` post-upgrade is necessary to confirm the 4 target packages clear and to see the residual (pre-existing MODERATE/LOW) advisory list.

### Real exposure — vulnerable feature usage in this codebase

**Twig sandbox (CVE-2026-46633 critical, CVE-2026-46640, and other sandbox-bypass CVEs) — not exploitable here.** Only one template exists, `templates/base.html.twig`; no other `.twig` files. No `Sandbox`/`SecurityPolicy` registration anywhere in PHP/YAML/Twig config. No `template_from_string`, `createTemplate`, or dynamic `render($userControlledName, …)` calls. The sandbox extension is simply not in use, so these CVEs — while CRITICAL/HIGH in the advisory — have no live attack surface in this project. The upgrade is still correct to apply (defense-in-depth / future-proofing), just not urgent for this specific reason.

**`#[IsGranted]` + GET-only route (CVE-2026-45075) — exploitable, four live occurrences:**
- `src/Controller/GroupController.php:27-28` — `#[Route('/groups', methods: ['GET'])]` + `#[IsGranted('ROLE_ADMIN')]`
- `src/Controller/GroupController.php:38-39` — `#[Route('/groups/{group}', methods: ['GET'])]` + `#[IsGranted('view', 'group')]`
- `src/Controller/Admin/UserController.php:26,38` — class-level `#[IsGranted('ROLE_ADMIN')]` applies to `#[Route('/admin/users', methods: ['GET'])]`
- `src/Controller/Admin/GroupMembershipController.php:29,75` — class-level `#[IsGranted('ROLE_ADMIN')]` applies to `#[Route('/{groupId}/users', methods: ['GET'])]`

This is the one CVE in the batch with concrete, confirmed exposure in production code — the vulnerable pattern (GET-only route filter + `#[IsGranted]`) is exactly what these four endpoints use. This elevates the priority of the `symfony/http-kernel`/`security-http` half of the fix beyond "hygiene."

**`X509Authenticator` / DN regex (CVE-2026-45063) — not present.** `config/packages/security.yaml` has no `x509` key; only `json_login` and the custom `DevHeaderAuthenticator` (dev/test only, per project's own `CLAUDE.md`). The one `x509` grep hit, in `config/reference.php:1008`, is Symfony's autogenerated documentation stub listing all *possible* config options — not live config.

**`failure_forward` (CVE-2026-48489) — not present.** No `failure_forward` key anywhere in `config/packages/security.yaml`. Same autogenerated-reference-only hits as above.

**Mailer header injection (CVE-2026-45067, symfony/mime) — uses the safe API.** `src/Service/InvitationMailer.php:22-32` builds the email via `(new Email())->from($this->mailerFrom)->to($to)->subject('Zaproszenie do Planner')->html(...)`. `$to` originates from `$dto->email` (`src/Controller/Admin/UserController.php:91,118`). This goes through Symfony's typed `Email::to()`/`from()` API, which normalizes into `Address` objects — not the raw-header-string-concatenation pattern the CVE targets. Worth double-checking (not confirmed in this pass) that the DTO field carries an `#[Assert\Email]` constraint as defense-in-depth, but the specific exploit pattern isn't present.

### Test coverage relevant to the upgrade

**Security/authorization** — all four exposed `#[IsGranted]`+GET routes already have functional test coverage:
- `tests/Functional/Controller/GroupControllerTest.php` — covers `GroupController`'s admin/view/delete IsGranted checks: admin/non-admin (403), unauthenticated (401), owner/member/non-member access.
- `tests/Functional/Controller/Admin/UserControllerTest.php` and `tests/Functional/Controller/Admin/GroupMembershipControllerTest.php` — both class-level `#[IsGranted('ROLE_ADMIN')]`, tests assert 403 for non-admin and 401 for unauthenticated.
- `tests/Unit/Security/GroupVoterTest.php` — unit-level voter logic (abstain/deny/grant across roles and ownership).
- `tests/Functional/Security/DevHeaderAuthenticatorTest.php` — dev auth header edge cases (valid/unknown/missing header, role assignment).

**Mailer** — `tests/Unit/Service/InvitationMailerTest.php` asserts `send()` call, correct From/To, body URL content, and subject string. No test asserts header-injection resistance specifically.

**Twig rendering** — no test renders a Twig template directly; this is a pure JSON API, so the 3.24→3.28 bump has essentially no dedicated regression coverage, consistent with the sandbox feature being unused.

**Gaps noted, not blocking**: no test for malformed/oversized `X-Dev-User` header input; no test asserting email header values resist CRLF injection. Both are pre-existing coverage gaps, not introduced by or required to complete this upgrade.

## Code References

- `src/Controller/GroupController.php:27-28` — GET-only route + `#[IsGranted('ROLE_ADMIN')]`, one of four CVE-2026-45075-exposed endpoints
- `src/Controller/GroupController.php:38-39` — GET-only route + `#[IsGranted('view', 'group')]`
- `src/Controller/Admin/UserController.php:26,38` — class-level `#[IsGranted('ROLE_ADMIN')]` over GET-only `/admin/users`
- `src/Controller/Admin/GroupMembershipController.php:29,75` — class-level `#[IsGranted('ROLE_ADMIN')]` over GET-only `/{groupId}/users`
- `src/Service/InvitationMailer.php:22-32` — mailer using safe typed `Email` API
- `src/Controller/Admin/UserController.php:91,118` — origin of `$dto->email` passed into the mailer
- `config/packages/security.yaml` — no `x509`, no `failure_forward` configured
- `tests/Functional/Controller/GroupControllerTest.php` — covers exposed IsGranted+GET routes
- `tests/Functional/Controller/Admin/UserControllerTest.php` — covers exposed IsGranted+GET routes
- `tests/Functional/Controller/Admin/GroupMembershipControllerTest.php` — covers exposed IsGranted+GET routes
- `tests/Unit/Security/GroupVoterTest.php` — voter unit coverage
- `tests/Unit/Service/InvitationMailerTest.php` — mailer unit coverage
- `composer.json` `extra.symfony.require` — `"7.4.*"` pin governing the safe upgrade range

## Architecture Insights

- Symfony's `extra.symfony.require: 7.4.*` pin is the reason this whole fix is a low-risk patch-level `composer update` rather than a migration — every fixed CVE version (7.4.12–7.4.14) falls inside the existing pin.
- The project's authorization model (role checks + `GroupVoter` for ownership) is exercised almost entirely through GET endpoints in the current route map, which is exactly the shape CVE-2026-45075 targets — a useful thing to know for future route additions, independent of this fix.
- Twig is present in the dependency tree (via `symfony/twig-bundle`/`twig-bridge`) but essentially unused at the application level (one static template, no dynamic rendering) — the project is a pure JSON API per its own `CLAUDE.md` documentation ("Backend wystawia trasy bez `/api/`", DTO-driven responses), which explains why Twig's attack surface here is theoretical rather than real.

## Historical Context (from prior changes)

- `context/changes/fix/change.md` — this change's own identity file, created today via `/10x-new`, no plan yet.
- `context/foundation/health-check.md` — already scoped this exact fix: identical CVE list, identical `composer update twig/twig "symfony/*" --with-all-dependencies` recommendation, and separately flagged that `composer audit` is not run in CI (`.gitlab-ci.yml` `test` stage only runs `php-cs-fixer` + `phpunit`). Recommends adding a `composer audit --locked` CI job.
- `context/foundation/stack-assessment.md` — corroborates the CI-enforcement gap pattern (also true of `phpstan`, which has no config file and no CI job), but doesn't address the Twig/Symfony CVEs directly — that's `health-check.md`'s finding.
- `context/archive/` — empty, no prior completed changes to reference.

## Related Research

None — this is the first research artifact for this change.

## Open Questions

- Should the `composer.json` cosmetic normalization Composer proposes (`twig/twig` constraint → `^2.12|^3.28`, `symfony/flex` → `^2.11`) be accepted as part of this fix, or left untouched to minimize diff scope? (Low-stakes either way — flag for the plan.)
- Should `$dto->email` in the invitation-invite DTO be double-checked for an `#[Assert\Email]` constraint as defense-in-depth for the mailer, even though the current exploit pattern isn't present? (Out of scope for the CVE fix itself, but adjacent — flag for the plan to decide in/out.)
- Should adding `composer audit` to CI (already recommended in `health-check.md`) be bundled into this same change, or split into a separate CI-hardening change? (Both `health-check.md` and `stack-assessment.md` treat it as a related-but-distinct item.)
