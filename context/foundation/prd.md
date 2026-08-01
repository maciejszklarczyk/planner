---
project: Plan
version: 1
status: draft
created: 2026-07-22
context_type: brownfield
product_type: api
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  delivery_weeks: 3
  hard_deadline: null
  after_hours_only: true
---

## Current System Overview

Plan — API (PHP 8.4, Symfony 7.4, Doctrine ORM 3.6, PostgreSQL 16, Redis 7,
FrankenPHP) do planowania i rozliczania wspólnych wycieczek. Frontend
(Next.js 16 + React 19) konsumuje API osobno.

Zaimplementowane dziś:
- **Event** — pełny CRUD (`Event` entity, `EventController`, `EventMapper`),
  soft-delete. Pola: `name`, `startDate`, `endDate`, `location`. Brak
  jakiegokolwiek pola ownera, grupy czy uczestników.
- **Group** — encja z relacją `UserHasGroup` (rola `owner|member`,
  `addedBy`), ale całe zarządzanie (create/add-user/remove-user/update-role)
  jest wyłącznie pod `#[IsGranted('ROLE_ADMIN')]`
  (`Admin\GroupMembershipController`). `GET /groups` też jest admin-only.
  Zwykły user nie ma żadnego endpointu do własnych grup.
- **User invitation (onboarding)** — `UserInvitationToken` (token sha256,
  ważność 1 dzień) + `InvitationController` (`/invitation/verify`,
  `/invitation/complete`) — służy do ustawienia hasła nowemu userowi, nie do
  zapraszania na event.
- Auth: sesje HTTP + `json_login` (`/auth/login`), dev: `X-Dev-User` header.
- Role: `ROLE_USER`, `ROLE_ADMIN`. Role w grupie: `owner`, `member`.
- Business rule istniejąca: nie można usunąć ostatniego ownera z grupy
  (`CannotRemoveLastOwnerException`).

Użytkownicy dziś: wczesny etap, praktycznie deweloper/testerzy. Docelowo:
grupy znajomych wspólnie planujące i rozliczające wycieczki.

## Problem Statement & Motivation

Trzy domeny (Event, Group, User) zostały zbudowane jako niezależne,
scaffold-first CRUD-y — każda solidna i przetestowana sama w sobie, ale bez
warstwy relacji, która łączy je w spójny produkt: Event nie ma ownera ani
modelu uczestnictwa/zaproszeń, Group nie ma self-service dla zwykłego usera
(create/leave/invite), a domena Friendship (User↔User) nie istnieje wcale.

Trigger: użytkownik (deweloper projektu) chce teraz przejść od szkieletu API
do realnego flow — tworzenie eventu z zaproszeniami, zarządzanie własną
grupą, listy znajomych. Obecny workaround: brak — te capability po prostu
nie istnieją, więc nie ma czego użyć zamiast tego.

## User & Persona

**Primary**: zwykły użytkownik z rolą `ROLE_USER`, członek (lub przyszły
owner) grupy znajomych, który chce zaplanować wspólną wycieczkę — stworzyć
event, zaprosić do niego znajomych, ewentualnie założyć nową grupę bez
udziału admina.

Istniejący użytkownicy, których dotyczy zmiana: wszyscy `ROLE_USER` — dziś
mogą tylko przeglądać/edytować eventy (bez scopingu) i widzieć grupy, do
których admin ich dodał; nie mają żadnej sprawczości nad grupami ani
uczestnictwem w evencie.

### Secondary persona

`ROLE_ADMIN` — pozostaje zarządcą na poziomie systemowym (np. usuwanie grup,
zarządzanie użytkownikami), ale przestaje być jedyną drogą do stworzenia
grupy czy dodania kogoś do eventu.

## Success Criteria

### Primary
- User tworzy event i zaprasza znajomych bezpośrednio (bez konieczności
  zakładania grupy); zaproszony akceptuje i widzi event jako uczestnik
  (read-only). Mieści się w 3 tygodniach pracy po godzinach.

### Secondary
- Zaproszona osoba bez konta dostaje link onboardingowy (reużycie flow
  `UserInvitationToken`) i po ustawieniu hasła od razu widzi event, do
  którego została zaproszona.

### Guardrails
- Istniejący Event CRUD (GET/POST/PUT/DELETE `/events`) dla właściciela
  eventu nie może się zepsuć po dodaniu ownera/uczestników.
- Zaproszony uczestnik (participant) nie może edytować cudzego eventu —
  tylko owner edytuje.

## User Stories

### US-01: User tworzy event i zaprasza znajomego

- **Given** zalogowany `ROLE_USER`, który ma już zaakceptowanego znajomego
  (patrz US-02 — zaproszenie do eventu wymaga uprzedniej znajomości)
- **When** tworzy nowy event i zaprasza znajomego
- **Then** event ma go jako ownera; zaproszony dostaje zaproszenie do
  zaakceptowania/odrzucenia, ważne do `endDate` eventu; przed tą zmianą
  Event nie miał w ogóle pojęcia ownera ani zaproszeń — każdy zalogowany
  user widział i mógł edytować wszystkie eventy

#### Acceptance Criteria
- Zapraszanie do eventu osoby, która nie jest zaakceptowanym znajomym, jest
  odrzucane z jasnym komunikatem (patrz FR-002)
- Event bez ownera (stare rekordy) nie blokuje działania API — patrz
  `## Open Questions`
- Zaproszona osoba bez konta w systemie dostaje link onboardingowy
  (reużycie `UserInvitationToken`)
- Uczestnik ze statusem "declined" nie widzi eventu
- Zaproszenie nieodpowiedziane do `endDate` eventu wygasa automatycznie

### US-02: User buduje listę znajomych

- **Given** zalogowany `ROLE_USER`
- **When** wysyła zaproszenie do innego usera po emailu
- **Then** zaproszony może zaakceptować/odrzucić; po akceptacji obaj widzą
  się nawzajem na liście znajomych; przed tą zmianą nie istniała żadna
  relacja User↔User poza członkostwem w grupie

#### Acceptance Criteria
- Zaproszenie do znajomych nie wymaga wspólnej grupy
- Duplikat zaproszenia (już wysłane, już znajomi) jest odrzucany z jasnym
  komunikatem

## Scope of Change

### Event & invitations

- [modified] FR-001: User can create an event and becomes its owner. Priority: must-have.
  > Socratic: Counter-argument considered: none raised (old Event records lack
  > an owner). Resolution: kept as written; ownership assignment for
  > pre-existing events tracked in `## Open Questions`.
- [new] FR-002: Event owner can invite another user to the event, but ONLY if that user is already an accepted friend. Priority: must-have.
  > Socratic: Counter-argument considered: "inviting by raw email without a
  > friendship check is a spam/abuse vector, and event invites should build
  > on the friend graph, not bypass it." Resolution: FR revised — event
  > invitations require prior accepted friendship (FR-005/FR-006). Non-friend
  > invites are out of MVP scope.
- [new] FR-003: Invited user can accept or decline an event invitation; the invitation expires at the event's end date. Priority: must-have.
  > Socratic: Counter-argument considered: "no expiry means an invitation
  > could be accepted months after the event." Resolution: invitation expires
  > at the event's `endDate`.
- [modified] FR-004: Event participant (accepted invitee) can view the event but cannot edit or delete it — only the owner can. Priority: must-have.
  > Socratic: Counter-argument considered: none raised (single-owner model,
  > no co-ownership transfer). Resolution: kept as written for MVP; ownership
  > transfer / co-owners is out of scope.

### Friendship

- [new] FR-005: User can send a friend request to another user. Priority: must-have.
  > Socratic: Counter-argument considered: "no protection against a user
  > spamming friend requests." Resolution: no rate limit in MVP; tracked in
  > `## Open Questions`.
- [new] FR-006: Invited user can accept or decline a friend request; declining starts a cooldown before the same sender can re-send to the same recipient. Priority: must-have.
  > Socratic: Counter-argument considered: "without blocking, a declined
  > sender can immediately re-send and effectively spam." Resolution: cooldown
  > period added after decline; exact cooldown length tracked in
  > `## Open Questions`.
- [new] FR-007: User can view their list of friends. Priority: must-have.
  > Socratic: Counter-argument considered: "should show shared events with
  > each friend." Resolution: kept as plain list for MVP; shared-events
  > enrichment is a future nice-to-have, not blocking.

### Group self-service

Świadomie odroczone poza tę zmianę — brak pozycji w Scope of Change tej
rundy. Patrz `## Non-Goals` i `## Open Questions`.

### Preserved

- [preserved] Istniejący Event CRUD (GET/POST/PUT/DELETE `/events`) dla
  właściciela eventu.
- [preserved] Onboardingowy flow `UserInvitationToken`
  (`/invitation/verify`, `/invitation/complete`).
- [preserved] `Admin\GroupMembershipController` (admin-only zarządzanie
  grupami) — bez zmian.
- [preserved] Reguła `CannotRemoveLastOwnerException` (nie można usunąć
  ostatniego ownera z grupy).

## Constraints & Compatibility

- Flow onboardingowy oparty o `UserInvitationToken` musi zostać zachowany
  bez zmian — zaproszenie do eventu dla osoby bez konta go reużywa, nie
  zastępuje.
- Projekt jest we wczesnej fazie — kształt odpowiedzi istniejących
  endpointów (`/events` i pochodnych) MOŻE się zmienić w ramach tej zmiany;
  nie jest to constraint. Frontend jest rozwijany równolegle.
- Istniejący admin flow zarządzania grupami zostaje nietknięty —
  self-service grup jest odroczone, nie zastępuje admin flow.

## Business Logic Changes

**System wymusza: zaproszenie do eventu wymaga uprzedniej akceptowanej
znajomości między zapraszającym a zapraszanym — nie można zaprosić do
eventu osoby, która nie jest jeszcze na liście znajomych.**

Ta reguła spina obie nowe domeny (Friendship, Event participation) w jeden
spójny model: znajomość jest bramką do zaproszenia na wycieczkę, nie
odwrotnie. Input: para (zapraszający, zapraszany) + status ich relacji
Friendship. Output: dopuszczenie lub odrzucenie próby zaproszenia do
eventu. User doświadcza tego jako komunikat błędu przy próbie zaproszenia
osoby spoza listy znajomych.

## Access Control Changes

**Bez zmian**: model auth (sesje HTTP + `json_login`), role systemowe
`ROLE_USER`/`ROLE_ADMIN`.

**Nowe granice uprawnień (self-service, w obrębie `ROLE_USER`) — w scope
tej zmiany**:
- Owner/twórca eventu widzi i edytuje swój event.
- Zaproszony i zaakceptowany uczestnik eventu widzi event, ale nie edytuje.

**Odroczone poza scope tej zmiany**: self-service zarządzania grupami przez
zwykłego `ROLE_USER` (create/invite/remove/role-change) — dziś nadal
wyłącznie przez istniejący admin flow. Istniejąca reguła zakazu usuwania
ostatniego ownera grupy zostaje zachowana bez zmian w międzyczasie.

`ROLE_ADMIN` zachowuje pełny dostęp do wszystkich grup/eventów niezależnie
od członkostwa (obecny model administracyjny, bez zmian).

## Non-Goals

- **Self-service grup** — tworzenie/zarządzanie grupami przez zwykłego
  `ROLE_USER` jest odroczone poza tę zmianę. Rationale: użytkownik nie jest
  pewien docelowego kształtu (czy grupa to w ogóle potrzebny koncept skoro
  jest Friendship, czy grupa ma być "paczką" znajomych do szybkiego
  zapraszania na event) — patrz `## Open Questions`.
- **Zapraszanie do eventu osób spoza listy znajomych** — event invite
  wymaga uprzedniej akceptowanej znajomości (FR-002); zapraszanie po samym
  emailu bez relacji Friendship nie wchodzi w MVP.
- **Współwłasność/transfer ownera eventu** — jeden owner na event, bez
  mechanizmu przekazania własności czy co-ownerów.

## Open Questions

1. **Kto powinien zostać właścicielem eventów utworzonych przed
   wprowadzeniem tej zmiany?** — Owner: user. Block: yes (FR-001 wymaga
   ownera na każdym evencie).
2. **Jaki limit (jeśli jakikolwiek) powinien obowiązywać dla wysyłania
   zaproszeń do znajomych (FR-005)?** — Owner: user. Block: no — MVP
   startuje bez limitu.
3. **Jak długi powinien być cooldown przed ponownym wysłaniem zaproszenia
   do znajomych po odrzuceniu (FR-006)?** — Owner: user. Block: yes (FR-006
   jest niekompletny bez tej wartości).
4. **Czy i w jakiej formie grupy self-service powinny wrócić do scope —
   np. grupa jako "paczka" znajomych do szybkiego zapraszania na event,
   zamiast (lub obok) relacji Friendship?** — Owner: user. Block: no —
   odroczone jako Non-Goal tej zmiany.
