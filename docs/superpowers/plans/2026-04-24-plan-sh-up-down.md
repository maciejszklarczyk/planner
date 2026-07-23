# plan.sh up/down Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Jeden skrypt w roocie projektu (`plan.sh up` / `plan.sh down`) startuje i zatrzymuje pełny dev stack (backend + frontend) wyłącznie przez Docker — bez lokalnego npm.

**Architecture:** Dodajemy nowy `frontend/docker-compose.yaml` (dev) z kontenerem `node:20-alpine` i anonymous volume na `node_modules` (unika konfliktu arch macOS arm64 ↔ alpine linux). `plan.sh` to bash CLI z subkomendami `up`/`down`, które wołają `docker compose` dla obu compose files. Weryfikacja manualna (smoke test HTTP + `docker ps`) — brak unit testów.

**Tech Stack:** bash, docker compose, node:20-alpine, Next.js 16

**Spec:** `docs/superpowers/specs/2026-04-24-plan-sh-up-down-design.md`

---

## File Structure

- Create: `frontend/docker-compose.yaml` — dev compose dla frontendu (node container, bind mount, anon volume)
- Modify: `plan.sh` — obecnie pusty, wypełniany o bash CLI z `up`/`down`

Brak zmian w backendzie, `frontend/docker-compose.prod.yaml`, Dockerfile produkcyjnym.

---

### Task 1: Utwórz `frontend/docker-compose.yaml`

**Files:**
- Create: `frontend/docker-compose.yaml`

- [ ] **Step 1: Stwórz plik z dev compose**

Zawartość `frontend/docker-compose.yaml`:

```yaml
services:
  frontend:
    image: node:20-alpine
    container_name: plan-frontend-dev
    working_dir: /app
    ports:
      - "3000:3000"
    volumes:
      - .:/app
      - /app/node_modules
    environment:
      - HOSTNAME=0.0.0.0
      - NEXT_PUBLIC_API_URL=http://localhost:8000
    command: sh -c "[ -d node_modules ] || npm ci; npm run dev"
```

- [ ] **Step 2: Walidacja składni compose**

Run: `docker compose -f frontend/docker-compose.yaml config`
Expected: wypisuje sparsowaną konfigurację bez błędów; `services.frontend.image: node:20-alpine`, ports `3000:3000`.

- [ ] **Step 3: Smoke test — uruchomienie samego frontendu**

Run: `docker compose -f frontend/docker-compose.yaml up -d`
Potem: `docker logs plan-frontend-dev -f` (Ctrl+C żeby odpiąć).
Expected: widać `npm ci` (pierwszy raz) potem `Next.js ... Local: http://localhost:3000`.
Weryfikacja: `curl -s -o /dev/null -w "%{http_code}" http://localhost:3000` → `200`.

- [ ] **Step 4: Smoke test — zatrzymanie**

Run: `docker compose -f frontend/docker-compose.yaml down`
Expected: kontener `plan-frontend-dev` zniknął (`docker ps -a --filter name=plan-frontend-dev` → pusty).

- [ ] **Step 5: Commit**

Projekt nie jest obecnie git repo (sprawdź `git rev-parse --is-inside-work-tree`). Jeśli jest — commituj:

```bash
git add frontend/docker-compose.yaml
git commit -m "feat(frontend): add dev docker-compose for node dev server"
```

Jeśli nie — pomiń commit, zgłoś w podsumowaniu.

---

### Task 2: Wypełnij `plan.sh` o CLI `up`/`down`

**Files:**
- Modify: `plan.sh` (obecnie pusty)

- [ ] **Step 1: Zapisz skrypt**

Zawartość `plan.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_COMPOSE="$ROOT/backend/docker-compose.yaml"
FRONTEND_COMPOSE="$ROOT/frontend/docker-compose.yaml"

up() {
  docker compose -f "$BACKEND_COMPOSE" up -d
  docker compose -f "$FRONTEND_COMPOSE" up -d
  echo "✔ backend: http://localhost:8000  frontend: http://localhost:3000"
}

down() {
  docker compose -f "$FRONTEND_COMPOSE" down
  docker compose -f "$BACKEND_COMPOSE" down
}

case "${1:-}" in
  up)   up ;;
  down) down ;;
  *)    echo "Usage: $0 {up|down}"; exit 1 ;;
esac
```

- [ ] **Step 2: Nadaj prawa wykonywalne**

Run: `chmod +x plan.sh`
Expected: `ls -l plan.sh` → `-rwxr-xr-x ... plan.sh`.

- [ ] **Step 3: Walidacja składni bash**

Run: `bash -n plan.sh`
Expected: brak outputu, exit 0.

- [ ] **Step 4: Test — brak argumentu pokazuje usage**

Run: `./plan.sh; echo "exit=$?"`
Expected: `Usage: ./plan.sh {up|down}` i `exit=1`.

- [ ] **Step 5: Test — nieznany argument pokazuje usage**

Run: `./plan.sh foo; echo "exit=$?"`
Expected: `Usage: ./plan.sh {up|down}` i `exit=1`.

- [ ] **Step 6: Commit**

Jeśli repo git:

```bash
git add plan.sh
git commit -m "feat: add plan.sh up/down CLI for full dev stack"
```

---

### Task 3: End-to-end weryfikacja `up` / `down`

**Files:** (brak zmian — test manualny)

- [ ] **Step 1: Upewnij się, że nic nie chodzi**

Run: `docker ps --format '{{.Names}}' | grep -E 'plan-|planner-' || echo "clean"`
Expected: `clean` (albo pusto). Jeśli coś chodzi — `./plan.sh down` albo `docker compose ... down` ręcznie.

- [ ] **Step 2: Wymagane pliki `.env` dla backendu**

Backend compose używa `${PROJECT_NAME}` i `env_file: [.env, .env.dev, .env.local]`. Sprawdź:

Run: `ls backend/.env backend/.env.dev 2>&1`
Expected: oba pliki istnieją. Jeśli brak — zgłoś użytkownikowi, nie twórz; to pliki projektowe.

- [ ] **Step 3: `./plan.sh up`**

Run: `./plan.sh up`
Expected output (skrócony):
```
[+] Running X/X  (backend services)
 ✔ Container planner-php  Started
 ...
[+] Running 1/1
 ✔ Container plan-frontend-dev  Started
✔ backend: http://localhost:8000  frontend: http://localhost:3000
```

- [ ] **Step 4: HTTP smoke test**

```bash
sleep 5  # Next.js potrzebuje chwili na pierwszy build (dev)
curl -s -o /dev/null -w "backend=%{http_code}\n" http://localhost:8000/api/doc
curl -s -o /dev/null -w "frontend=%{http_code}\n" http://localhost:3000
```
Expected: `backend=200` (albo `401`/`302` — byle nie `000`/`404`), `frontend=200`.

Jeśli `frontend=000` — kontener dalej buduje; `docker logs plan-frontend-dev --tail 50` i poczekaj jeszcze chwilę.

- [ ] **Step 5: Test HMR (bind mount działa)**

1. Otwórz `http://localhost:3000` w przeglądarce.
2. Zmień tekst w jakimkolwiek pliku `frontend/app/`.
3. Zapisz.
4. Przeglądarka powinna sama się odświeżyć w ciągu ~2 s.

Jeśli HMR nie działa na macOS — dodaj do `frontend/docker-compose.yaml`, w sekcji `environment`:
```yaml
      - WATCHPACK_POLLING=true
      - CHOKIDAR_USEPOLLING=true
```
Zrestartuj (`./plan.sh down && ./plan.sh up`) i sprawdź ponownie. Jeśli działa — zcommituj zmianę.

- [ ] **Step 6: `./plan.sh down`**

Run: `./plan.sh down`
Expected: oba stacki schodzą.

Weryfikacja: `docker ps --format '{{.Names}}' | grep -E 'plan-|planner-' || echo "clean"` → `clean`.

- [ ] **Step 7: Drugi `up` — szybki start (cached `node_modules`)**

Run: `time ./plan.sh up`
Expected: frontend startuje szybciej niż za pierwszym razem (brak `npm ci`, bo anon volume zachował `node_modules`). W logach `docker logs plan-frontend-dev --tail 20` — brak linii `npm ci` / `added N packages`, od razu `Next.js ... ready`.

- [ ] **Step 8: Cleanup**

Run: `./plan.sh down`
Expected: `clean` jak w Step 6.

- [ ] **Step 9: Commit — jeśli HMR wymagał polling flags (Step 5)**

Jeśli dodano `WATCHPACK_POLLING` — commit:

```bash
git add frontend/docker-compose.yaml
git commit -m "chore(frontend): enable watch polling for bind mount on macOS"
```

W przeciwnym razie — pomiń.

---

## Self-review

**Spec coverage:**
- Cel — pełny dev stack przez docker, bez lokalnego npm → Task 1 + 2 ✓
- `frontend/docker-compose.yaml` z node:20-alpine, anon volume, HOSTNAME, conditional `npm ci`, NEXT_PUBLIC_API_URL → Task 1 Step 1 ✓
- `plan.sh` z `up`/`down`/usage + `set -euo pipefail` + ROOT resolve → Task 2 Step 1 ✓
- Kolejność `down`: frontend → backend → Task 2 Step 1 (kod) ✓
- Risk: HMR na macOS — polling flags → Task 3 Step 5 ✓
- Risk: port zajęty — compose da błąd (nic do zrobienia w planie, domyślne zachowanie) ✓
- Risk: brak `.env` w backendzie → Task 3 Step 2 ✓
- Manual test plan z spec (pięć scenariuszy) → Task 3 Steps 3-8 ✓

**Placeholder scan:** brak TBD/TODO, każdy step ma komendę albo konkretny kod.

**Consistency:** nazwy `BACKEND_COMPOSE`/`FRONTEND_COMPOSE`, nazwa kontenera `plan-frontend-dev`, ścieżka `/app` — identyczne we wszystkich taskach.

Plan kompletny.
