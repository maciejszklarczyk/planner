# plan.sh — `up` / `down` dla stacku dev

**Data:** 2026-04-24
**Status:** Draft — oczekuje review użytkownika

## Cel

Jeden skrypt (`plan.sh`) w roocie projektu uruchamia i zatrzymuje cały stack developerski (backend + frontend) bez zależności od lokalnej wersji node/npm.

## Kontekst

- Backend: `backend/docker-compose.yaml` już istnieje (php, db, redis, itd.)
- Frontend: dotąd uruchamiany przez `npm run dev` lokalnie
- Lokalne node/npm tworzą tarcia (różne wersje, zanieczyszczenie node_modules)
- `plan.sh` istnieje w roocie, ale jest pusty

## Scope

**In:**
- `frontend/docker-compose.yaml` — dev-compose dla frontendu
- `plan.sh` — CLI z dwoma podkomendami: `up`, `down`

**Out (świadomie):**
- `logs`, `status`, `restart`
- Dedykowany `Dockerfile.dev` (wystarczy `node:20-alpine` z volume mountem)
- Produkcyjne zmiany (`frontend/docker-compose.prod.yaml` nie ruszamy)
- Auto-polling dla zmian plików (dodać później jako `WATCHPACK_POLLING=true`, jeśli HMR nie działa na macOS)

## Architektura

### `frontend/docker-compose.yaml`

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

**Decyzje:**
- `node:20-alpine` — spójność z prod Dockerfile (stage `deps`)
- `HOSTNAME=0.0.0.0` — Next.js w kontenerze musi słuchać poza pętlą zwrotną
- Anonymous volume `/app/node_modules` — przykrywa `node_modules` z bind mounta hosta, unika konfliktu arch (macOS arm64 vs linux alpine, np. `@next/swc`)
- Warunkowy `npm ci` — instaluje tylko przy pierwszym uruchomieniu; kolejne `up` startują natychmiast
- `NEXT_PUBLIC_API_URL=http://localhost:8000` — przeglądarka łączy się z backendem przez port hosta

### `plan.sh`

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

**Decyzje:**
- CLI subcommand (`./plan.sh up`) zamiast sourcowanych funkcji — nic nie trzeba sourcować, wykonywalne od razu
- `set -euo pipefail` — fail-fast na błędach, niezainicjowanych zmiennych, błędach w pipe
- `ROOT` liczony z `$BASH_SOURCE[0]` — działa z dowolnego `cwd`
- Kolejność `down`: najpierw frontend (zależny), potem backend
- Brak `--build` / `--pull` — domyślne zachowanie compose wystarczy dla dev

## Użycie

```bash
chmod +x plan.sh       # jednorazowo
./plan.sh up           # start obu stacków (backend już w tle, frontend nowy)
./plan.sh down         # stop obu
```

## Ryzyka / znane kwestie

| Ryzyko | Łagodzenie |
|---|---|
| HMR na macOS przez bind mount bywa zawodny | Dodać `WATCHPACK_POLLING=true` do `environment:`, jeśli wystąpi |
| `npm ci` może się nie zbudować jeśli `package-lock.json` nie zsynchronizowane | Użytkownik wie — komunikat `npm` wystarczy |
| Port 3000/8000 już zajęty | `docker compose` da jasny komunikat błędu |
| Backend compose używa `${PROJECT_NAME}` — wymagany `.env` | Już istnieje w `backend/` — status quo |

## Testowanie ręczne

1. `./plan.sh up` → oba kontenery wstają, `curl http://localhost:3000` zwraca HTML, `curl http://localhost:8000/api/doc` → Swagger
2. Zmiana pliku w `frontend/app/` → HMR przeładowuje przeglądarkę
3. `./plan.sh down` → oba kontenery zniknęły (`docker ps` puste dla `plan-*`)
4. `./plan.sh up` drugi raz → start szybki (brak `npm ci`, bo `node_modules` już w anon volume)
5. `./plan.sh` (bez arg) → usage + exit 1
