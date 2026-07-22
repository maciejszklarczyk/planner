# Frame Brief: Trip domain model — events, groups, friends

> Framing step before /10x-plan. Captures what's actually at issue vs what was
> initially assumed.

## Reported Observation

API dla planowania/rozliczania wycieczek — dużo już zrobione, ale "brak ładu
i składu". Trzeba przeanalizować co jest zaimplementowane i czego brakuje w
trzech obszarach: tworzenie eventów wraz z zaproszeniami, zarządzanie
grupami, listy znajomych. Wydatki na razie poza scope (zewnętrzny system).

## Initial Framing (preserved)

- **User's stated cause**: implementacja istnieje, ale jest nieuporządkowana
  ("brak ładu i składu") — sugeruje refactor/porządkowanie istniejącego kodu.
- **User's proposed direction**: audyt stanu + plan dla 3 features (eventy+
  zaproszenia, grupy, znajomi).
- **Pre-dispatch narrowing**: użytkownik wybrał (1) wszystkie 3 obszary razem
  jako jeden spójny model, (2) event MOŻE istnieć bez grupy (zapraszanie
  pojedynczych osób/znajomych bez konieczności tworzenia grupy), (3) znajomi
  to nowa, osobna relacja User↔User — nie nadużycie mechanizmu grup.

## Dimension Map

1. **Event ownership/access** — kto może tworzyć/widzieć/edytować event.
   `Event` entity ma zero pól ownera/grupy.
2. **Event participants/invitations** — mechanizm zapraszania do eventu.
   Nie istnieje w ogóle.
3. **Group self-service** — czy zwykły user może tworzyć/zarządzać własną
   grupą, czy tylko admin.  ← częściowo pasuje do initial framing
4. **Friends domain** — relacja User↔User poza grupami. Nie istnieje w ogóle
   (brak encji, kontrolera, DTO).
5. **Invitation infrastructure reuse** — czy istniejący `UserInvitationToken`
   (email-token, 1 dzień ważności, do onboardingu użytkownika) da się/powinno
   się reużyć dla zaproszeń do eventu/znajomych, czy to inny mechanizm.

## Hypothesis Investigation

| Hypothesis | Evidence | Verdict |
|---|---|---|
| (1) Event ma zerowy model ownera/uczestników | `src/Entity/Event.php` — pola: `id, name, startDate, endDate, location`. Brak `owner`, `group`, `participants`. `EventController::getEventsCollection()` (`src/Controller/EventController.php:31`) robi `findAll()` bez scoping po userze/grupie. | STRONG |
| (2) Zaproszenia do eventu nie istnieją | Brak `EventInvitation`/podobnej encji w `src/Entity/`. Jedyny invitation flow to `UserInvitationToken` — służy do onboardingu konta (ustawienie hasła), nie do zapraszania na event (`src/Controller/InvitationController.php`). | STRONG |
| (3) Grupy są admin-only, brak self-service | `GroupController` (`src/Controller/GroupController.php`) — `GET /groups` ma `#[IsGranted('ROLE_ADMIN')]` (linia 22-23), brak `POST /groups`. Całe tworzenie/członkostwo idzie przez `Admin\GroupMembershipController` pod `#[IsGranted('ROLE_ADMIN')]` na poziomie klasy (linia 27). Zwykły user nie ma żadnego endpointu do zarządzania własną grupą. | STRONG |
| (4) Friends domain nie istnieje | Brak jakiejkolwiek encji `Friend`/`Friendship` w `src/Entity/`, brak kontrolera, DTO. Potwierdzone też w `CLAUDE.md` (lista encji: User, Group, UserHasGroup, Event, UserInvitationToken, UserActivityLog — brak Friend). | STRONG |
| (5) "Brak ładu" jako powód nieuporządkowania kodu (initial framing dosłownie) | CHANGELOG pokazuje Event budowany jako czysty, w pełni CRUD-owy scaffold (`38a92d9`, `a60d803`, `89b7188`) — kod sam w sobie jest spójny/testowany (mapper pattern, DTOs), nie ma śladów bałaganu w istniejącym kodzie. Brak w `context/archive/**` jakiejkolwiek wcześniejszej decyzji projektowej łączącej Event↔Group↔Friends. | WEAK — kod istniejący jest schludny; problemem nie jest jakość kodu, tylko brakująca warstwa łącząca domeny |

## Narrowing Signals

- Użytkownik potwierdził: event nie musi należeć do grupy → to NIE jest brakujące pole `groupId`, tylko brakujący osobny model uczestnictwa (participants/invitees), niezależny od Group.
- Użytkownik potwierdził: friends to osobna relacja User↔User → to nowa domena od zera, nie rozszerzenie `UserHasGroup`.
- Użytkownik wybrał "wszystkie razem" → sygnał, że oczekiwany output planu to jeden spójny model relacji (Event, Group, Friend, invitations), nie trzy niezależne mini-plany.

## Cross-System Convention

W tym repo każda dotychczasowa relacja (Group↔User) idzie przez jawną encję
łącznikową (`UserHasGroup`) z rolą enum, a nie przez pole na encji głównej.
Konwencja wskazuje, że Event↔User (participants) i User↔User (friends)
powinny też dostać własne encje łącznikowe (`EventParticipant`/podobna,
`Friendship`), analogicznie do `UserHasGroup` — nie ManyToMany bez payloadu,
bo już teraz potrzebny jest status (invited/accepted/declined) i data.

## Reframed (or Confirmed) Problem Statement

> **Rzeczywisty problem do zaplanowania**: nie brakuje "porządku" w istniejącym
> kodzie (ten jest schludny i przetestowany) — brakuje **warstwy relacji
> łączącej trzy niezależnie zbudowane domeny** (Event, Group, User) w spójny
> model produktu: kto jest uczestnikiem eventu, kto może go zobaczyć/edytować,
> kto jest czyim znajomym, i jak zwykły user (nie tylko admin) zarządza własną
> grupą.

Initial framing była częściowo trafna (obszary do zaplanowania są właściwe),
ale przyczyna nie jest "brak ładu" w sensie code quality — to brakująca
warstwa domenowa (ownership + participation + friendship), której zbudowane
dotąd CRUD-y nigdy nie miały. Plan powinien projektować te relacje jako
jeden spójny model, nie trzy osobne featury doklejane do istniejących
kontrolerów.

## Confidence

**HIGH** — wszystkie 4 kluczowe hipotezy mają silne dowody (file:line), brak
sprzecznych wcześniejszych decyzji w `context/archive/**`, konwencja repo
(`UserHasGroup`) jednoznacznie wskazuje właściwy wzorzec do reużycia.

## What Changes for /10x-plan

Plan powinien objąć: (1) model uczestnictwa w evencie (encja łącznikowa +
zaproszenia, analogicznie do `UserInvitationToken`/`UserHasGroup`), (2)
self-service zarządzanie grupą dla zwykłego usera (create/leave/invite —
obecnie 100% admin-only), (3) nową domenę Friendship (encja + żądania
zaproszeń), oraz (4) reguły widoczności/uprawnień dla Event (obecnie brak
scoping — `findAll()` zwraca wszystkie eventy wszystkim). Traktować jako
jeden spójny model relacji, nie trzy oddzielne plany.

## References

- Source files:
  - `src/Entity/Event.php`
  - `src/Controller/EventController.php:31`
  - `src/Controller/GroupController.php:22-23`
  - `src/Controller/Admin/GroupMembershipController.php:27`
  - `src/Entity/UserHasGroup.php`
  - `src/Entity/UserInvitationToken.php`
  - `src/Controller/InvitationController.php`
- Related research: brak (`context/changes/trip-domain-model/research.md` nie istnieje)
- Investigation: bezpośrednie odczyty kodu (bez sub-agentów — dowody wystarczająco silne i jednoznaczne po 2 rundach czytania)
