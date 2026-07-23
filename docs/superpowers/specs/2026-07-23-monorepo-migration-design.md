# Monorepo migration — design

Data: 2026-07-23

## Kontekst

Projekt `planner` (dawniej katalog `plan`, przemianowany na `planner`) składa się z 4 osobnych repozytoriów git na GitLab, trzymanych obok siebie w jednym katalogu na dysku i spiętych wyłącznie przez `herald/herald.sh`:

- `backend` — git@gitlab.com:planner6551704/backend.git
- `frontend` — git@gitlab.com:planner6551704/frontend.git
- `herald` — git@gitlab.com:planner6551704/herald.git
- `bruno` — git@gitlab.com:planner6551704/bruno.git

Backend ma pipeline deploy na tag semver (`v\d+\.\d+\.\d+`). Frontend deployuje się automatycznie na merge do `main` — niespójne z backendem.

## Cel

Połączyć wszystkie 4 repozytoria w jedno repo monorepo, zachowując pełną historię commitów (git blame/log), z płaską strukturą katalogów identyczną jak dziś.

## Decyzje

1. **Historia**: zachowana — migracja przez `git filter-repo --to-subdirectory-filter <nazwa>/` na każdym z 4 repo, potem scalenie do nowego pustego repo przez `git remote add` + `fetch` + `merge --allow-unrelated-histories`.
2. **Stare repo na GitLab**: zostają bez zmian na razie (backup), nieusuwane, niearchiwizowane w ramach tej migracji.
3. **Struktura katalogów**: płaska — `backend/`, `frontend/`, `herald/`, `bruno/`, `docs/` w rootcie nowego repo, bez zmian względem obecnej.
4. **CI/CD**: root `.gitlab-ci.yml` z `include: local:` dla `backend/.gitlab-ci.yml` i `frontend/.gitlab-ci.yml`. Joby build/test/quality uruchamiają się zawsze (bez path-filtrów `changes:`), niezależnie od tego co się zmieniło. `deploy-production` w obu ujednolicone na regułę `if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'` — frontend traci dotychczasowy auto-deploy na merge do main. Jeden wspólny tag `vX.Y.Z` deployuje backend i frontend razem.
5. **Docker compose (dev)**: bez zmian — zostają osobne `backend/docker-compose.yaml` i `frontend/docker-compose.yaml`, spinane przez `herald.sh`.
6. **`herald/herald.sh`**: wymaga zmiany w funkcjach `dirty` i `cleanup` — dziś iterują po 4 osobnych `.git` w podkatalogach (`backend`, `frontend`, `bruno`, `herald`). Po migracji istnieje jeden `.git` w rootcie monorepo, więc te funkcje muszą operować bezpośrednio na `$ROOT` (`git -C "$ROOT" status --porcelain`, `git -C "$ROOT" log`, itd.) zamiast pętli po podkatalogach.

## Kroki migracji

1. Zainstalować `git-filter-repo` (jeśli brak).
2. Dla każdego z `backend`, `frontend`, `herald`, `bruno`:
   - sklonować świeżą kopię repo (osobno od katalogu roboczego, żeby nie ruszać obecnych zmian),
   - `git filter-repo --to-subdirectory-filter <nazwa>/`.
3. `git init` w `/Users/maciejszklarczyk/Projects/planner` (root obecnie nie jest repo git).
4. Dla każdego przefiltrowanego repo: dodać jako remote, `git fetch`, `git merge --allow-unrelated-histories <remote>/main` (lub odpowiedni branch domyślny).
5. Rozwiązać ewentualne konflikty (nie powinno ich być — katalogi się nie pokrywają).
6. Utworzyć root `.gitlab-ci.yml` z `include: local:`, dodać `cd backend`/`cd frontend` na początku skryptów w istniejących `.gitlab-ci.yml` (bo joby z `include:local` uruchamiają się z rootu repo, nie z podkatalogu).
7. Zmienić regułę `deploy-production` we `frontend/.gitlab-ci.yml` na tag semver (jak w backend).
8. Poprawić `herald/herald.sh` (`dirty`, `cleanup`) pod jeden `.git` w rootcie.
9. Bez pushowania na razie — migracja zostaje lokalna w tym katalogu. Remote GitLab dla monorepo to osobna decyzja na później.
10. Zweryfikować: `herald.sh up`, `herald.sh test`, `herald.sh dirty` działają poprawnie z nowej struktury.

## Poza zakresem

- Nie ruszamy starych 4 repozytoriów na GitLab (zostają, bez archiwizacji).
- Nie scalamy docker-compose w jeden root plik.
- Nie wprowadzamy narzędzi typu Nx/Turborepo (stack mieszany PHP+Node, nie pasuje).
