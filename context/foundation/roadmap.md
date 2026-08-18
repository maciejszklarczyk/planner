---
project: Plan
version: 1
status: draft
created: 2026-07-22
updated: 2026-08-01
prd_version: 1
main_goal: low-complexity
top_blocker: decisions
---

# Roadmap: Plan

> Derived from `context/foundation/prd.md` (v1) + auto-researched codebase baseline.
> Edit-in-place; archive when superseded.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Vision recap

Event, Group i User zostały zbudowane jako niezależne, scaffold-first CRUD-y —
każda solidna sama w sobie, ale bez warstwy relacji łączącej je w spójny
produkt. Event nie ma ownera ani modelu uczestnictwa, a domena Friendship
(User↔User) nie istnieje wcale. Ta zmiana dodaje owner + zaproszenia do
Event oraz nową domenę Friendship, żeby user mógł stworzyć wycieczkę i
zaprosić do niej znajomych — bez potrzeby zakładania grupy.

## North star

**S-02: User tworzy event i zaprasza znajomego** — dokładnie Primary Success
Criterion PRD: pierwszy pełny, end-to-end flow (stworzenie eventu →
zaproszenie → akceptacja → widok uczestnika read-only), który dowodzi, że
cały model relacji działa.

> Gwiazda przewodnia (north star) = najmniejszy kompletny flow, którego
> udane dostarczenie dowodzi, że produkt spełnia swoją podstawową obietnicę.
> Ustawiona tak wcześnie, jak pozwalają na to jej Prerequisites — S-02
> wymaga wcześniej istniejącej znajomości (S-01), więc ląduje jako druga w
> kolejności, zaraz po jedynym bloku, który ją poprzedza.

## At a glance

| ID   | Change ID                  | Outcome (user can …)                                              | Prerequisites | PRD refs                    | Status  |
| ---- | --------------------------- | ------------------------------------------------------------------ | -------------- | ---------------------------- | ------- |
| F-01 | api-exception-handling       | (enabler, not user-facing) Consistent error envelope across the whole API | —      | —                             | done |
| S-01 | friendship-requests          | User wysyła/akceptuje/odrzuca zaproszenie do znajomych, widzi listę | F-01           | US-02, FR-005, FR-006, FR-007 | done |
| S-02 | event-owner-and-invites      | User tworzy event, zaprasza znajomego, ten widzi się jako uczestnik | S-01           | US-01, FR-001, FR-002, FR-003, FR-004 | blocked |

## Baseline

Co już istnieje w kodzie na dzień `2026-07-22` (auto-zbadane + potwierdzone
przez usera). Poniższe slice'y NIE odtwarzają tych warstw od zera.

- **Frontend:** present — Next.js 16 App Router w tym samym monorepo
  (`frontend/`), konsumuje API. Wcześniej traktowany jako osobne repo poza
  zakresem tej rundy; od promocji `friendship-requests` do zmian
  full-stack (`context/changes/friendship-requests/`) frontendowe fazy
  mogą wchodzić w skład slice'ów w tym roadmapie.
- **Backend / API:** present — Symfony 7.4 + FrankenPHP, `EventController`,
  `Admin\GroupMembershipController`, pełny CRUD dla `Event` (`src/Entity/Event.php` —
  pola `name`, `startDate`, `endDate`, `location`, brak `owner`).
- **Data:** present — Doctrine ORM 3.6 + PostgreSQL 16, soft-delete (Gedmo).
  Brak encji Friendship, brak pola owner/participants na `Event`.
- **Auth:** present — sesje HTTP + `json_login`, dev: `X-Dev-User` header
  (`DevHeaderAuthenticator`). Role `ROLE_USER`/`ROLE_ADMIN` bez zmian.
- **Deploy / infra:** present — `docker-compose.yaml`, `.gitlab-ci.yml`.
- **Observability:** absent — brak `config/packages/monolog.yaml`, brak
  Sentry/Datadog. Żaden NFR tej zmiany tego nie wymaga — nie promowane do
  Foundation.

## Foundations

### F-01: Global API exception-handling infrastructure

- **Outcome:** Consistent `{error, message, timestamp, path}` JSON error
  envelope across the entire API (`ApiExceptionInterface` +
  `kernel.exception` listener); every existing controller migrated off
  today's four incompatible ad-hoc error shapes (`{error,message}`,
  `{status,message}`, `{valid,message}`, bare `{message}`).
- **Change ID:** api-exception-handling
- **Prerequisites:** —
- **Enables:** S-01 (`friendship-requests`) needs a consistent exception
  system for its new domain exceptions (self-request, duplicate, cooldown,
  etc.) rather than inventing a fifth ad-hoc shape; S-02 will need the same
  foundation for its own new exceptions (non-friend invite rejection,
  expired invitation, etc.).
- **Risk:** Touches every existing controller (6) and the 3 existing
  domain exceptions — regression risk on already-shipped Event/Group/Auth/
  Invitation/Avatar endpoints. Mitigated by keeping existing HTTP status
  codes unchanged and existing test assertions passing (see
  `backend/context/archive/2026-07-22-api-exception-handling/plan.md`).
- **Status:** done — split out of `friendship-requests` during planning
  once its scope grew beyond that slice; see
  `backend/context/archive/2026-07-22-api-exception-handling/`.

Was: "Brak Foundations w tej rundzie" at initial roadmap generation. Revised
during `/10x-plan friendship-requests`: the exception-handling work turned
out to be a genuine cross-cutting enabler (touches 6 controllers unrelated
to Friendship), not something that belongs inside a single vertical slice —
promoted to a Foundation and split into its own change.

## Slices

### S-01: User buduje listę znajomych

- **Outcome:** User wysyła zaproszenie do innego usera po emailu; zaproszony
  akceptuje/odrzuca; po akceptacji obaj widzą się nawzajem na liście
  znajomych; odrzucenie startuje cooldown przed ponownym wysłaniem.
- **Change ID:** friendship-requests
- **PRD refs:** US-02, FR-005, FR-006, FR-007
- **Prerequisites:** —
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:**
  - ~~Jak długi cooldown po odrzuceniu zaproszenia (FR-006)?~~ — Resolved: 3 dni, wartość konfigurowalna (env), nie hardcoded.
  - Jaki limit (jeśli jakikolwiek) na wysyłanie zaproszeń do znajomych (FR-005)? — Owner: user. Block: no (MVP startuje bez limitu).
- **Risk:** Nowa domena od zera (encja Friendship + relacje User↔User) —
  sekwencjonowana jako pierwsza, bo S-02 (FR-002) wymaga wcześniej
  istniejącej akceptowanej znajomości; bez niej nie da się zweryfikować
  bramki friendship→invite w S-02.
- **Status:** done

### S-02: User tworzy event i zaprasza znajomego

- **Outcome:** User tworzy event i staje się jego ownerem; zaprasza
  zaakceptowanego znajomego; zaproszony akceptuje/odrzuca (zaproszenie wygasa
  na `endDate`); zaakceptowany uczestnik widzi event, ale go nie edytuje.
- **Change ID:** event-owner-and-invites
- **PRD refs:** US-01, FR-001, FR-002, FR-003, FR-004
- **Prerequisites:** S-01 (zaakceptowana znajomość jako warunek zaproszenia — FR-002)
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:**
  - Kto zostaje ownerem eventów utworzonych przed tą zmianą (backfill dla starych rekordów bez owner)? — Owner: user. Block: yes.
- **Risk:** Dodanie ownera do istniejącego, używanego Event CRUD wymaga
  backfillu/migracji bez zepsucia istniejącego flow (guardrail PRD) —
  sekwencjonowana po S-01, żeby bramka friendship→invite miała co
  weryfikować od pierwszego dnia.
- **Status:** blocked

## Backlog Handoff

| Roadmap ID | Change ID              | Suggested issue title                                    | Ready for `/10x-plan` | Notes                                                   |
| ---------- | ----------------------- | ---------------------------------------------------------- | ---------------------- | -------------------------------------------------------- |
| F-01       | api-exception-handling   | Global API exception-handling infrastructure                | done                    | Already planned — `backend/context/archive/2026-07-22-api-exception-handling/plan.md`. Implement before S-01. |
| S-01       | friendship-requests      | Friendship: send/accept/decline requests + friend list      | done                    | Already planned — `context/changes/friendship-requests/plan.md`. Depends on F-01. |
| S-02       | event-owner-and-invites  | Event ownership + friend-gated invitations                   | no                      | Blocked on backfill-owner decision (Open Q1) and on S-01  |
| (unfriend) | *(not yet named)*        | Friendship: remove/end an accepted friendship                | no                      | Parked during S-01 planning — needs a `/10x-new` + `/10x-plan` pass of its own before this MVP round closes |

## Open Roadmap Questions

1. **Kto powinien zostać właścicielem eventów utworzonych przed
   wprowadzeniem tej zmiany?** — Owner: user. Block: S-02.
2. **Jaki limit (jeśli jakikolwiek) powinien obowiązywać dla wysyłania
   zaproszeń do znajomych?** — Owner: user. Block: no (nieblokujące, MVP
   startuje bez limitu).
3. **Czy i w jakiej formie grupy self-service powinny wrócić do scope?** —
   Owner: user. Block: no (roadmap-wide, odroczone jako Non-Goal).

~~Cooldown po odrzuceniu zaproszenia do znajomych (dawne pytanie 3)~~ —
Resolved: 3 dni, konfigurowalne przez env, nie hardcoded. Odblokowuje S-01.

## Parked

- **Usuwanie znajomości (unfriend)** — Why parked: nie jest wymagane przez
  żaden FR w obecnym PRD (FR-005/006/007 pokrywają tylko send/accept/
  decline/list). Zidentyfikowane podczas planowania `friendship-requests`
  jako realna luka (po zaakceptowaniu znajomości nie ma sposobu, by ją
  zakończyć) — do dodania jako osobny slice w tym MVP, nie w scope S-01.
- **Self-service grup** (create/invite/remove/role-change przez zwykłego
  `ROLE_USER`) — Why parked: PRD `## Non-Goals` — niepewny docelowy kształt
  (czy Group ma sens obok Friendship), patrz Open Question 4.
- **Zapraszanie do eventu osób spoza listy znajomych** — Why parked: PRD
  `## Non-Goals` — FR-002 socratic resolution, invite wymaga wcześniejszej
  znajomości; raw-email invite poza MVP.
- **Współwłasność/transfer ownera eventu** — Why parked: PRD `## Non-Goals`
  — jeden owner na event w MVP, bez mechanizmu przekazania własności.

## Done

- **F-01: (enabler, not user-facing) Consistent error envelope across the whole API** — Archived 2026-08-01 → `backend/context/archive/2026-07-22-api-exception-handling/`. Lesson: —.
- **S-01: User wysyła zaproszenie do innego usera po emailu; zaproszony akceptuje/odrzuca; po akceptacji obaj widzą się nawzajem na liście znajomych; odrzucenie startuje cooldown przed ponownym wysłaniem.** — Archived 2026-08-01 → `context/archive/2026-07-22-friendship-requests/`. Lesson: —.
