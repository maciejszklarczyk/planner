# Backend - Model Danych

## Diagram ERD (Entity Relationship)

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│    User     │       │    Trip     │       │   Expense   │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id          │       │ id          │       │ id          │
│ email       │◄─────►│ name        │◄─────►│ amount      │
│ name        │       │ created_by  │       │ description │
│ ...        │       │ ...         │       │ paid_by     │
└─────────────┘       └─────────────┘       └─────────────┘
        │                   │                     │
        │                   │                     │
        ▼                   ▼                     ▼
┌─────────────────────────────────────────────────────────┐
│                    TripMember                            │
│              (relacja user <-> trip)                     │
└─────────────────────────────────────────────────────────┘
```

---

## Encje Doctrine

### User (Użytkownik)

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| id | UUID | Tak | Unikalny identyfikator |
| email | String | Tak | Email (login) |
| name | String | Tak | Imię wyświetlane |
| avatar_url | String | Nie | URL do avatara |
| created_at | DateTime | Tak | Data utworzenia |
| deleted_at | DateTime | Nie | Soft delete |

---

### Trip (Wycieczka)

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| id | UUID | Tak | Unikalny identyfikator |
| name | String | Tak | Nazwa wycieczki |
| description | String | Nie | Opis |
| start_date | Date | Nie | Data rozpoczęcia |
| end_date | Date | Nie | Data zakończenia |
| created_by | UUID (FK) | Tak | Twórca wycieczki |
| default_currency | String | Tak | Domyślna waluta |
| status | Enum | Tak | active/archived |
| invite_code | String | Nie | Kod zaproszenia |
| created_at | DateTime | Tak | Data utworzenia |
| deleted_at | DateTime | Nie | Soft delete |

---

### TripMember (Uczestnik wycieczki)

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| id | UUID | Tak | |
| trip_id | UUID (FK) | Tak | |
| user_id | UUID (FK) | Tak | |
| role | Enum | Tak | admin/member |
| status | Enum | Tak | pending/confirmed |
| joined_at | DateTime | Tak | |

---

### Expense (Wydatek)

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| id | UUID | Tak | |
| trip_id | UUID (FK) | Tak | |
| paid_by | UUID (FK) | Tak | Kto zapłacił |
| amount | Decimal | Tak | Kwota |
| currency | String | Tak | Waluta |
| description | String | Tak | Opis wydatku |
| category | String | Nie | Kategoria |
| date | Date | Tak | Data wydatku |
| split_type | Enum | Tak | equal/custom |
| created_at | DateTime | Tak | |
| deleted_at | DateTime | Nie | Soft delete |

---

### ExpenseSplit (Podział wydatku)

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| id | UUID | Tak | |
| expense_id | UUID (FK) | Tak | |
| user_id | UUID (FK) | Tak | Kto jest winien |
| amount | Decimal | Tak | Kwota do zapłaty |
| percentage | Decimal | Nie | % udziału |

---

### Settlement (Rozliczenie)

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| id | UUID | Tak | |
| trip_id | UUID (FK) | Tak | |
| from_user | UUID (FK) | Tak | Kto płaci |
| to_user | UUID (FK) | Tak | Komu |
| amount | Decimal | Tak | Kwota |
| settled_at | DateTime | Nie | Kiedy rozliczono |
| status | Enum | Tak | pending/completed |

---

## Kategorie wydatków

- Nocleg
- Transport
- Jedzenie
- Rozrywka
- Zakupy
- Bilety
- Inne

---

## Waluty

- PLN (jedyna wspierana waluta na start)

Każda wycieczka ma jedną walutę, bez przelicznika.

---

## Podjęte decyzje

| Pytanie | Decyzja |
|---------|---------|
| Użytkownik w wielu wycieczkach? | Tak |
| Wydatek dla osoby spoza wycieczki? | Nie |
| Historia edycji wydatków? | Nie |
| Wydatek w innej walucie? | Nie |
| Soft delete czy hard delete? | Soft delete |

---

## Powiązane dokumenty

- [Tech Stack](01-tech-stack.md)
- [API Design](03-api-design.md)
- [Architektura](04-architecture.md)
