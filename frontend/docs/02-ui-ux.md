# Frontend - UI/UX Design

## Nawigacja aplikacji

```
┌─────────────────────────────────────────┐
│              App Structure              │
├─────────────────────────────────────────┤
│                                         │
│  ┌─────────┐    ┌─────────────────┐    │
│  │ Login   │───►│ Lista wycieczek │    │
│  └─────────┘    └────────┬────────┘    │
│                          │              │
│                          ▼              │
│                 ┌─────────────────┐     │
│                 │ Szczegóły       │     │
│                 │ wycieczki       │     │
│                 └────────┬────────┘     │
│                          │              │
│        ┌─────────────────┼──────────┐   │
│        ▼                 ▼          ▼   │
│  ┌──────────┐    ┌──────────┐ ┌──────┐ │
│  │ Wydatki  │    │ Saldo    │ │ ...  │ │
│  └──────────┘    └──────────┘ └──────┘ │
│                                         │
└─────────────────────────────────────────┘
```

---

## Lista ekranów (MVP)

### Autentykacja
- Ekran logowania
- Rejestracja przez zaproszenie
- Profil użytkownika (podstawowy)

### Wycieczki
- Lista wycieczek
- Tworzenie wycieczki
- Szczegóły wycieczki
- Edycja wycieczki
- Zapraszanie uczestników

### Wydatki
- Lista wydatków
- Dodawanie wydatku
- Szczegóły wydatku
- Edycja wydatku

### Rozliczenia
- Podsumowanie salda
- Lista przelewów do wykonania
- Historia rozliczeń

---

## Wireframes

### Ekran: Lista wycieczek

```
┌─────────────────────────────────────┐
│  TripPlanner            [Avatar]    │
├─────────────────────────────────────┤
│                                     │
│  Twoje wycieczki                    │
│                                     │
│  ┌─────────────────────────────┐   │
│  │ Tatry 2024                  │   │
│  │ 15-20 lip • 5 osób         │   │
│  │ Twoje saldo: -125 PLN      │   │
│  └─────────────────────────────┘   │
│                                     │
│  ┌─────────────────────────────┐   │
│  │ Chorwacja 2024              │   │
│  │ 10-17 sie • 8 osób         │   │
│  │ Twoje saldo: +50 PLN       │   │
│  └─────────────────────────────┘   │
│                                     │
│              [+ Nowa wycieczka]     │
│                                     │
└─────────────────────────────────────┘
```

### Ekran: Szczegóły wycieczki

```
┌─────────────────────────────────────┐
│  <- Tatry 2024              [...]   │
├─────────────────────────────────────┤
│                                     │
│  [Wydatki] [Saldo] [Uczestnicy]    │
│  ---------                          │
│                                     │
│  Dzisiaj                            │
│  ┌─────────────────────────────┐   │
│  │ Pizza na kolacje            │   │
│  │ Jan zaplacil • 120 PLN      │   │
│  └─────────────────────────────┘   │
│                                     │
│  Wczoraj                            │
│  ┌─────────────────────────────┐   │
│  │ Paliwo                       │   │
│  │ Anna zaplacila • 250 PLN    │   │
│  └─────────────────────────────┘   │
│                                     │
│              [+ Dodaj wydatek]      │
│                                     │
└─────────────────────────────────────┘
```

### Ekran: Dodawanie wydatku

```
┌─────────────────────────────────────┐
│  <- Nowy wydatek           [Zapisz] │
├─────────────────────────────────────┤
│                                     │
│  Kwota                              │
│  ┌─────────────────────────────┐   │
│  │ 0.00                   PLN v│   │
│  └─────────────────────────────┘   │
│                                     │
│  Opis                               │
│  ┌─────────────────────────────┐   │
│  │ np. Obiad w restauracji     │   │
│  └─────────────────────────────┘   │
│                                     │
│  Kategoria                          │
│  [Jedzenie] [Transport] [Nocleg]   │
│  [Rozrywka] [Inne]                 │
│                                     │
│  Zaplacone przez                    │
│  ┌─────────────────────────────┐   │
│  │ Ja (domyslnie)          v  │   │
│  └─────────────────────────────┘   │
│                                     │
│  Podziel miedzy                     │
│  o Wszystkich rowno                 │
│  o Wybrane osoby                    │
│  o Niestandardowo                   │
│                                     │
└─────────────────────────────────────┘
```

### Ekran: Saldo i rozliczenia

```
┌─────────────────────────────────────┐
│  <- Tatry 2024              [...]   │
├─────────────────────────────────────┤
│                                     │
│  [Wydatki] [Saldo] [Uczestnicy]    │
│            -------                  │
│                                     │
│  Calkowite wydatki: 2,500 PLN      │
│                                     │
│  Twoje saldo                        │
│  ┌─────────────────────────────┐   │
│  │      -125.00 PLN            │   │
│  │   Jestes winien grupie      │   │
│  └─────────────────────────────┘   │
│                                     │
│  Do zaplaty:                        │
│  ┌─────────────────────────────┐   │
│  │ -> Jan                       │   │
│  │   125.00 PLN       [Zaplac] │   │
│  └─────────────────────────────┘   │
│                                     │
│  Wszystkie salda:                   │
│  Jan      +250 PLN                  │
│  Anna     +100 PLN                  │
│  Ty       -125 PLN                  │
│  Tomek    -225 PLN                  │
│                                     │
└─────────────────────────────────────┘
```

---

## Komponenty UI (shadcn/ui)

Wykorzystamy gotowe komponenty z shadcn/ui:
- Button (primary, secondary, ghost)
- Input (tekstowy, numeryczny)
- Select / Dropdown
- Checkbox / Radio
- Card (trip card, expense card)
- Dialog (modal)
- Toast
- Avatar
- Badge (status, kategoria)
- Skeleton (loading)
- Alert (error state)

---

## UX - Flow dodawania wydatku

1. User klika "+" na ekranie wycieczki
2. Uzupelnia ile zaplacil
3. Uzupelnia za kogo zaplacil
4. Wybiera kategorie
5. Wydatek zapisany, toast "Dodano wydatek"

---

## Decyzje UX

| Pytanie | Decyzja |
|---------|---------|
| Salda w liscie wycieczek? | Tak |
| Domyslnie dzielic na wszystkich? | Tak |
| Osoby bez konta? | Nie obslugujemy |
| Powiadomienia push? | Nie (na razie) |

---

## Powiazane dokumenty

- [Tech Stack](01-tech-stack.md)
- [Architektura](03-architecture.md)
