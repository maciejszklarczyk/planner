# Backend - Architektura

## Diagram architektury

```
┌─────────────────────────────────────────────────────────┐
│                      Routes / Controllers               │
│                           │                             │
│                     ┌─────┴─────┐                       │
│                     │ Services  │   Business logic      │
│                     └─────┬─────┘                       │
│                           │                             │
│                     ┌─────┴─────┐                       │
│                     │Repository │   Data access         │
│                     └─────┬─────┘                       │
│                           │                             │
│                     ┌─────┴─────┐                       │
│                     │ Database  │                       │
│                     └───────────┘                       │
└─────────────────────────────────────────────────────────┘
```

---

## Warstwy aplikacji

### Controller Layer
- Obsługa żądań HTTP
- Walidacja danych wejściowych
- Serializacja odpowiedzi
- Zarządzanie sesją

### Service Layer
- Logika biznesowa
- Algorytm wyliczania salda
- Algorytm optymalizacji przelewów
- Generowanie kodów zaproszenia

### Repository Layer
- Dostęp do danych przez Doctrine
- Zapytania do bazy danych
- Cache queries

### Entity Layer
- Definicje encji Doctrine
- Relacje między encjami
- Walidacja na poziomie modelu

---

## Struktura katalogów

```
backend/
├── src/
│   ├── Controller/
│   │   ├── AuthController.php
│   │   ├── TripController.php
│   │   ├── ExpenseController.php
│   │   └── SettlementController.php
│   │
│   ├── Entity/
│   │   ├── User.php
│   │   ├── Trip.php
│   │   ├── TripMember.php
│   │   ├── Expense.php
│   │   ├── ExpenseSplit.php
│   │   └── Settlement.php
│   │
│   ├── Repository/
│   │   ├── UserRepository.php
│   │   ├── TripRepository.php
│   │   ├── ExpenseRepository.php
│   │   └── SettlementRepository.php
│   │
│   ├── Service/
│   │   ├── AuthService.php
│   │   ├── TripService.php
│   │   ├── ExpenseService.php
│   │   ├── BalanceCalculator.php
│   │   └── SettlementOptimizer.php
│   │
│   └── Security/
│       ├── UserProvider.php
│       └── LoginFormAuthenticator.php
│
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── security.yaml
│   │   └── framework.yaml
│   └── routes.yaml
│
├── migrations/
└── tests/
```

---

## Autentykacja - flow

```
┌────────┐     ┌────────┐     ┌────────┐     ┌────────┐
│ User   │     │Frontend│     │Backend │     │   DB   │
└───┬────┘     └───┬────┘     └───┬────┘     └───┬────┘
    │              │              │              │
    │  1. Login    │              │              │
    │─────────────►│              │              │
    │              │  2. POST     │              │
    │              │─────────────►│              │
    │              │              │  3. Verify   │
    │              │              │─────────────►│
    │              │              │◄─────────────│
    │              │  4. Cookie   │              │
    │              │◄─────────────│              │
    │  5. Redirect │              │              │
    │◄─────────────│              │              │
    │              │              │              │
```

---

## Bezpieczeństwo

| Zabezpieczenie | Implementacja |
|----------------|---------------|
| HTTPS everywhere | Nginx reverse proxy |
| Session cookies | httpOnly, secure, sameSite=strict |
| Rate limiting | Symfony RateLimiter |
| Input validation | Symfony Validator |
| SQL injection | Doctrine ORM (prepared statements) |
| XSS protection | Automatic escaping |
| CORS | NelmioCorsBundle |
| CSRF | Symfony CSRF tokens |
| Password hashing | bcrypt (password_hash) |

---

## CI/CD Pipeline

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  Push    │───►│  Tests   │───►│  Build   │───►│  Deploy  │
│  to main │    │ (PHPUnit)│    │ (Docker) │    │ (Docker) │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
```

**Narzędzia:**
- GitLab CI
- PHPUnit
- PHP CS Fixer
- PHPStan

---

## Testy

### Testy jednostkowe (PHPUnit)
- Algorytm wyliczania salda
- Algorytm optymalizacji przelewów
- Walidacja danych wejściowych

### Testy integracyjne
- API endpoints
- Autentykacja flow

---

## Powiązane dokumenty

- [Tech Stack](01-tech-stack.md)
- [Model danych](02-data-model.md)
- [API Design](03-api-design.md)
