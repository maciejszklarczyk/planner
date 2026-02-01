# Planner Backend API

API do zarządzania zadaniami oparte na Symfony 7.4 i Doctrine ORM.

## Wymagania

- PHP >= 8.2
- Composer
- Docker & Docker Compose (opcjonalnie)
- PostgreSQL/MySQL (w zależności od konfiguracji)

## Instalacja

### Lokalna instalacja

```bash
# Instalacja zależności
composer install

# Konfiguracja środowiska
cp .env.example .env
# Edytuj .env i ustaw dane dostępowe do bazy danych

# Migracje bazy danych
php bin/console doctrine:migrations:migrate
```

### Instalacja z Docker

```bash
# Uruchomienie kontenerów
docker-compose up -d

# Instalacja zależności wewnątrz kontenera
docker-compose exec php composer install

# Migracje
docker-compose exec php bin/console doctrine:migrations:migrate
```

## Podstawowe komendy

### Symfony Console

```bash
# Lista wszystkich dostępnych komend
php bin/console list

# Cache
php bin/console cache:clear
php bin/console cache:warmup

# Debugowanie
php bin/console debug:router
php bin/console debug:container
```

### Doctrine

```bash
# Utworzenie bazy danych
php bin/console doctrine:database:create

# Migracje
php bin/console doctrine:migrations:migrate
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:status

# Schema
php bin/console doctrine:schema:validate
php bin/console doctrine:schema:update --dump-sql

# Fixtures (dane testowe)
php bin/console doctrine:fixtures:load --no-interaction
# Z Docker:
docker exec planner-php php bin/console doctrine:fixtures:load --no-interaction
```

### Maker Bundle

```bash
# Utworzenie nowej encji
php bin/console make:entity

# Utworzenie kontrolera
php bin/console make:controller

# Utworzenie migracji
php bin/console make:migration
```

### Docker

```bash
# Uruchomienie kontenerów
docker-compose up -d

# Zatrzymanie kontenerów
docker-compose down

# Logi
docker-compose logs -f

# Wejście do kontenera PHP
docker-compose exec php bash

# Rebuild kontenerów
docker-compose up -d --build
```

### Git

```bash
# Status
git status

# Commit
git add .
git commit -m "commit message"

# Push
git push origin branch-name

# Pull
git pull origin branch-name
```

## Struktura projektu

```
.
├── bin/              # Pliki wykonywalne (console)
├── config/           # Konfiguracja aplikacji
├── docker/           # Pliki Docker
├── docs/             # Dokumentacja
├── fixtures/         # Dane testowe (YAML)
├── migrations/       # Migracje bazy danych
├── public/           # Publiczny katalog (index.php)
├── src/              # Kod źródłowy aplikacji
│   ├── DataFixtures/ # Loadery fixtur
│   ├── Entity/       # Encje Doctrine
│   ├── Repository/   # Repozytoria
│   └── ...
├── var/              # Pliki tymczasowe (cache, logs)
└── vendor/           # Zależności Composer
```

## Fixtures (Dane testowe)

Projekt używa **nelmio/alice** i **hautelook/alice-bundle** do zarządzania danymi testowymi.

### Struktura

```
fixtures/
├── users.yaml              # Definicje użytkowników
├── groups.yaml             # Definicje grup
└── user_has_groups.yaml    # Relacje użytkownik-grupa
```

### Edycja fixtur

Fixtures są definiowane w plikach YAML z użyciem Faker do losowych danych:

```yaml
# fixtures/users.yaml
App\Entity\User:
    user_admin:
        email: 'admin@example.com'
        password: 'password'
        roles: ['ROLE_ADMIN']
        name: 'Admin User'

    user_1:
        email: 'user1@example.com'
        password: 'password'
        roles: []
        name: '<firstName()> <lastName()>'  # Faker
```

### Użyteczne Faker formattery

- `<firstName()>`, `<lastName()>` - losowe imiona/nazwiska
- `<safeEmail()>` - losowy email
- `<sentence()>` - losowe zdanie
- `<randomElement(['option1', 'option2'])>` - losowy wybór
- `<numberBetween(1, 10)>` - losowa liczba

Hasła są automatycznie hashowane przez `UserPasswordProcessor`.

## Konfiguracja środowiska

Główne zmienne środowiskowe w pliku `.env`:

```
APP_ENV=dev
APP_SECRET=your-secret-key
DATABASE_URL="postgresql://user:password@localhost:5432/database?serverVersion=16&charset=utf8"
```

## Dodatkowe informacje

### API Endpoints

(Tutaj dodaj swoje endpointy)

### Troubleshooting

(Tutaj dodaj typowe problemy i rozwiązania)

### Notatki

(Tutaj dodaj swoje notatki)

## Licencja

Proprietary
