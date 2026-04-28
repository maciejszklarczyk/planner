# Planner Backend API

Symfony 7.4 + PHP 8.4 + FrankenPHP + PostgreSQL 16.

## Uruchamianie (dev)

```bash
# z projektu root (zalecane — startuje backend + frontend razem)
./herald/herald.sh up

# lub tylko backend
docker compose up -d
```

- API: http://localhost:8000
- Swagger UI: http://localhost:8000/api/doc

## Komendy

```bash
# Migracje
bin/console doctrine:migrations:migrate
bin/console doctrine:migrations:diff

# Fixtures (wewnątrz kontenera)
docker exec planner-php php bin/console doctrine:fixtures:load --no-interaction
# lub przez herald:
./herald/herald.sh db-reset

# Cache
bin/console cache:clear

# Debug
bin/console debug:router
bin/console debug:container
```

## Testy i jakość kodu

```bash
# Wszystkie sprawdzenia (CS + PHPStan + testy)
composer run-all

# Tylko testy
docker compose run --rm php env $(cat .env.test | grep -v '^#' | xargs) bin/phpunit

# CS Fixer
composer cs-fix           # napraw
composer cs-fix-analyse   # dry-run

# PHPStan
composer phpstan

# Coverage (wymaga Xdebug)
composer run-coverage
```

> Testy wymagają `.env.test`. Bez niego `createClient()` rzuci `LogicException`.
> Config PHPUnit: `phpunit.dist.xml`.

## Środowisko

Wymagane zmienne (patrz `.env.example`):

| Zmienna | Cel |
|---------|-----|
| `DATABASE_URL` | PostgreSQL DSN |
| `REDIS_URL` | Redis DSN (cache + rate limiter) |
| `MAILER_DSN` | SMTP (Mailpit w dev) |
| `APP_SECRET` | Symfony security secret |
| `S3_ENDPOINT`, `S3_KEY`, `S3_SECRET`, `S3_BUCKET`, `S3_REGION` | Przechowywanie avatarów |
| `FRONTEND_URL` | Adres frontendu (CORS) |

## Dokumentacja codebase

Szczegółowa wiedza o projekcie w `docs/codebase/`:

| Plik | Zawartość |
|------|-----------|
| [STACK.md](docs/codebase/STACK.md) | Runtime, frameworki, zależności, komendy |
| [STRUCTURE.md](docs/codebase/STRUCTURE.md) | Struktura katalogów, encje, granice modułów |
| [ARCHITECTURE.md](docs/codebase/ARCHITECTURE.md) | Warstwy, przepływ danych, wzorce |
| [CONVENTIONS.md](docs/codebase/CONVENTIONS.md) | Nazewnictwo, formatowanie, obsługa błędów |
| [INTEGRATIONS.md](docs/codebase/INTEGRATIONS.md) | Baza danych, Redis, S3, mailer |
| [TESTING.md](docs/codebase/TESTING.md) | Frameworki testowe, układ plików, mocking |
| [CONCERNS.md](docs/codebase/CONCERNS.md) | Dług techniczny, ryzyka, luki bezpieczeństwa |

## Licencja

Proprietary
