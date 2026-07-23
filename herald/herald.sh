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
  local branch
  branch=$(git -C "$ROOT" branch --show-current 2>/dev/null)
  local uncommitted
  uncommitted=$(git -C "$ROOT" status --porcelain 2>/dev/null)
  local unpushed
  unpushed=$(git -C "$ROOT" log --oneline "@{u}..HEAD" 2>/dev/null || true)
  if [[ -n "$uncommitted" || -n "$unpushed" ]]; then
    echo "✖ planner ($branch)"
    [[ -n "$uncommitted" ]] && echo "    uncommitted: $(echo "$uncommitted" | wc -l | tr -d ' ') file(s)"
    [[ -n "$unpushed"    ]] && echo "    unpushed:    $(echo "$unpushed"    | wc -l | tr -d ' ') commit(s)"
  else
    echo "✔ planner ($branch) — clean"
  fi
}

cleanup() {
  local modified untracked
  modified=$(git -C "$ROOT" status --porcelain 2>/dev/null)
  untracked=$(git -C "$ROOT" clean -nfd 2>/dev/null)
  if [[ -z "$modified" && -z "$untracked" ]]; then
    echo "Nothing to clean."; return
  fi
  [[ -n "$modified"  ]] && echo "$modified" | sed 's/^/  /'
  [[ -n "$untracked" ]] && echo "$untracked" | sed 's/^/  /'
  echo ""
  echo "All listed files will be permanently lost (reset --hard + clean -fd)."
  read -r -p "Are you sure? [y/N] " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || { echo "Aborted."; return; }
  git -C "$ROOT" reset --hard
  git -C "$ROOT" clean -fd
  echo "✔ planner — clean"
}

help() {
  echo "Usage: $0 <command>"
  echo ""
  echo "Commands:"
  echo "  up        Start backend and frontend containers"
  echo "  down      Stop and remove containers"
  echo "  restart   Restart all containers"
  echo "  db-reset  Reload fixtures into the database"
  echo "  status    Show running Docker containers"
  echo "  health    Check backend and frontend reachability"
  echo "  test      Run backend test suite"
  echo "  dirty     Show uncommitted/unpushed changes across all repos"
  echo "  cleanup   Reset all repos to a clean state (destructive)"
  echo "  help      Show this help message"
}

case "${1:-help}" in
  up)       up ;;
  down)     down ;;
  restart)  restart ;;
  db-reset) db-reset ;;
  status)   status ;;
  health)   health ;;
  test)     test ;;
  dirty)    dirty ;;
  cleanup)  cleanup ;;
  help|--help|-h) help ;;
  *)        echo "Unknown command: $1"; echo ""; help; exit 1 ;;
esac
