# Admin Bootstrap Console Command — Plan Brief

> Full plan: `context/changes/admin-load/plan.md`
> Research: `context/changes/admin-load/research.md`

## What & Why

The live server's database gets wiped fairly often while the project is in test status. Because the app is invitation-based, there's no way to log back in afterward — you need an existing admin to send an invite, but the wipe removed the admin too. This adds a `bin/console app:admin:bootstrap <email>` command that creates a `ROLE_ADMIN` user and prints a working invitation link, so you can get back into a freshly-wiped live server yourself.

## Starting Point

Today, `ROLE_ADMIN` can only be granted via a dev/test-only Alice fixture (`fixtures/users.yaml`) with a plaintext password — never through the real invitation flow. `Admin\UserController::sendUserInvite()` hard-codes new users to `ROLE_USER`. No custom console command exists anywhere in this codebase yet.

## Desired End State

Running `docker compose exec -T php php bin/console app:admin:bootstrap you@example.com` against a freshly wiped/migrated live database creates a `ROLE_ADMIN` user and prints a `{FRONTEND_URL}/set-password?token=...` link. Opening that link lets you set a password through the normal frontend flow and log in as admin. Running it again on an email that already exists fails loudly instead of silently re-granting admin.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Mechanism | CLI console command | Matches the existing prod deploy pattern of running one-off `bin/console` commands against the live container. | Research |
| Auth completion | Reuse invitation flow | Operator sets their own password through the existing `/set-password` frontend page instead of a CLI-supplied password. | Research |
| Command name | `app:admin:bootstrap` | Reads as a disaster-recovery action, not routine tooling. | Plan |
| Re-run on existing email | Fail loudly (`UserAlreadyExistsException`) | Matches `sendUserInvite()`'s existing behavior; prevents silently re-granting admin on a live, non-wiped DB. | Plan |
| Token logic | Extract `InvitationTokenService` | Two real call sites (controller + command) crosses the DRY threshold for security-sensitive token logic. | Plan |
| Link delivery | Print to console only | No dependency on a working mail transport — the whole point is recovering when things are broken. | Plan |
| Safety gate | No extra `--force` flag | The existing-user check already blocks the dangerous case; a one-shot recovery command needs to run fast under pressure. | Plan |

## Scope

**In scope:**
- New `app:admin:bootstrap <email>` console command
- Extracting token-creation logic into a shared `InvitationTokenService`
- Functional tests for both the new service (via the existing controller tests) and the new command

**Out of scope:**
- Automating the database wipe itself
- Wiring this into the deploy workflow (auto-seed on deploy)
- `--force`/confirmation flags, email sending, or upsert-on-existing-user behavior

## Architecture / Approach

Phase 1 extracts the existing, working token-creation logic out of `Admin\UserController` into `src/Service/InvitationTokenService.php`, with zero behavior change (proven by the existing test suite passing unchanged). Phase 2 adds the new command on top of that shared service, so the CLI path and the two existing invitation endpoints never have two copies of the raw-token/hash/expiry logic.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Extract InvitationTokenService | Token-creation logic moved to a shared service, controller behavior unchanged | Regression in existing invitation endpoints if the extraction isn't behavior-identical |
| 2. Add app:admin:bootstrap command | New command creates a ROLE_ADMIN user + prints a real invitation link | None significant — small, additive, well-precedented by the existing invitation flow |

**Prerequisites:** None — builds entirely on existing entities/services.
**Estimated effort:** ~1 session, 2 phases.

## Open Risks & Assumptions

- Assumes the operator has SSH/exec access to the live `php` container after a wipe — this plan doesn't change how that access works, only what you run once you have it.
- Assumes `FRONTEND_URL` is correctly set in the live environment (it already is, since `InvitationMailer` depends on it today).

## Success Criteria (Summary)

- After a database wipe, the operator can run one command and get a working admin login within minutes, with no email/SMTP dependency.
- Running the command twice on the same email fails safely instead of creating duplicate or re-privileged accounts.
