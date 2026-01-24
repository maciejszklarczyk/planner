# Backend - Tech Stack

## Podsumowanie

```
Framework:      Symfony (PHP 8.2+)
Baza danych:    PostgreSQL 15
ORM:            Doctrine
Autentykacja:   Session cookies (Symfony Security)
API:            REST
```

---

## Technologie

### Framework: Symfony

Symfony to dojrzały framework PHP, używany w produkcji przez wiele dużych aplikacji. Wybór podyktowany:
- Używany w pracy, chęć rozwoju w tym kierunku
- Wiele gotowych rozwiązań przyspieszających development
- Świetna dokumentacja i aktywna społeczność

### Baza danych: PostgreSQL

Domyślna baza dla Symfony, niezawodna i popularna:
- Wsparcie dla UUID jako primary key
- Doskonała wydajność
- Dojrzałe narzędzia do backupu i replikacji

### ORM: Doctrine

Domyślny ORM dla Symfony:
- Mapowanie obiektowo-relacyjne
- Migracje schematu bazy
- Query Builder i DQL
- Repository pattern

### Autentykacja: Session Cookies

Symfony Security z session cookies:
- Prostsze niż JWT (brak refresh tokenów)
- httpOnly cookies chronią przed XSS
- Wbudowane w Symfony Security
- Lepsze wsparcie dla SSR w Next.js

---

## Struktura katalogów

```
backend/
├── src/
│   ├── Controller/      # API Controllers
│   ├── Entity/          # Doctrine entities
│   ├── Repository/      # Data access
│   ├── Service/         # Business logic
│   └── Security/        # Auth
├── config/              # Konfiguracja Symfony
├── migrations/          # Migracje Doctrine
├── tests/               # PHPUnit tests
└── Dockerfile
```

---

## Wymagania

- PHP 8.4+
- Composer
- PostgreSQL 15+
- Docker

---

## Powiązane dokumenty

- [Model danych](02-data-model.md)
- [API Design](03-api-design.md)
- [Architektura](04-architecture.md)
