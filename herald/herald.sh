#!/usr/bin/env bash
set -euo pipefail

SCRIPT="${BASH_SOURCE[0]}"
while [ -L "$SCRIPT" ]; do SCRIPT="$(readlink "$SCRIPT")"; done
ROOT="$(cd "$(dirname "$SCRIPT")/.." && pwd)"

up() {
  (cd "$ROOT/backend" && docker compose up -d)
  (cd "$ROOT/frontend" && docker compose up -d)
  echo "✔ backend: http://localhost:8000  frontend: http://localhost:3000"
}

down() {
  (cd "$ROOT/frontend" && docker compose down -v)
  (cd "$ROOT/backend" && docker compose down)
}

restart() {
  down
  up
}

db-reset() {
  docker exec planner-php php bin/console doctrine:fixtures:load --no-interaction
}

status() {
  docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
}

health() {
  _check() {
    local name=$1 url=$2 code
    code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 3 "$url" 2>/dev/null) || true
    if [[ "${code:-000}" =~ ^[2-4][0-9]{2}$ ]]; then
      echo "✔ $name — HTTP $code  ($url)"
    else
      echo "✖ $name — unreachable  ($url)"
    fi
  }
  _check "backend"  "http://localhost:8000/api"
  _check "frontend" "http://localhost:3000"
}

test() {
  docker compose -f "$ROOT/backend/docker-compose.yaml" run --rm php \
    env $(cat "$ROOT/backend/.env.test" | grep -v '^#' | xargs) \
    composer run-tests
}

dirty() {
  local clean=true
  for repo in backend frontend bruno herald; do
    local dir="$ROOT/$repo"
    local branch
    branch=$(git -C "$dir" branch --show-current 2>/dev/null)
    local uncommitted
    uncommitted=$(git -C "$dir" status --porcelain 2>/dev/null)
    local unpushed
    unpushed=$(git -C "$dir" log --oneline "@{u}..HEAD" 2>/dev/null || true)
    if [[ -n "$uncommitted" || -n "$unpushed" ]]; then
      clean=false
      echo "✖ $repo ($branch)"
      [[ -n "$uncommitted" ]] && echo "    uncommitted: $(echo "$uncommitted" | wc -l | tr -d ' ') file(s)"
      [[ -n "$unpushed"    ]] && echo "    unpushed:    $(echo "$unpushed"    | wc -l | tr -d ' ') commit(s)"
    else
      echo "✔ $repo ($branch)"
    fi
  done
  $clean && echo "All repos clean." || true
}

cleanup() {
  local has_changes=false
  for repo in backend frontend bruno herald; do
    local dir="$ROOT/$repo"
    local modified untracked
    modified=$(git -C "$dir" status --porcelain 2>/dev/null)
    untracked=$(git -C "$dir" clean -nfd 2>/dev/null)
    if [[ -n "$modified" || -n "$untracked" ]]; then
      has_changes=true
      echo "── $repo ──"
      [[ -n "$modified"  ]] && echo "$modified" | sed 's/^/  /'
      [[ -n "$untracked" ]] && echo "$untracked" | sed 's/^/  /'
    fi
  done
  if [[ "$has_changes" == false ]]; then
    echo "Nothing to clean."; return
  fi
  echo ""
  echo "All listed files will be permanently lost (reset --hard + clean -fd)."
  read -r -p "Are you sure? [y/N] " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || { echo "Aborted."; return; }
  for repo in backend frontend bruno herald; do
    git -C "$ROOT/$repo" reset --hard
    git -C "$ROOT/$repo" clean -fd
    echo "✔ $repo — clean"
  done
}

case "${1:-}" in
  up)       up ;;
  down)     down ;;
  restart)  restart ;;
  db-reset) db-reset ;;
  status)   status ;;
  health)   health ;;
  test)     test ;;
  dirty)    dirty ;;
  cleanup)  cleanup ;;
  *)        echo "Usage: $0 {up|down|restart|db-reset|status|health|test|dirty|cleanup}"; exit 1 ;;
esac
