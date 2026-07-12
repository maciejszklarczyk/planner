# Setup GitLab Runner (Docker Executor) dla lokalnego deploymentu

> **Uwaga:** Ten dokument używa `/opt/plan-backend` jako przykładowej ścieżki deploymentu.
> Aktualna wartość `DEPLOY_DIR` w `.gitlab-ci.yml` to `/home/maciej/docker/apps/planner/backend` —
> dopasuj ścieżki poniżej do tej zmiennej.

## 1. Konfiguracja Runner Config

Edytuj konfigurację runnera:

```bash
sudo nano /etc/gitlab-runner/config.toml
```

Dodaj/zmień sekcję `[runners.docker]` aby wyglądała tak:

```toml
[[runners]]
  name = "my-docker-runner"
  url = "https://gitlab.com/"
  token = "TWOJ_TOKEN"
  executor = "docker"
  [runners.docker]
    tls_verify = false
    image = "docker:latest"
    privileged = false
    disable_entrypoint_overwrite = false
    oom_kill_disable = false
    disable_cache = false
    volumes = [
      "/var/run/docker.sock:/var/run/docker.sock",
      "/opt/plan-backend:/opt/plan-backend"
    ]
    shm_size = 0
  [runners.cache]
    [runners.cache.s3]
    [runners.cache.gcs]
    [runners.cache.azure]
```

**Kluczowe elementy:**
- `"/var/run/docker.sock:/var/run/docker.sock"` - dostęp do Docker hosta (budowanie obrazów)
- `"/opt/plan-backend:/opt/plan-backend"` - dostęp do katalogu deploymentu
- `privileged = false` - bezpieczniejsze (nie potrzebujemy privileged mode)

## 2. Restart runnera

```bash
sudo systemctl restart gitlab-runner
sudo gitlab-runner verify
```

Output powinien pokazać:
```
Verifying runner... is alive
```

## 3. Przygotuj katalog deploymentu

```bash
# Utwórz katalog
sudo mkdir -p /opt/plan-backend

# Ustaw odpowiednie uprawnienia
# Runner będzie działał jako root w kontenerze, ale pliki muszą być dostępne
sudo chmod 755 /opt/plan-backend
```

## 4. Skopiuj pliki konfiguracyjne

### Na serwerze utwórz pliki:

#### .env
```bash
sudo nano /opt/plan-backend/.env
```

Wklej zawartość (z .env.example):
```env
APP_ENV=prod
APP_SECRET=ZMIEN_NA_LOSOWY_SECRET_64_ZNAKI

POSTGRES_VERSION=16
POSTGRES_DB=plan_production
POSTGRES_USER=plan_user
POSTGRES_PASSWORD=ZMIEN_NA_SILNE_HASLO
DATABASE_URL="postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@database:5432/${POSTGRES_DB}?serverVersion=${POSTGRES_VERSION}&charset=utf8"

PROJECT_NAME=plan
```

#### docker-compose.prod.yaml
```bash
# Skopiuj z repozytorium lub utwórz
sudo nano /opt/plan-backend/docker-compose.prod.yaml
```

**WAŻNE:** Zmień domenę w Traefik labels:
```yaml
labels:
  - "traefik.http.routers.plan-backend.rule=Host(`api.TWOJA-DOMENA.pl`)"
```

## 5. Dodaj tag do runnera w GitLab

1. GitLab → Settings → CI/CD → Runners
2. Znajdź swojego runnera (powinien być zielony/aktywny)
3. Kliknij **Edit** (ikona ołówka)
4. W polu **Tags** wpisz: `docker`
5. ✅ Zaznacz **"Run untagged jobs"** (opcjonalnie, dla elastyczności)
6. **Save changes**

## 6. Dodaj zmienne w GitLab (opcjonalne dla registry)

GitLab → Settings → CI/CD → Variables

Jeśli chcesz pushować do GitLab Container Registry:

| Key | Value | Protected | Masked |
|-----|-------|-----------|--------|
| `CI_REGISTRY_USER` | Twój GitLab username | ✅ | ❌ |
| `CI_REGISTRY_PASSWORD` | Personal Access Token (scope: `write_registry`) | ✅ | ✅ |

**Jak stworzyć Personal Access Token:**
- GitLab → User Settings → Access Tokens
- Name: `gitlab-ci-registry`
- Scopes: ✅ `write_registry`, ✅ `read_registry`
- Create token → skopiuj wartość

## 7. Sprawdź czy sieć zbyszek-network istnieje

```bash
docker network ls | grep zbyszek-network
```

Jeśli nie istnieje:
```bash
docker network create zbyszek-network
```

Skoro Traefik już działa w tej sieci, powinna już istnieć.

## 8. Test konfiguracji

### Test 1: Sprawdź czy runner widzi docker.sock

```bash
# Uruchom testowy job ręcznie (w GitLab lub lokalnie)
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock docker:latest docker ps
```

Powinno pokazać listę kontenerów.

### Test 2: Sprawdź dostęp do katalogu

```bash
docker run --rm -v /opt/plan-backend:/opt/plan-backend docker:latest ls -la /opt/plan-backend
```

Powinno pokazać zawartość `/opt/plan-backend`.

### Test 3: Sprawdź docker compose

```bash
docker run --rm \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v /opt/plan-backend:/opt/plan-backend \
  docker:latest sh -c "apk add --no-cache docker-compose && docker compose version"
```

## 9. Pierwszy deploy

### Krok 1: Commit i push
```bash
git add .gitlab-ci.yml docker-compose.prod.yaml .env.example
git commit -m "Configure Docker executor deployment"
git push origin main
```

### Krok 2: Monitoruj pipeline w GitLab
1. GitLab → CI/CD → Pipelines
2. Push na `main` buduje i wypycha image (stage **docker-build**), ale **nie** deployuje
3. Deploy odpala się automatycznie tylko po pushu tagu pasującego do `vX.Y.Z`
   (`git tag vX.Y.Z && git push origin vX.Y.Z`) — stage **deploy-production**
   uruchamia się wtedy sam, bez manualnego triggera

### Krok 3: Sprawdź logi

**Logi runnera:**
```bash
sudo journalctl -u gitlab-runner -f
```

**Logi aplikacji:**
```bash
docker compose -f /opt/plan-backend/docker-compose.prod.yaml logs -f
```

**Status kontenerów:**
```bash
docker compose -f /opt/plan-backend/docker-compose.prod.yaml ps
```

## 10. Troubleshooting

### Błąd: "Cannot connect to Docker daemon"

**Problem:** Runner nie ma dostępu do docker.sock

**Rozwiązanie:**
```bash
# Sprawdź uprawnienia
ls -la /var/run/docker.sock

# Powinno być: srw-rw---- 1 root docker

# Jeśli nie, ustaw uprawnienia
sudo chmod 666 /var/run/docker.sock  # UWAGA: to tymczasowe rozwiązanie
# lub lepiej:
sudo chown root:docker /var/run/docker.sock
```

### Błąd: "permission denied" w /opt/plan-backend

**Rozwiązanie:**
```bash
sudo chmod -R 755 /opt/plan-backend
```

### Job "pending" - nie uruchamia się

**Problem:** Runner nie ma odpowiedniego taga

**Rozwiązanie:**
- Sprawdź czy runner ma tag `docker`
- Sprawdź czy runner jest online (zielony) w GitLab → Runners
- Sprawdź czy w `.gitlab-ci.yml` używasz właściwego taga

### Błąd: "docker: not found" w stage deploy

**Problem:** Brak docker compose w image

**Rozwiązanie:** Już dodane w `.gitlab-ci.yml`:
```yaml
before_script:
  - apk add --no-cache docker-compose
```

### Container nie startuje po deploymencie

**Sprawdź logi:**
```bash
docker compose -f /opt/plan-backend/docker-compose.prod.yaml logs php
docker compose -f /opt/plan-backend/docker-compose.prod.yaml logs database
```

**Typowe problemy:**
- Zły `.env` (hasło do bazy, APP_SECRET)
- Brak sieci Traefik: `docker network create traefik`
- Konflikt portów: sprawdź `docker ps`

## 11. Opcje optymalizacji

### Wyłącz manual trigger (automatyczny deploy)

W `.gitlab-ci.yml` usuń:
```yaml
deploy-production:
  when: manual  # <- usuń tę linię
```

### Dodaj cache dla Docker layers

```yaml
docker-build:
  variables:
    DOCKER_BUILDKIT: 1
  script:
    - docker build --cache-from $CI_REGISTRY_IMAGE:latest ...
```

### Health check po deploymencie

Dodaj do stage deploy:
```yaml
deploy-production:
  script:
    # ... existing commands ...
    - sleep 5
    - |
      if ! docker compose -f docker-compose.prod.yaml exec -T php php -v; then
        echo "Health check failed! Rolling back..."
        docker compose -f docker-compose.prod.yaml down
        exit 1
      fi
```

## 12. Porównanie: Volumes vs DinD

| Aspekt | Docker Socket (obecnie) | Docker-in-Docker (DinD) |
|--------|-------------------------|-------------------------|
| Konfiguracja | Prostsza | Bardziej złożona |
| Bezpieczeństwo | Średnie (dostęp do hosta) | Lepsze (izolacja) |
| Performance | Szybsze | Wolniejsze |
| Cache | Współdzielony | Per-job |
| Privileged | Nie wymagane | Wymagane |

**Rekomendacja:** Docker Socket (obecna konfiguracja) jest lepsza dla domowego serwera.

## 13. Bezpieczeństwo

### Jeśli runner jest shared (używany przez wiele projektów):

**Opcja 1:** Użyj DinD zamiast docker.sock (bezpieczniejsze, ale wolniejsze)

**Opcja 2:** Ogranicz które projekty mogą używać runnera:
- GitLab → Runner Settings → "Lock to current projects"

**Opcja 3:** Stwórz dedykowanego runnera tylko dla tego projektu:
```bash
sudo gitlab-runner register --locked
```

Dla domowego serwera (jeden projekt) obecna konfiguracja jest OK.
