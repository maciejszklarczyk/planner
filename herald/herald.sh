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

case "${1:-}" in
  up)       up ;;
  down)     down ;;
  restart)  restart ;;
  db-reset) db-reset ;;
  status)   status ;;
  health)   health ;;
  *)        echo "Usage: $0 {up|down|restart|db-reset|status|health}"; exit 1 ;;
esac
