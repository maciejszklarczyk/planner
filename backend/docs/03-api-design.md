# Backend - API Design

## Styl API

**REST** - klasyczne endpointy HTTP

---

## Autentykacja

**Metoda:** Session cookies (Symfony Security)

**Flow:**
```
1. User wysyła POST /auth/login z email + password
2. Symfony weryfikuje dane i tworzy sesję
3. Serwer zwraca cookie z session ID (httpOnly, secure, sameSite)
4. Każde kolejne żądanie zawiera cookie automatycznie
5. Symfony weryfikuje sesję i autoryzuje żądanie
```

**Dlaczego session cookies?**
- Prostsze niż JWT (nie trzeba zarządzać refresh tokenami)
- Symfony Security ma to wbudowane
- Bezpieczniejsze (httpOnly chroni przed XSS)
- Lepsze dla SSR w Next.js

---

## Endpointy REST

### Autentykacja

| Metoda | Endpoint | Opis | Body/Params |
|--------|----------|------|-------------|
| POST | /auth/register | Rejestracja | email, password, name |
| POST | /auth/login | Logowanie | email, password |
| POST | /auth/logout | Wylogowanie | - |
| GET | /auth/me | Dane zalogowanego | - |
| POST | /auth/invite | Wyślij zaproszenie do aplikacji | email |
| POST | /auth/register/:token | Rejestracja przez zaproszenie | password, name |

---

### Wycieczki (Trips)

| Metoda | Endpoint | Opis | Body/Params |
|--------|----------|------|-------------|
| GET | /trips | Lista wycieczek usera | ?status=active |
| POST | /trips | Utwórz wycieczkę | name, currency, ... |
| GET | /trips/:id | Szczegóły wycieczki | - |
| PATCH | /trips/:id | Edytuj wycieczkę | ... |
| DELETE | /trips/:id | Usuń/archiwizuj | - |

---

### Uczestnicy (Members)

| Metoda | Endpoint | Opis | Body/Params |
|--------|----------|------|-------------|
| GET | /trips/:id/members | Lista uczestników | - |
| POST | /trips/:id/members | Dodaj uczestnika | user_id lub email |
| DELETE | /trips/:id/members/:userId | Usuń uczestnika | - |
| POST | /trips/:id/invite | Generuj link zaproszenia | - |
| POST | /trips/join/:code | Dołącz przez kod | - |

---

### Wydatki (Expenses)

| Metoda | Endpoint | Opis | Body/Params |
|--------|----------|------|-------------|
| GET | /trips/:id/expenses | Lista wydatków | ?category=... |
| POST | /trips/:id/expenses | Dodaj wydatek | amount, description, splits |
| GET | /trips/:id/expenses/:expenseId | Szczegóły wydatku | - |
| PATCH | /trips/:id/expenses/:expenseId | Edytuj wydatek | ... |
| DELETE | /trips/:id/expenses/:expenseId | Usuń wydatek | - |

---

### Rozliczenia (Settlements)

| Metoda | Endpoint | Opis | Body/Params |
|--------|----------|------|-------------|
| GET | /trips/:id/balance | Salda wszystkich | - |
| GET | /trips/:id/settlements | Sugerowane przelewy | - |
| POST | /trips/:id/settlements | Oznacz jako rozliczone | from, to, amount |

---

## Przykładowe Response'y

### GET /trips/:id

```json
{
  "id": "uuid",
  "name": "Wyjazd w Tatry 2024",
  "description": "...",
  "startDate": "2024-07-15",
  "endDate": "2024-07-20",
  "defaultCurrency": "PLN",
  "status": "active",
  "members": [
    {
      "id": "uuid",
      "name": "Jan Kowalski",
      "role": "admin"
    }
  ],
  "summary": {
    "totalExpenses": 2500.00,
    "myBalance": -125.50
  }
}
```

### GET /trips/:id/balance

```json
{
  "balances": [
    {
      "userId": "uuid",
      "name": "Jan",
      "balance": 250.00
    },
    {
      "userId": "uuid",
      "name": "Anna",
      "balance": -125.00
    }
  ]
}
```

### GET /trips/:id/settlements

```json
{
  "settlements": [
    {
      "from": { "id": "uuid", "name": "Anna" },
      "to": { "id": "uuid", "name": "Jan" },
      "amount": 125.00,
      "currency": "PLN"
    }
  ]
}
```

---

## Obsługa błędów

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Kwota musi być większa od 0",
    "field": "amount"
  }
}
```

### Kody błędów

| Kod | HTTP Status | Opis |
|-----|-------------|------|
| UNAUTHORIZED | 401 | Brak autoryzacji |
| FORBIDDEN | 403 | Brak uprawnień |
| NOT_FOUND | 404 | Nie znaleziono |
| VALIDATION_ERROR | 400 | Błąd walidacji |

---

## Paginacja

```json
{
  "data": [...],
  "pagination": {
    "page": 1,
    "perPage": 20,
    "total": 45,
    "totalPages": 3
  }
}
```

---

## Powiązane dokumenty

- [Tech Stack](01-tech-stack.md)
- [Model danych](02-data-model.md)
- [Architektura](04-architecture.md)
