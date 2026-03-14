# Status prac — PLA-12

## Zrealizowane

### Zaproszenia użytkowników
- Encja `UserInvitationToken` z tokenem (unikalny), emailem, datą wygaśnięcia (`+24h`) i `usedAt`
- Enum `UserStatusEnum`: `new | active | inactive | blocked | deleted`
- Pole `status` na encji `User` (domyślnie `new`)
- Relacja `User.addedBy` — kto dodał użytkownika
- `InvitationMailer` — serwis do wysyłki maili z linkiem do ustawienia hasła
- `POST /admin/user-invite` — tworzy usera, token, wysyła mail
- `POST /admin/user-invite/resend` — unieważnia stare tokeny, wysyła nowe zaproszenie (tylko dla statusu `new`)
- `GET /invitation/verify?token=` — walidacja tokenu (publiczny)
- `POST /invitation/complete` — ustawienie hasła i aktywacja konta (publiczny)

### Zarządzanie członkostwem w grupach
- `GET /admin/groups/{groupId}/users` — lista członków grupy z rolami
- `POST /admin/groups/{groupId}/users` — dodanie użytkownika do grupy (było wcześniej)
- `DELETE /admin/groups/{groupId}/users/{userId}` — usunięcie użytkownika z grupy (z ochroną ostatniego ownera)
- `PATCH /admin/groups/{groupId}/users/{userId}/role` — zmiana roli użytkownika w grupie (z ochroną ostatniego ownera)

---

## Do zrobienia

### Backend
- (brak otwartych zadań)

### Otwarte kwestie
- Brak możliwości ponownego zaproszenia jeśli poprzedni token wygasł a user ma status inny niż `new` — rozważyć reset statusu lub osobny flow
- Mail wysyłany z hardcoded treścią HTML — w przyszłości zastąpić szablonami Twig

---

## Pomysły do rozważenia

### Struktura repo i CI/CD

#### Wariant A — Dockerfile przy kodzie, osobne repo infra (preferowany)
```
plan/backend   → Dockerfile + .gitlab-ci.yml (build image → push do registry)
plan/frontend  → Dockerfile + .gitlab-ci.yml (build image → push do registry)
plan/infra     → docker-compose.prod.yml, Traefik config, .gitlab-ci.yml (deploy)
```
- Każdy serwis buduje i pushuje własny obraz (`registry.gitlab.com/org/plan/backend:sha`)
- Repo `infra` triggerowane przez `trigger:` z pipeline'ów serwisów lub ręcznie
- Dockerfile zostaje blisko kodu — zmiana zależności nie wymaga commitowania w dwóch miejscach
- Lokalny dev: `compose.override.yaml` zostaje w każdym serwisie

#### Wariant B — Mono-repo
```
plan/
├── backend/
├── frontend/
├── docker-compose.yml
└── .gitlab-ci.yml
```
- Jeden pipeline, jeden clone, zero cross-project triggerów
- Prostsze dla małego zespołu
- Mniej elastyczne przy skalowaniu zespołu lub CI

**Odradzany:** wyciąganie Dockerfile do repo infra — przy każdej zmianie zależności trzeba commitować w dwóch miejscach jednocześnie.