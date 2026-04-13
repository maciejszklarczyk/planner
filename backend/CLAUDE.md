# Instrukcje projektowe

Wytyczne projektu. Uzupełniaj w punktach.

## Ogólne zasady

- Do generowania commit messages używaj skilla `git-commit`

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
- Soft delete: Gedmo `SoftDeleteable` — encje mają pole `deletedAt`, nie usuwane fizycznie
- Enumy PHP 8.1 (np. `UserStatusEnum`, `UserGroupRoleEnum`) z metodą `::from()`
- Wyjątki domenowe w `src/Exception/` — rzucane z serwisów, łapane w kontrolerach
- Response DTOs w `src/Dto/Response/` — transformacja encji do JSON
- Request DTOs w `src/Dto/` — walidacja inputu
- CS Fixer dla PSR-12 + styl Symfony: `vendor/bin/php-cs-fixer fix`
- Admin endpointy: `#[IsGranted('ROLE_ADMIN')]`
- Domena osobna dla frontu i API — brak segmentu `/api/` w URL endpointów

## Ważne ścieżki i pliki

- `src/Controller/Admin/GroupMembershipController.php` — zarządzanie członkami grup
- `src/Service/GroupMembershipService.php` — logika grup (add/remove/update role)
- `src/Entity/UserHasGroup.php` — encja User ↔ Group z rolą
- `src/Entity/Enum/UserGroupRoleEnum.php` — `owner | member`
- `src/Exception/CannotRemoveLastOwnerException.php` — rzucana przy próbie usunięcia ostatniego ownera
- `config/packages/security.yaml` — sesje, json_login, access_control
- `config/packages/nelmio_cors.yaml` — CORS (allowed origins: localhost:3000, planner.msolve.it)
- `.env.test` — wymagany do testów funkcjonalnych

## Uruchamianie projektu

- Serwer deweloperski: `docker compose up`

## Testy

- Testy funkcjonalne wymagają `.env.test` jako zmiennych środowiskowych — bez tego Symfony nie załaduje `framework.test: true` i `createClient()` rzuci LogicException.
- Uruchamianie z CLI (poza PhpStorm):
  ```
  docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit
  ```
- PhpStorm: konfiguracja Docker Compose z polem "Environment variables" = `.env.test`.
- Plik konfiguracji PHPUnit: `phpunit.dist.xml` (nie `phpunit.xml.dist` — stary plik).
- Fixtures deterministyczne — bez losowych przypisań. Mapa grup:
  - `group_1`: admin=owner, user_1=member, user_2=member
  - `group_2`: admin=member, user_1=owner, user_3=member
  - `group_3`: user_2=owner, user_3=member, user_4=member
  - `group_4`: user_4=owner, user_5=member
  - `group_5`: user_5=owner

## Inne uwagi

- Rate limiting logowania: 3 próby / 15 min (`symfony/rate-limiter`)
- Maile przez Mailpit w dev (`symfony/mailer`)
- Token zaproszenia: `bin2hex(random_bytes(32))`, ważność 1 dzień
- `UserRepository` obsługuje paginację, wyszukiwanie (parametr `search`) i `excludeGroupId`
- Swagger/OpenAPI pod `/api/doc` (Nelmio API Doc Bundle)
- Naming strategy Doctrine: `underscore_number_aware` (snake_case w bazie)