# Plan Implementacji: Zarządzanie Użytkownikami w Grupach

## Status: 🟢 W IMPLEMENTACJI (MVP częściowo gotowe)

### 📊 Postęp: ~60% MVP

**✅ Zrealizowane:**
- UserController z GET /admin/users (paginacja, wyszukiwanie, filtry)
- GroupMembershipController z POST /admin/groups/{groupId}/users
- GroupMembershipService z addUserToGroup()
- DTO: AddUserToGroupDto, UpdateUserRoleDto, UserListItemDto, GroupMembershipDto
- Walidacje biznesowe (duplikaty, istnienie encji)
- Autoryzacja ROLE_ADMIN
- JSON error handlers
- Pliki HTTP requests (users.http, group-membership.http)
- 10 testów funkcjonalnych dla AuthController
- PHPUnit configuration
- GitLab CI/CD stage dla PHPUnit

**🔄 W toku / TODO:**
- GET /admin/groups/{groupId}/users - lista członków grupy
- DELETE /admin/groups/{groupId}/users/{userId} - usuwanie użytkownika
- PATCH /admin/groups/{groupId}/users/{userId}/role - zmiana roli
- Walidacja ostatniego OWNER (nie można usunąć/zdegradować)
- Testy funkcjonalne dla GroupMembershipController
- Dokumentacja OpenAPI/Swagger

**📝 Ostatnie commity:**
- `869ede8` - Add functional tests and PHPUnit configuration
- `132484d` - Implement group membership and user management features

---

## 1. Przegląd Istniejącej Struktury

### Encje
- **UserHasGroup**: Relacja many-to-many z dodatkowymi polami
  - `user`: User (nullable: false)
  - `group`: Group (nullable: false)
  - `role`: UserGroupRoleEnum (OWNER | MEMBER, domyślnie: MEMBER)
  - `addedBy`: User (nullable: true) - kto dodał użytkownika

### Role w grupach
- `OWNER` - właściciel grupy
- `MEMBER` - zwykły członek

---

## 2. Decyzje Projektowe

### 2.1 Strategia API

### ✅ WYBÓR: Pojedyncze Operacje

**Uzasadnienie:**
Administrator będzie zarządzał członkostwem w grupach przez interfejs webowy, gdzie dodaje pojedynczego użytkownika na raz wybierając go z listy dropdown. Operacje hurtowe nie są potrzebne w obecnym zakresie funkcjonalności.

**Endpointy:**
```
POST   /admin/groups/{groupId}/users
DELETE /admin/groups/{groupId}/users/{userId}
PATCH  /admin/groups/{groupId}/users/{userId}/role
GET    /admin/groups/{groupId}/users
GET    /admin/users
```

---

### 2.2 Perspektywa API

### ✅ WYBÓR: Group-centric (przez grupę)

**Uzasadnienie:**
Administrator będzie zarządzał użytkownikami z poziomu widoku grupy. W panelu administracyjnym będzie zakładka "Grupy", po wejściu w konkretną grupę będzie mógł dodawać/usuwać użytkowników oraz zmieniać ich role.

**Struktura URL:**
```
POST   /admin/groups/{groupId}/users
DELETE /admin/groups/{groupId}/users/{userId}
PATCH  /admin/groups/{groupId}/users/{userId}/role
GET    /admin/groups/{groupId}/users
```

---

## 2.3 Widok UI/UX

### Przepływ użytkownika (Admin):
1. **Panel Admin** → Zakładka **"Grupy"**
2. **Lista Grup** → Przycisk "Szczegóły" przy grupie
3. **Widok Grupy**:
   - Nagłówek z nazwą grupy
   - Sekcja "Dodaj użytkownika":
     - **Dropdown 1**: Wybór użytkownika z listy (wszystkich użytkowników systemu)
     - **Dropdown 2**: Wybór roli (OWNER / MEMBER)
     - **Przycisk**: "Dodaj do grupy"
   - Tabela z obecnymi członkami grupy:
     - Kolumny: Użytkownik | Email | Rola | Dodany przez | Data | Akcje
     - Akcje: Zmień rolę | Usuń z grupy

### Wymagane endpointy dla UI:

**UserController** (zarządzanie użytkownikami):
- `GET /admin/users` - lista wszystkich użytkowników (dla dropdown i innych celów)

**GroupMembershipController** (zarządzanie członkostwem w grupach):
- `GET /admin/groups/{groupId}/users` - członkowie grupy
- `POST /admin/groups/{groupId}/users` - dodaj użytkownika
- `PATCH /admin/groups/{groupId}/users/{userId}/role` - zmień rolę
- `DELETE /admin/groups/{groupId}/users/{userId}` - usuń z grupy

---

## 3. Projekt Endpointów

### 3.1 Dodawanie Użytkowników do Grupy

**Endpoint:**
```
POST /admin/groups/{groupId}/users
```

**Request Body:**
```json
{
  "userId": 123,
  "role": "MEMBER"  // opcjonalne, domyślnie MEMBER
                    // akceptuje: "MEMBER"/"member", "OWNER"/"owner" (case-insensitive)
}
```

**Response (201):**
```json
{
  "id": 456,
  "user": {
    "id": 123,
    "email": "user@example.com",
    "firstName": "Jan",
    "lastName": "Kowalski"
  },
  "group": {
    "id": 1,
    "name": "Developers"
  },
  "role": "MEMBER",
  "addedBy": {
    "id": 1,
    "email": "admin@example.com"
  },
  "createdAt": "2026-01-27T10:00:00Z"
}
```

**Response (400):**
```json
{
  "error": "USER_ALREADY_IN_GROUP",
  "message": "User is already a member of this group"
}
```

---

### 3.2 Usuwanie Użytkownika z Grupy

**Endpoint:**
```
DELETE /admin/groups/{groupId}/users/{userId}
```

**Response (204):** No Content

**Response (404):**
```json
{
  "error": "MEMBERSHIP_NOT_FOUND",
  "message": "User is not a member of this group"
}
```

---

### 3.3 Zmiana Roli Użytkownika w Grupie

**Endpoint:**
```
PATCH /admin/groups/{groupId}/users/{userId}/role
```

**Request Body:**
```json
{
  "role": "OWNER"  // akceptuje: "OWNER"/"owner", "MEMBER"/"member" (case-insensitive)
}
```

**Response (200):**
```json
{
  "id": 456,
  "role": "OWNER",
  "updatedAt": "2026-01-27T10:00:00Z"
}
```

---

### 3.4 Lista Użytkowników w Grupie

**Endpoint:**
```
GET /admin/groups/{groupId}/users
```

**Query Parameters:**
- `role`: filtruj po roli (OWNER | MEMBER)
- `page`: numer strony (domyślnie 1)
- `limit`: liczba wyników (domyślnie 20)

**Response (200):**
```json
{
  "data": [
    {
      "id": 456,
      "user": {
        "id": 123,
        "email": "user@example.com",
        "firstName": "Jan",
        "lastName": "Kowalski"
      },
      "role": "MEMBER",
      "addedBy": {
        "id": 1,
        "email": "admin@example.com"
      },
      "createdAt": "2026-01-27T10:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "pages": 8
  }
}
```

---

### 3.5 Lista Wszystkich Użytkowników (dla Dropdown)

**Endpoint:**
```
GET /admin/users
```

**Query Parameters:**
- `search`: wyszukiwanie po email/nazwisku (opcjonalne)
- `excludeGroupId`: wykluczenie użytkowników już będących w grupie (opcjonalne)
- `page`: numer strony (domyślnie 1)
- `limit`: liczba wyników (domyślnie 50)

**Response (200):**
```json
{
  "data": [
    {
      "id": 123,
      "email": "jan.kowalski@example.com",
      "firstName": "Jan",
      "lastName": "Kowalski",
      "fullName": "Jan Kowalski"
    },
    {
      "id": 456,
      "email": "anna.nowak@example.com",
      "firstName": "Anna",
      "lastName": "Nowak",
      "fullName": "Anna Nowak"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 50,
    "total": 234,
    "pages": 5
  }
}
```

**Uwagi:**
- Parametr `excludeGroupId` pozwala automatycznie filtrować użytkowników już będących w grupie
- Endpoint używany przez dropdown do wyboru użytkownika
- Wyszukiwanie pomocne przy dużej liczbie użytkowników

---

## 4. Autoryzacja i Bezpieczeństwo

### 4.1 Wymagania
- ✅ Tylko użytkownicy z rolą `ADMIN` mogą zarządzać członkostwem w grupach
- ✅ Walidacja istnienia użytkownika i grupy
- ✅ Śledzenie kto dodał użytkownika (pole `addedBy`)

### 4.2 Implementacja
```php
#[IsGranted('ROLE_ADMIN')]
class GroupMembershipController extends AbstractController
{
    // ...
}
```

### 4.3 Walidacje Biznesowe
- [ ] Użytkownik nie może być dodany dwa razy do tej samej grupy
- [ ] Grupa musi mieć przynajmniej jednego OWNER
- [ ] Nie można usunąć ostatniego OWNER z grupy
- [ ] Użytkownik musi istnieć i być aktywny (jeśli ma takie pole)
- [ ] Grupa musi istnieć

---

## 5. Struktura Komponentów

### 5.1 Controllers
```
src/Controller/Admin/UserController.php
  - GET /admin/users - lista użytkowników (dla dropdown i innych celów)

src/Controller/Admin/GroupMembershipController.php
  - GET /admin/groups/{groupId}/users - członkowie grupy
  - POST /admin/groups/{groupId}/users - dodaj użytkownika do grupy
  - PATCH /admin/groups/{groupId}/users/{userId}/role - zmień rolę
  - DELETE /admin/groups/{groupId}/users/{userId} - usuń z grupy
```

### 5.2 Service
```
src/Service/GroupMembershipService.php
```
Odpowiedzialność:
- Logika biznesowa dodawania/usuwania użytkowników
- Walidacje biznesowe
- Zarządzanie transakcjami

### 5.3 DTO (Data Transfer Objects)
```
src/Dto/GroupMembership/AddUserToGroupDto.php
src/Dto/GroupMembership/UpdateUserRoleDto.php
```

### 5.4 Repository
Wykorzystanie istniejącego:
```
src/Repository/UserHasGroupRepository.php
```

### 5.5 Voter (opcjonalnie)
```
src/Security/Voter/GroupMembershipVoter.php
```
Jeśli w przyszłości członkowie grupy (np. OWNER) będą mogli zarządzać członkostwem

---

## 6. Przypadki Użycia

### 6.1 Admin dodaje użytkownika do grupy (UI Flow)
1. Admin otwiera panel administracyjny → zakładka "Grupy"
2. Admin klika "Szczegóły" przy wybranej grupie
3. W sekcji "Dodaj użytkownika":
   - Frontend pobiera listę użytkowników: `GET /admin/users?excludeGroupId={groupId}`
   - Admin wybiera użytkownika z dropdown
   - Admin wybiera rolę z dropdown (OWNER/MEMBER)
   - Admin klika "Dodaj do grupy"
4. Frontend wysyła: `POST /admin/groups/{groupId}/users` z userId i role
5. Backend:
   - Sprawdza czy admin ma uprawnienia (ROLE_ADMIN)
   - Waliduje czy user i group istnieją
   - Sprawdza czy user nie jest już w grupie
   - Tworzy UserHasGroup z addedBy = admin
   - Zwraca 201 z danymi relacji
6. Frontend odświeża tabelę członków grupy
7. Wyświetla komunikat sukcesu: "Użytkownik został dodany do grupy"

### 6.2 Admin usuwa użytkownika z grupy
1. Admin wysyła DELETE request
2. System sprawdza uprawnienia
3. System waliduje czy relacja istnieje
4. System sprawdza czy nie jest to ostatni OWNER
5. System usuwa relację
6. Zwraca 204 No Content

### 6.3 Admin zmienia rolę użytkownika
1. Admin wysyła PATCH request z nową rolą
2. System sprawdza uprawnienia
3. System waliduje czy relacja istnieje
4. Jeśli degradacja OWNER → MEMBER: sprawdza czy są inni OWNER
5. System aktualizuje rolę
6. Zwraca 200 z zaktualizowanymi danymi

---

## 7. Testy

### 7.1 Konfiguracja PHPUnit
- [x] ✅ phpunit.xml.dist - konfiguracja PHPUnit 12.5
- [x] ✅ tests/bootstrap.php - inicjalizacja środowiska testowego
- [x] ✅ DatabaseTestCase - bazowa klasa dla testów z bazą danych
- [x] ✅ GitLab CI/CD - stage `phpunit` uruchamiany na wszystkich branchach oprócz main

### 7.2 Testy Funkcjonalne - Zrealizowane
**AuthControllerTest** (10 testów) - commit: 869ede8
- [x] ✅ testLoginWithValidCredentials
- [x] ✅ testLoginWithInvalidPassword
- [x] ✅ testLoginWithInvalidEmail
- [x] ✅ testLoginWithMissingCredentials
- [x] ✅ testLoginWithMissingEmail
- [x] ✅ testLoginWithMissingPassword
- [x] ✅ testMeEndpointWithoutAuthentication
- [x] ✅ testMeEndpointWithAuthentication
- [x] ✅ testRegularUserLogin
- [x] ✅ testLogout

### 7.3 Testy Funkcjonalne - TODO
**GroupMembershipControllerTest** (do zaimplementowania)
- [ ] 🔄 POST /admin/groups/{groupId}/users - sukces (201)
- [ ] 🔄 POST /admin/groups/{groupId}/users - user już w grupie (400)
- [ ] 🔄 POST /admin/groups/{groupId}/users - nieistniejący user (404)
- [ ] 🔄 POST /admin/groups/{groupId}/users - nieistniejąca grupa (404)
- [ ] 🔄 POST /admin/groups/{groupId}/users - brak autoryzacji (401)
- [ ] 🔄 POST /admin/groups/{groupId}/users - użytkownik bez ROLE_ADMIN (403)
- [ ] 🔄 GET /admin/groups/{groupId}/users - lista członków
- [ ] 🔄 GET /admin/groups/{groupId}/users?role=OWNER - filtrowanie po roli
- [ ] 🔄 DELETE /admin/groups/{groupId}/users/{userId} - sukces (204)
- [ ] 🔄 DELETE - ostatni owner (błąd 400)
- [ ] 🔄 PATCH /admin/groups/{groupId}/users/{userId}/role - zmiana roli

**UserControllerTest** (do zaimplementowania)
- [ ] 🔄 GET /admin/users - lista wszystkich użytkowników
- [ ] 🔄 GET /admin/users?search=jan - wyszukiwanie
- [ ] 🔄 GET /admin/users?excludeGroupId=1 - wykluczenie użytkowników z grupy
- [ ] 🔄 GET /admin/users - paginacja
- [ ] 🔄 GET /admin/users - brak autoryzacji (401)
- [ ] 🔄 GET /admin/users - użytkownik bez ROLE_ADMIN (403)

### 7.4 Testy Jednostkowe/Integracyjne - TODO
**GroupMembershipServiceTest**
- [ ] 🔄 testAddUserToGroup_Success
- [ ] 🔄 testAddUserToGroup_UserNotFound
- [ ] 🔄 testAddUserToGroup_GroupNotFound
- [ ] 🔄 testAddUserToGroup_UserAlreadyInGroup
- [ ] 🔄 testRemoveUserFromGroup_Success
- [ ] 🔄 testRemoveUserFromGroup_LastOwner
- [ ] 🔄 testUpdateUserRole_Success
- [ ] 🔄 testUpdateUserRole_CannotRemoveLastOwner

### 7.5 Testy E2E (Frontend + Backend)
- [ ] 🔄 Pełny flow dodawania użytkownika przez UI
- [ ] 🔄 Pełny flow usuwania użytkownika przez UI
- [ ] 🔄 Pobieranie listy użytkowników do dropdown
- [ ] 🔄 Filtrowanie użytkowników już będących w grupie
- [ ] 🔄 Zmiana roli użytkownika przez UI

---

## 8. Migracje Bazy Danych

### 8.1 Status
- ✅ Tabela `user_has_group` już istnieje
- ✅ Kolumny: id, user_id, group_id, role, added_by_id

### 8.2 Potrzebne zmiany
```
<!-- Lista zmian w strukturze bazy jeśli potrzebne -->
- [ ] Brak - struktura jest gotowa
```

---

## 9. Dokumentacja API

### 9.1 OpenAPI/Swagger
- [ ] Dodać adnotacje do kontrolera
- [ ] Wygenerować dokumentację

### 9.2 Przykładowe Requesty HTTP

Pliki zawierają gotowe requesty do uruchomienia w PhpStorm/IntelliJ z wykorzystaniem zmiennych środowiskowych:

- **`requests/users.http`** - zarządzanie użytkownikami (UserController)
  - GET /admin/users z paginacją i filtrami

- **`requests/group-membership.http`** - zarządzanie członkostwem w grupach (GroupMembershipController)
  - GET /admin/groups/{groupId}/users
  - POST /admin/groups/{groupId}/users
  - PATCH /admin/groups/{groupId}/users/{userId}/role
  - DELETE /admin/groups/{groupId}/users/{userId}

---

## 10. CI/CD i Automatyzacja

### 10.1 GitLab CI/CD Pipeline
- [x] ✅ Stage `phpunit` - uruchamia testy PHPUnit na wszystkich branchach oprócz main
- [x] ✅ Stage `php-cs-fixer` - sprawdzanie standardów kodowania
- [x] ✅ Stage `lint` - walidacja YAML i kontenera Symfony
- [x] ✅ Stage `secret-detection` - wykrywanie sekretów w kodzie
- [x] ✅ Composer cache dla szybszych buildów

### 10.2 Struktura Pipeline (.gitlab-ci.yml)
```yaml
stages:
    - test           # PHPUnit + PHP CS Fixer
    - secret-detection
    - build          # Composer install
    - lint           # Symfony linting
    - docker-build   # Build Docker images (tylko main)
    - deploy         # Deploy produkcja (tylko main, manual)
```

### 10.3 Stage PHPUnit
```yaml
phpunit:
    stage: test
    image: composer:2.9.4
    script:
        - composer install --no-interaction --prefer-dist
        - vendor/bin/phpunit
    cache:
        paths:
            - vendor/
    except:
        - main
```

**Charakterystyka:**
- Uruchamia się równolegle z `php-cs-fixer` (oba w stage `test`)
- Używa PHP 8.3 (z image composer:2.9.4)
- Cache vendor/ dla szybszych kolejnych uruchomień
- Nie uruchamia się na main (produkcja jest już przetestowana przed merge)

### 10.4 Przyszłe Rozszerzenia CI/CD
- [ ] Code coverage report (PHPUnit --coverage-html)
- [ ] Publikacja coverage do GitLab Pages
- [ ] Minimum coverage threshold (np. 80%)
- [ ] Parallel matrix dla różnych wersji PHP (8.2, 8.3, 8.4)
- [ ] Integration tests stage (osobny stage po unit tests)
- [ ] Static analysis (PHPStan, Psalm)
- [ ] Mutation testing (Infection)

---

## 11. Timeline i Priorytety

### Faza 1: MVP (Must Have) - ✅ CZĘŚCIOWO GOTOWE
- [x] ✅ GET /admin/users - lista użytkowników dla dropdown (z paginacją i filtrami)
- [x] ✅ POST /admin/groups/{groupId}/users - dodawanie użytkownika
- [ ] 🔄 GET /admin/groups/{groupId}/users - lista członków grupy (TODO)
- [ ] 🔄 DELETE /admin/groups/{groupId}/users/{userId} - usuwanie użytkownika (TODO)
- [x] ✅ Podstawowe walidacje biznesowe (duplikaty, istnienie encji)
- [x] ✅ Autoryzacja admin-only (#[IsGranted('ROLE_ADMIN')])

### Faza 2: Rozszerzenia (Should Have)
- [ ] 🔄 PATCH /admin/groups/{groupId}/users/{userId}/role - zmiana roli (TODO)
- [ ] 🔄 Zaawansowane walidacje (ostatni owner nie może być usunięty/zdegradowany)
- [x] ✅ Wyszukiwanie i filtrowanie w dropdownie użytkowników (search)
- [x] ✅ Parametr excludeGroupId dla ukrycia użytkowników już w grupie
- [ ] 🔄 Paginacja listy członków grupy (częściowo - backend gotowy, endpoint TODO)
- [x] ✅ Testy funkcjonalne dla autentykacji (AuthControllerTest - 10 testów)
- [ ] 🔄 Testy funkcjonalne dla GroupMembershipController (TODO)

### Faza 3: Opcjonalne (Nice to Have)
- [ ] Eventy (UserAddedToGroupEvent, UserRemovedFromGroupEvent)
- [ ] Logi audytowe zmian członkostwa
- [ ] Notyfikacje email przy dodaniu/usunięciu
- [ ] Eksport listy członków grupy (CSV/Excel)
- [ ] Historia członkostwa (migracja z soft delete)

---

## 12. Pytania i Niejasności

### Do Rozważenia:
1. **Czy grupa może istnieć bez OWNER?**
   - Decyzja: [ NIE ]

2. **Czy user może być OWNER w wielu grupach jednocześnie?**
   - Decyzja: [ TAK ]

3. **Czy potrzebujemy historii członkostwa (kto kiedy był dodany/usunięty)?**
   - Decyzja: [ Na razie nie ]

4. **Czy potrzebujemy powiadomień email przy dodaniu do grupy?**
   - Decyzja: [ Na razie nie ]

5. **Maksymalna liczba członków w grupie?**
   - Decyzja: [ brak ]

---

## 13. Notatki Implementacyjne

### Symfony Components
- Security: `#[IsGranted]` attribute
- Validation: Symfony Validator dla DTO
- Serialization: dla responses (JSON)
- Doctrine: dla operacji na encjach

### Best Practices
- Używaj serwisów dla logiki biznesowej
- DTO dla walidacji input
- Repository pattern dla queries
- Zwracaj odpowiednie kody HTTP
- Loguj ważne operacje
- Transakcje dla atomowości

---

## 14. Checklist Implementacji

### Backend:
- [x] ✅ Podjąć decyzje projektowe (sekcja 2) - DONE (commit: 132484d)
- [x] ✅ Utworzyć DTO (AddUserToGroupDto, UpdateUserRoleDto, UserListItemDto, GroupMembershipDto)
- [x] ✅ Utworzyć szkielet GroupMembershipService z metodą addUserToGroup()
- [x] ✅ Utworzyć UserController dla zarządzania użytkownikami
- [x] ✅ Utworzyć szkielet GroupMembershipController z autoryzacją ROLE_ADMIN
- [x] ✅ Zaimplementować JSON error handlers (401 Unauthorized, 403 Forbidden)
- [x] ✅ Zaimplementować UserRepository z paginacją i filtrami
- [x] ✅ Utworzyć pliki HTTP requests (users.http, group-membership.http)
- [x] ✅ Zaimplementować logikę w GroupMembershipService (addUserToGroup)
- [x] ✅ Dodać walidacje biznesowe w Service (user/group exists, duplicates)
- [x] ✅ Napisać testy funkcjonalne dla AuthController (10 testów) - commit: 869ede8
- [x] ✅ Skonfigurować PHPUnit (phpunit.xml.dist, tests/bootstrap.php) - commit: 869ede8
- [x] ✅ Dodać stage PHPUnit do GitLab CI/CD
- [ ] 🔄 Zaimplementować pozostałe endpointy w GroupMembershipController:
  - [x] ✅ GET /admin/users (lista użytkowników - w UserController)
  - [ ] 🔄 GET /admin/groups/{groupId}/users (członkowie grupy)
  - [x] ✅ POST /admin/groups/{groupId}/users (dodaj)
  - [ ] 🔄 DELETE /admin/groups/{groupId}/users/{userId} (usuń)
  - [ ] 🔄 PATCH /admin/groups/{groupId}/users/{userId}/role (zmień rolę)
- [ ] 🔄 Rozszerzyć GroupMembershipService o:
  - [ ] getGroupMembers() - pobieranie listy członków grupy
  - [ ] removeUserFromGroup() - usuwanie użytkownika z grupy
  - [ ] updateUserRole() - zmiana roli użytkownika
  - [ ] validateLastOwnerNotRemoved() - walidacja ostatniego ownera
- [ ] 🔄 Napisać testy funkcjonalne dla GroupMembershipController
- [ ] 🔄 Dodać dokumentację API (OpenAPI/Swagger)
- [ ] 🔄 Code review

### Frontend (jeśli w zakresie):
- [ ] Utworzyć stronę "Grupy" w panelu admin
- [ ] Utworzyć widok szczegółów grupy
- [ ] Zaimplementować dropdown wyboru użytkownika
- [ ] Zaimplementować dropdown wyboru roli
- [ ] Zaimplementować tabelę członków z akcjami
- [ ] Dodać komunikaty sukcesu/błędów
- [ ] Testy E2E dla całego flow

### Deployment:
- [ ] Deploy backend
- [ ] Deploy frontend
- [ ] Smoke testy na produkcji

---

## 15. Zrealizowane Zmiany (Changelog)

### 2026-01-27 - Faza 1 (Częściowa implementacja MVP)

**Commit: 132484d** - Implement group membership and user management features
- ✅ Zaimplementowano UserController z endpointem GET /admin/users
- ✅ Zaimplementowano GroupMembershipController z POST /admin/groups/{groupId}/users
- ✅ Utworzono GroupMembershipService z metodą addUserToGroup()
- ✅ Utworzono DTO: AddUserToGroupDto, UpdateUserRoleDto, UserListItemDto, GroupMembershipDto
- ✅ Dodano walidacje biznesowe: duplikaty, istnienie użytkownika/grupy
- ✅ Zaimplementowano autoryzację ROLE_ADMIN
- ✅ Utworzono pliki HTTP requests (users.http, group-membership.http)
- ✅ Dodano obsługę błędów: UserAlreadyInGroupException, NotFoundHttpException

**Commit: 869ede8** - Add functional tests and PHPUnit configuration
- ✅ Skonfigurowano PHPUnit (phpunit.xml.dist)
- ✅ Utworzono tests/bootstrap.php
- ✅ Napisano 10 testów funkcjonalnych dla AuthController
- ✅ Dodano DatabaseTestCase jako bazę dla testów z bazą danych

**2026-01-27** - GitLab CI/CD Enhancement
- ✅ Dodano stage `phpunit` do GitLab CI/CD
- ✅ Testy uruchamiane na wszystkich branchach oprócz main

---

## 16. Dalszy Rozwój (Roadmap)

### Priorytet 1: Dokończenie MVP (najbliższe zadania)
1. **GET /admin/groups/{groupId}/users** - Lista członków grupy
   - Zaimplementować metodę getGroupMembers() w Service
   - Dodać endpoint w Controller
   - Obsługa paginacji i filtrowania po roli
   - Testy funkcjonalne

2. **DELETE /admin/groups/{groupId}/users/{userId}** - Usuwanie użytkownika z grupy
   - Zaimplementować metodę removeUserFromGroup() w Service
   - Dodać endpoint w Controller
   - Walidacja: grupa musi mieć przynajmniej 1 OWNER
   - Testy funkcjonalne

3. **PATCH /admin/groups/{groupId}/users/{userId}/role** - Zmiana roli użytkownika
   - Zaimplementować metodę updateUserRole() w Service
   - Dodać endpoint w Controller
   - Walidacja: nie można zdegradować ostatniego OWNER
   - Testy funkcjonalne

### Priorytet 2: Zaawansowane Funkcje
1. **Walidacje biznesowe**
   - validateLastOwnerNotRemoved() - zapobieganie usunięciu ostatniego OWNER
   - Walidacja przy degradacji OWNER → MEMBER
   - Opcjonalnie: limit członków w grupie

2. **Testy**
   - Testy funkcjonalne dla wszystkich endpointów GroupMembershipController
   - Testy integracyjne dla GroupMembershipService
   - Coverage > 80%

3. **Dokumentacja API**
   - Dodać adnotacje OpenAPI/Swagger do kontrolerów
   - Wygenerować dokumentację API
   - Przykłady requestów i responses

### Priorytet 3: Opcjonalne Rozszerzenia (Nice to Have)
1. **Eventy i Audyt**
   - UserAddedToGroupEvent
   - UserRemovedFromGroupEvent
   - UserRoleChangedEvent
   - Logi audytowe zmian członkostwa

2. **Notyfikacje**
   - Email przy dodaniu do grupy
   - Email przy usunięciu z grupy
   - Email przy zmianie roli

3. **Historia i Raportowanie**
   - Historia członkostwa (soft delete)
   - Eksport listy członków (CSV/Excel)
   - Statystyki członkostwa

4. **Rozszerzenia UX**
   - Hurtowe dodawanie użytkowników (bulk operations)
   - Import użytkowników z CSV
   - Kopiowanie członków między grupami
   - Szablony grup z domyślnymi członkami

### Priorytet 4: Integracja z Frontend
1. **Panel Administracyjny**
   - Zakładka "Grupy" w panelu admin
   - Widok szczegółów grupy z listą członków
   - Formularz dodawania użytkowników (dropdown)
   - Akcje: zmień rolę, usuń z grupy

2. **Komponenty UI**
   - Komponent GroupMemberList
   - Komponent AddUserToGroupForm
   - Komponent MemberActionsMenu
   - Komunikaty sukcesu/błędów

---

## 17. Znane Ograniczenia i TODO

### Techniczne
- [ ] Brak paginacji w GET /admin/groups/{groupId}/users (backend gotowy, endpoint TODO)
- [ ] Brak walidacji ostatniego OWNER przy usuwaniu/degradacji
- [ ] Brak testów dla GroupMembershipController
- [ ] Brak dokumentacji OpenAPI/Swagger

### Biznesowe
- [ ] Brak limitu członków w grupie
- [ ] Brak historii członkostwa (kto kiedy został dodany/usunięty)
- [ ] Brak powiadomień email
- [ ] Brak możliwości hurtowych operacji

### Bezpieczeństwo
- [x] ✅ Autoryzacja ROLE_ADMIN zaimplementowana
- [x] ✅ Walidacja istnienia użytkownika i grupy
- [x] ✅ Śledzenie kto dodał użytkownika (addedBy)
- [ ] Brak rate limiting na endpointy
- [ ] Brak audytu zmian (event sourcing)

---

**Utworzono:** 2026-01-27
**Ostatnia aktualizacja:** 2026-01-27
**Autor:** Maciej Szklarczyk
**Status:** 🟢 W implementacji (MVP częściowo gotowe)
