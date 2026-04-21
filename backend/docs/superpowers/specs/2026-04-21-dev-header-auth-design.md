# Dev Header Authenticator — Design Spec

**Date:** 2026-04-21
**Status:** Approved

## Problem

Switching users during local development requires logging out and logging in again via `/auth/login`. This slows down testing of role-based behaviour across multiple users.

## Solution

A dev-only Symfony authenticator that reads a request header (`X-Dev-User`) and authenticates the user by email — no password, no session. Active only in the `dev` environment via `when@dev:` config block.

## Architecture

### New file: `src/Security/DevHeaderAuthenticator.php`

Implements `AuthenticatorInterface`. Behaviour:

- `supports()`: returns true when `X-Dev-User` header is present
- `authenticate()`: loads user from `UserRepository::findOneBy(['email' => $email])`, returns `SelfValidatingPassport` (no credentials check)
- If user not found: throws `CustomUserMessageAuthenticationException` → 401
- No session badge added → stateless per request

### Config change: `config/packages/security.yaml`

```yaml
when@dev:
    security:
        firewalls:
            main:
                custom_authenticators:
                    - App\Security\DevHeaderAuthenticator
```

Authenticator runs before the session authenticator — header always wins when present.

## Usage

Install [ModHeader](https://modheader.com/) or equivalent. Add header:

```
X-Dev-User: admin@example.com
```

Switch users by changing the value. Remove header to fall back to normal session auth. Clear existing sessions before enabling to avoid conflicts.

## Scope

| Item | Change |
|------|--------|
| `src/Security/DevHeaderAuthenticator.php` | New file |
| `config/packages/security.yaml` | Add `when@dev:` block |

No migrations, no new routes, no frontend changes.

## Security

- Config block is `when@dev:` only — does not exist in `prod` or `test` environments
- No code path reaches prod
- Soft constraint only (anyone with network access to dev server can impersonate any user) — acceptable for local dev
