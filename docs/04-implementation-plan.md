# 04 - Plan Implementacji

## Struktura repozytoriów (Multirepo)

```
tripplanner-frontend/     # github.com/org/tripplanner-frontend
├── docs/                 # Dokumentacja frontendu
├── app/                  # Next.js App Router
├── components/           # React components
└── Dockerfile

tripplanner-backend/      # github.com/org/tripplanner-backend
├── docs/                 # Dokumentacja backendu
├── src/                  # Symfony source
├── config/               # Konfiguracja
└── Dockerfile

tripplanner-docker/       # github.com/org/tripplanner-docker
├── docs/                 # Dokumentacja infrastruktury
├── docker-compose.yml    # Produkcja
├── docker-compose.dev.yml# Development
└── scripts/              # Deploy, backup, restore

tripplanner-docs/         # github.com/org/tripplanner-docs (to repo)
└── docs/                 # Ogólna dokumentacja projektu
```

---

## Fazy projektu

### Faza 0: Przygotowanie (AKTUALNIE)

- [x] Uzupełnienie dokumentacji projektowej
- [x] Wybór tech stacku
- [x] Reorganizacja na strukturę multirepo
- [ ] Setup środowiska developerskiego
- [ ] Inicjalizacja repozytoriów
- [ ] Konfiguracja Docker

### Faza 1: MVP

**Cel:** Podstawowa funkcjonalność pozwalająca na testowanie z grupą

- [ ] Autentykacja (logowanie, rejestracja przez zaproszenie)
- [ ] CRUD wycieczek
- [ ] Dodawanie uczestników
- [ ] Dodawanie wydatków (podział równy i niestandardowy)
- [ ] Podgląd salda
- [ ] Lista przelewów do wykonania

### Faza 2: Rozbudowa (po MVP)

- [ ] Optymalizacja przelewów
- [ ] Archiwizacja wycieczek
- [ ] Profil użytkownika (edycja danych)
- [ ] Email z przypomnieniem o rozliczeniu

### Faza 3: Polish

- [ ] Testy E2E
- [ ] Optymalizacja wydajności
- [ ] PWA features (offline, instalacja)

---

## Milestones

### Milestone 1: "Hello World"

**Zakres:**
- [ ] Docker Compose z Next.js + Symfony + PostgreSQL
- [ ] Podstawowa komunikacja frontend <-> backend
- [ ] Deploy na serwer (staging)

**Definition of Done:**
- Można odwiedzić stronę i zobaczyć dane pobrane z API

---

### Milestone 2: "Autentykacja"

**Zakres:**
- [ ] Encja User w Doctrine
- [ ] Symfony Security (session auth)
- [ ] Endpoint logowania
- [ ] Rejestracja przez zaproszenie (link mailowy)
- [ ] Ochrona API (tylko zalogowani)
- [ ] Frontend: ekran logowania, context użytkownika

**Definition of Done:**
- Admin może zaprosić użytkownika mailem
- Użytkownik może się zarejestrować przez link i zalogować

---

### Milestone 3: "Pierwsza wycieczka"

**Zakres:**
- [ ] Encje Trip, TripMember
- [ ] CRUD wycieczek (API)
- [ ] Lista wycieczek usera (frontend)
- [ ] Tworzenie/edycja wycieczki (frontend)
- [ ] Zapraszanie uczestników do wycieczki

**Definition of Done:**
- User może stworzyć wycieczkę i dodać do niej znajomych

---

### Milestone 4: "Pierwszy wydatek"

**Zakres:**
- [ ] Encje Expense, ExpenseSplit
- [ ] Dodawanie wydatku (API + frontend)
- [ ] Podział równy na wszystkich
- [ ] Podział na wybrane osoby
- [ ] Lista wydatków wycieczki

**Definition of Done:**
- User może dodać wydatek i zobaczyć go na liście

---

### Milestone 5: "Rozliczenie"

**Zakres:**
- [ ] Algorytm wyliczania salda
- [ ] Encja Settlement
- [ ] Wyświetlanie kto komu ile
- [ ] Oznaczanie przelewu jako wykonany

**Definition of Done:**
- Po dodaniu wydatków, system pokazuje przelewy do wykonania

---

### Milestone 6: "MVP Complete"

**Zakres:**
- [ ] Wszystkie flow działają end-to-end
- [ ] Podstawowa obsługa błędów
- [ ] Testy manualne
- [ ] Deploy produkcyjny

**Definition of Done:**
- Można przeprowadzić pełny scenariusz: stworzyć wycieczkę, dodać osoby, dodać wydatki, zobaczyć rozliczenie

---

## Kolejność implementacji

```
1. Setup projektu (Docker, repozytoria)
   └── 2. Baza danych i encje (Doctrine)
       └── 3. Autentykacja (Symfony Security)
           └── 4. CRUD Wycieczek
               └── 5. Zarządzanie uczestnikami
                   └── 6. CRUD Wydatków
                       └── 7. Algorytm rozliczeń
                           └── 8. UI/UX polish
                               └── 9. Testy
                                   └── 10. Deploy produkcyjny
```

---

## Testy

### Backend (PHPUnit)
- [ ] Algorytm wyliczania salda
- [ ] Algorytm optymalizacji przelewów
- [ ] Walidacja danych wejściowych
- [ ] API endpoints

### Frontend (Jest/Vitest)
- [ ] Komponenty UI
- [ ] Hooks

### E2E (opcjonalnie)
- [ ] Rejestracja -> Logowanie -> Tworzenie wycieczki
- [ ] Pełny flow dodawania wydatku

---

## Następny krok

**Gotowy do startu!** Dokumentacja jest kompletna.

Następne akcje:
1. Utworzenie repozytoriów Git (frontend, backend, docker)
2. Setup Docker Compose (tripplanner-docker)
3. Inicjalizacja projektów (Next.js, Symfony)
4. Konfiguracja GitLab CI
5. Rozpoczęcie Milestone 1

---

## Powiązane dokumenty

- [Wizja projektu](01-vision.md)
- [Lista funkcjonalności](02-features.md)
- [User Stories](03-user-stories.md)

### Dokumentacja per repozytorium

**Backend:**
- [Tech Stack](../backend/docs/01-tech-stack.md)
- [Model danych](../backend/docs/02-data-model.md)
- [API Design](../backend/docs/03-api-design.md)
- [Architektura](../backend/docs/04-architecture.md)

**Frontend:**
- [Tech Stack](../frontend/docs/01-tech-stack.md)
- [UI/UX Design](../frontend/docs/02-ui-ux.md)
- [Architektura](../frontend/docs/03-architecture.md)

**Docker:**
- [Przegląd infrastruktury](../docker/docs/01-overview.md)
- [Deployment](../docker/docs/02-deployment.md)
