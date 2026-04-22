# Planner Backend API

![coverage](https://img.shields.io/badge/coverage-51.8%25-yellow)

Symfony 7.4 + Doctrine ORM API.

## Uruchamianie

```bash
# Docker (zalecane)
docker-compose up -d

# Lokalnie
composer install
cp .env.example .env
php bin/console doctrine:migrations:migrate
symfony serve
```

## Dokumentacja API

- Swagger UI: http://localhost:8000/api/doc
- OpenAPI JSON: http://localhost:8000/api/doc.json

## Komendy

```bash
# Migracje
php bin/console doctrine:migrations:migrate
php bin/console doctrine:migrations:diff

# Fixtures
php bin/console doctrine:fixtures:load --no-interaction
# lub z Docker:
docker exec planner-php php bin/console doctrine:fixtures:load --no-interaction

# Cache
php bin/console cache:clear

# Debug
php bin/console debug:router
php bin/console debug:container
```

## Struktura

```
src/
├── Controller/   # Kontrolery HTTP
├── Entity/       # Encje Doctrine
├── Repository/   # Repozytoria
├── Service/      # Logika biznesowa
├── Dto/          # Request/Response DTOs
└── Exception/    # Wyjątki domenowe
fixtures/
├── users.yaml
├── groups.yaml
└── user_has_groups.yaml
```

## Środowisko

```
APP_ENV=dev
APP_SECRET=your-secret-key
DATABASE_URL="postgresql://user:password@localhost:5432/database?serverVersion=16&charset=utf8"
```

## Licencja

Proprietary
