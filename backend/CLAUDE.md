# Instrukcje projektowe

Poniżej znajdziesz wytyczne dla tego projektu. Uzupełniaj je w punktach.

## Ogólne zasady

-

## Stack technologiczny

- **PHP 8.4** (FrankenPHP jako serwer)
- **Symfony 7.4**
- **PostgreSQL 16** (ORM: Doctrine 3.6 + migracje)
- **Redis 7** (cache, sesje, rate limiter, locki)
- **Mailpit** (SMTP dev)
- Nelmio API Doc Bundle (OpenAPI/Swagger pod `/api/doc`)
- Nelmio CORS Bundle
- Stof Doctrine Extensions (SoftDeleteable, naming strategy)
- Alice + Fixtures Bundle (dane testowe)
- PHPUnit 12
- PHP CS Fixer (styl PSR-12 + Symfony)
- Symfony Maker Bundle (generowanie kodu)
- Xdebug 3 (dev/test)
- Docker Compose (dev) + Traefik (prod)

## Konwencje kodu

- Routing przez atrybuty PHP `#[Route(...)]` (nie YAML/XML)
- Kontrolery dziedziczą po `AbstractController` Symfony
- DTOs walidowane przez `#[Assert\...]` + `$validator->validate()`
- Encje mapowane przez atrybuty Doctrine (`#[ORM\Entity]`, `#[ORM\Column]`, itp.)
- Soft delete: Gedmo `SoftDeleteable` — encje mają pole `deletedAt`, nie są fizycznie usuwane
- Enumy PHP 8.1 (np. `UserStatusEnum`, `UserGroupRoleEnum`) z metodą `::from()`
- Wyjątki domenowe w `src/Exception/` — rzucane z serwisów, łapane w kontrolerach
- Response DTOs w `src/Dto/Response/` — transformacja encji do JSON
- Request DTOs w `src/Dto/` — walidacja inputu
- CS Fixer skonfigurowany dla PSR-12 + styl Symfony: `vendor/bin/php-cs-fixer fix`
- Admin endpointy zabezpieczone przez `#[IsGranted('ROLE_ADMIN')]`

## Ważne ścieżki i pliki

- `src/Controller/Admin/GroupMembershipController.php` — zarządzanie członkami grup
- `src/Service/GroupMembershipService.php` — logika biznesowa grup (add/remove/update role)
- `src/Entity/UserHasGroup.php` — encja łącząca User ↔ Group z rolą
- `src/Entity/Enum/UserGroupRoleEnum.php` — `owner | member`
- `src/Exception/CannotRemoveLastOwnerException.php` — rzucana gdy próbujemy usunąć ostatniego ownera
- `config/packages/security.yaml` — konfiguracja sesji, json_login, access_control
- `config/packages/nelmio_cors.yaml` — CORS (allowed origins: localhost:3000, planner.msolve.it)
- `.env.test` — wymagany do testów funkcjonalnych (patrz sekcja Testy)

## Uruchamianie projektu

- Serwer deweloperski: `docker compose up`

## Testy

- Testy funkcjonalne wymagają załadowania `.env.test` jako zmiennych środowiskowych — **bez tego Symfony nie załaduje `framework.test: true`** i `createClient()` rzuci LogicException.
- Poprawne uruchamianie testów z CLI (poza PhpStorm):
  ```
  docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit
  ```
- PhpStorm uruchamia testy poprawnie przez konfigurację Docker Compose z polem "Environment variables" ustawionym na `.env.test`.
- Plik konfiguracji PHPUnit: `phpunit.dist.xml` (nie `phpunit.xml.dist` — to stary plik).
- Fixtures są w pełni deterministyczne — nie używają losowych przypisań. Mapa grup:
  - `group_1`: admin=owner, user_1=member, user_2=member
  - `group_2`: admin=member, user_1=owner, user_3=member
  - `group_3`: user_2=owner, user_3=member, user_4=member
  - `group_4`: user_4=owner, user_5=member
  - `group_5`: user_5=owner

## Inne uwagi

- Rate limiting na logowaniu: 3 próby w 15 minutach (`symfony/rate-limiter`)
- Maile (zaproszenia) przez Mailpit w dev (`symfony/mailer`)
- Token zaproszenia: `bin2hex(random_bytes(32))`, ważność 1 dzień
- `UserRepository` obsługuje paginację i wyszukiwanie (parametr `search`) oraz `excludeGroupId`
- Swagger/OpenAPI dostępny pod `/api/doc` (Nelmio API Doc Bundle)
- Naming strategy Doctrine: `underscore_number_aware` (snake_case w bazie)
