# Monorepo Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Merge 4 separate GitLab repos (`backend`, `frontend`, `herald`, `bruno`) — currently sibling directories under `/Users/maciejszklarczyk/Projects/planner`, each with its own `.git` — into a single monorepo at that same root path, preserving full commit history per subdirectory, with a flat directory layout and unified CI deploy semantics.

**Architecture:** Use `git filter-repo --to-subdirectory-filter` to rewrite each source repo's history so every path lives under `<name>/`, then merge all four rewritten histories into one fresh repo at the root via `git remote add` + `fetch` + `merge --allow-unrelated-histories`. Root `.gitlab-ci.yml` uses `include: local:` to pull in each subproject's existing `.gitlab-ci.yml`, with jobs `cd`-ing into their subdirectory. `herald/herald.sh`'s `dirty`/`cleanup` commands are rewritten to operate on the single root repo instead of looping over 4 subdirectory repos.

**Tech Stack:** git, git-filter-repo, bash, GitLab CI YAML.

> **Post-execution note (2026-07-23):** two things below shipped differently than this plan describes, per whole-branch review findings:
> - Root `.gitlab-ci.yml` does **not** use flat `include: local:` as Task 3 describes — that design was found to collide backend's and frontend's job/stage/variable names into one namespace (frontend would silently overwrite backend's `docker-build`/`deploy-production`/`stages`/`variables`). It was replaced with **parent-child pipelines**: two `trigger: include: local:` jobs (`backend`, `frontend`) with `strategy: depend`, each giving its subproject a fully separate namespace. `backend/.gitlab-ci.yml` and `frontend/.gitlab-ci.yml` needed no further changes beyond what Task 3 already describes.
> - Both projects' `docker-build` job ended up gated on `if: main` OR `if: tag` (not tag-only for backend, not unconditional for frontend as Task 3 Step 2/3 originally specified) — matching a pattern already established and documented in backend's own prior CI rework (`backend/context/archive/2026-07-12-cicd-rework/`). `deploy-production` stays tag-only in both, as originally planned.

## Global Constraints

- Preserve full commit history (git log / blame) for every file from its original repo — spec section "Decyzje" #1.
- Do not touch, archive, or delete the 4 original GitLab repos — spec section "Decyzje" #2.
- Final directory layout is flat: `backend/`, `frontend/`, `herald/`, `bruno/`, `docs/` directly under repo root — spec section "Decyzje" #3.
- CI: build/test/quality jobs always run (no `changes:` path filters); `deploy-production` in both `backend/.gitlab-ci.yml` and `frontend/.gitlab-ci.yml` only fires on tag matching `^v\d+\.\d+\.\d+$` — spec section "Decyzje" #4.
- Do not merge or otherwise change `backend/docker-compose.yaml` / `frontend/docker-compose.yaml` — spec section "Decyzje" #5.
- No new remote/push in this plan — everything stays local to `/Users/maciejszklarczyk/Projects/planner` — spec section "Kroki migracji" #9.

---

### Task 1: Install git-filter-repo and stage rewritten copies of the 4 repos

**Files:**
- Create (scratch, outside the project): `/private/tmp/claude-501/-Users-maciejszklarczyk-Projects-plan/ed9fa796-ca1b-4a12-9c75-cfd186c7e351/scratchpad/monorepo-migration/{backend,frontend,herald,bruno}` — filtered clones, one per source repo.

**Interfaces:**
- Produces: 4 local bare-metal git repos, each containing the full history of one source repo, with every path prefixed by `<name>/` (e.g. `backend/composer.json` instead of `composer.json`). These are consumed by Task 2 as `git remote` sources.

- [ ] **Step 1: Install git-filter-repo**

Run: `brew install git-filter-repo`
Expected: exits 0, `git filter-repo --version` prints a version string.

- [ ] **Step 2: Create scratch directory for filtered clones**

Run:
```bash
mkdir -p /private/tmp/claude-501/-Users-maciejszklarczyk-Projects-plan/ed9fa796-ca1b-4a12-9c75-cfd186c7e351/scratchpad/monorepo-migration
cd /private/tmp/claude-501/-Users-maciejszklarczyk-Projects-plan/ed9fa796-ca1b-4a12-9c75-cfd186c7e351/scratchpad/monorepo-migration
```
Expected: directory exists, cwd changed.

- [ ] **Step 3: Clone each source repo fresh (do not touch the working copies under the project root)**

Run (from the scratch dir):
```bash
git clone /Users/maciejszklarczyk/Projects/planner/backend backend
git clone /Users/maciejszklarczyk/Projects/planner/frontend frontend
git clone /Users/maciejszklarczyk/Projects/planner/herald herald
git clone /Users/maciejszklarczyk/Projects/planner/bruno bruno
```
Expected: 4 subdirectories, each a full clone (`git -C backend log --oneline | wc -l` matches `git -C /Users/maciejszklarczyk/Projects/planner/backend log --oneline | wc -l`).

- [ ] **Step 4: Rewrite each clone's history into its subdirectory**

Run:
```bash
git -C backend filter-repo --to-subdirectory-filter backend/
git -C frontend filter-repo --to-subdirectory-filter frontend/
git -C herald filter-repo --to-subdirectory-filter herald/
git -C bruno filter-repo --to-subdirectory-filter bruno/
```
Expected: each command prints `New history written and repacked` (or similar filter-repo success output), exit 0. Verify with `git -C backend log --oneline -1 --name-only` — file paths now start with `backend/`.

- [ ] **Step 5: Note the default branch name of each clone (needed for Task 2)**

Run: `for d in backend frontend herald bruno; do echo "$d: $(git -C $d branch --show-current)"; done`
Expected: prints one branch name per repo (e.g. `main`). Record these — Task 2 uses them.

---

### Task 2: Initialize the monorepo and merge all 4 histories

**Files:**
- Modify: `/Users/maciejszklarczyk/Projects/planner` — becomes a git repo (currently has no `.git`).

**Interfaces:**
- Consumes: the 4 filtered clones from Task 1 (`.../monorepo-migration/{backend,frontend,herald,bruno}`), and the branch names recorded in Task 1 Step 5.
- Produces: `/Users/maciejszklarczyk/Projects/planner/.git` containing merged history from all 4 subprojects, working tree matching the flat layout already on disk.

- [ ] **Step 1: Verify the project root isn't already a repo, then init**

Run:
```bash
cd /Users/maciejszklarczyk/Projects/planner
git rev-parse --is-inside-work-tree 2>&1
git init
```
Expected: first command prints `fatal: not a git repository...` (confirms clean start); `git init` prints `Initialized empty Git repository in .../planner/.git/`.

- [ ] **Step 2: Add each filtered clone as a temporary remote and fetch**

Run (from `/Users/maciejszklarczyk/Projects/planner`):
```bash
git remote add backend-src /private/tmp/claude-501/-Users-maciejszklarczyk-Projects-plan/ed9fa796-ca1b-4a12-9c75-cfd186c7e351/scratchpad/monorepo-migration/backend
git remote add frontend-src /private/tmp/claude-501/-Users-maciejszklarczyk-Projects-plan/ed9fa796-ca1b-4a12-9c75-cfd186c7e351/scratchpad/monorepo-migration/frontend
git remote add herald-src /private/tmp/claude-501/-Users-maciejszklarczyk-Projects-plan/ed9fa796-ca1b-4a12-9c75-cfd186c7e351/scratchpad/monorepo-migration/herald
git remote add bruno-src /private/tmp/claude-501/-Users-maciejszklarczyk-Projects-plan/ed9fa796-ca1b-4a12-9c75-cfd186c7e351/scratchpad/monorepo-migration/bruno
git fetch --all
```
Expected: `git fetch --all` lists all 4 remotes fetched with no errors.

- [ ] **Step 3: Merge backend first (establishes the initial commit graph)**

Run: `git merge --allow-unrelated-histories -m "chore: merge backend history into monorepo" backend-src/<branch-from-task-1>`
Expected: exits 0, printing a merge summary listing files under `backend/`.

- [ ] **Step 4: Merge frontend, herald, bruno the same way**

Run:
```bash
git merge --allow-unrelated-histories -m "chore: merge frontend history into monorepo" frontend-src/<branch-from-task-1>
git merge --allow-unrelated-histories -m "chore: merge herald history into monorepo" herald-src/<branch-from-task-1>
git merge --allow-unrelated-histories -m "chore: merge bruno history into monorepo" bruno-src/<branch-from-task-1>
```
Expected: each merge exits 0 with no conflicts (directories don't overlap — `backend/`, `frontend/`, `herald/`, `bruno/` are disjoint). If a conflict appears on `docs/` (since `docs/` isn't part of any of the 4 source repos and already exists untracked on disk), resolve by keeping the on-disk version and `git add docs/`.

- [ ] **Step 5: Remove the temporary remotes and verify history**

Run:
```bash
git remote remove backend-src
git remote remove frontend-src
git remote remove herald-src
git remote remove bruno-src
git log --oneline | wc -l
git log --oneline --follow -- backend/composer.json | head -5
```
Expected: remotes gone (`git remote -v` prints nothing); commit count is roughly the sum of the 4 source repos' commit counts plus the merge commits; `--follow` on `backend/composer.json` shows real historical commits (not just the merge commit), confirming blame/log survived the filter.

- [ ] **Step 6: Add root-level files not yet tracked (CLAUDE.md, docs/, this plan/spec) and commit**

Run:
```bash
git add CLAUDE.md docs
git status
git commit -m "chore: add root CLAUDE.md and docs to monorepo"
```
Expected: commit succeeds; `git status` afterward shows only `.claude/`, `.idea/`, `.DS_Store` as untracked/ignored (leave those — they're editor/tool state, not part of this migration).

---

### Task 3: Unify CI — root include, tag-gated deploys, subdirectory `cd`

**Files:**
- Create: `/Users/maciejszklarczyk/Projects/planner/.gitlab-ci.yml`
- Modify: `/Users/maciejszklarczyk/Projects/planner/backend/.gitlab-ci.yml`
- Modify: `/Users/maciejszklarczyk/Projects/planner/frontend/.gitlab-ci.yml`

**Interfaces:**
- Produces: a root `.gitlab-ci.yml` that includes both subproject pipelines; each job in both subproject files runs from the repo root (via `cd`) and deploy jobs share the same tag rule.

- [ ] **Step 1: Create the root include file**

Write `/Users/maciejszklarczyk/Projects/planner/.gitlab-ci.yml`:
```yaml
include:
  - local: 'backend/.gitlab-ci.yml'
  - local: 'frontend/.gitlab-ci.yml'
```

- [ ] **Step 2: Make every backend job `cd` into `backend/` first**

Edit `/Users/maciejszklarczyk/Projects/planner/backend/.gitlab-ci.yml`. GitLab CI jobs pulled in via `include: local:` still run with the repo root as working directory, so every `script:`/`before_script:` must `cd backend` first. Rewrite each job's script block, prefixing with `cd backend &&` (or a leading `- cd backend` line). Resulting file:

```yaml
stages:
    - build
    - test
    - secret-detection
    - lint
    - docker-build
    - deploy

variables:
    SECRET_DETECTION_ENABLED: 'true'
    DEPLOY_DIR: '/home/maciej/docker/apps/planner/backend'
    DOCKER_DRIVER: overlay2
    DOCKER_TLS_CERTDIR: ""

secret_detection:
    stage: secret-detection
include:
    -   template: Security/Secret-Detection.gitlab-ci.yml

php-cs-fixer:
    stage: test
    image: composer:2.9.4
    needs:
        - composer
    script:
        - cd backend
        - vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff --verbose

phpstan:
    stage: test
    image: composer:2.9.4
    needs:
        - composer
    script:
        - cd backend
        - vendor/bin/phpstan analyse --memory-limit 1G

phpunit:
    stage: test
    image: composer:2.9.4
    needs:
        - composer
    script:
        - cd backend
        - apk add --no-cache $PHPIZE_DEPS linux-headers
        - pecl install pcov && docker-php-ext-enable pcov
        - vendor/bin/phpunit --coverage-cobertura=coverage.xml --coverage-text
    coverage: '/Lines:\s{3,}(\d+\.\d+)%/'
    artifacts:
        reports:
            coverage_report:
                coverage_format: cobertura
                path: backend/coverage.xml

composer-audit:
    stage: test
    image: composer:2.9.4
    script:
        - cd backend
        - composer audit --locked

composer:
    stage: build
    image: composer:2.9.4
    script:
        - cd backend
        - composer install --no-interaction --prefer-dist --ignore-platform-req=ext-gd
    artifacts:
        paths:
            - backend/vendor/
    cache:
        key:
            files:
                - backend/composer.lock
        paths:
            - backend/vendor/

lint:
    stage: lint
    image: composer:2.9.4
    needs:
        - composer
    script:
        - cd backend
        - composer validate
        - php bin/console lint:yaml config/
        - php bin/console lint:container

docker-build:
    stage: docker-build
    image: docker:latest
    tags:
        - docker
    script:
        - cd backend
        - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
        - docker build -t $CI_REGISTRY_IMAGE:latest -t $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA -f docker/php/Dockerfile.prod .
        - if [ -z "$CI_COMMIT_TAG" ]; then docker push $CI_REGISTRY_IMAGE:latest && docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA; fi
        - if [ -n "$CI_COMMIT_TAG" ]; then docker tag $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE:$CI_COMMIT_TAG && docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_TAG; fi
    rules:
        - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'

deploy-production:
    stage: deploy
    image: docker:latest
    tags:
        - docker
    before_script:
        - apk add --no-cache docker-compose curl
        - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    script:
        - cd backend
        - export IMAGE_TAG=$CI_COMMIT_TAG
        - cd $DEPLOY_DIR
        - cp $CI_PROJECT_DIR/backend/docker-compose.prod.yaml $DEPLOY_DIR/
        - docker compose -f docker-compose.prod.yaml pull
        - docker compose -f docker-compose.prod.yaml down
        - docker compose -f docker-compose.prod.yaml up -d
        - sleep 10
        - docker compose -f docker-compose.prod.yaml exec -T php php bin/console doctrine:migrations:migrate --no-interaction
        - docker compose -f docker-compose.prod.yaml exec -T php php bin/console cache:clear --env=prod
        - docker compose -f docker-compose.prod.yaml ps
        - curl -f https://api-planner.msolve.it/health
    environment:
        name: production
        url: https://api-planner.msolve.it
    rules:
        - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'
```

Note: `deploy-production`'s rule was already tag-gated — unchanged, just `cd`-prefixed and `docker-compose.prod.yaml` copy path fixed to `backend/docker-compose.prod.yaml`.

- [ ] **Step 3: Make frontend jobs `cd` into `frontend/`, and switch its deploy rule to tag-gated**

Rewrite `/Users/maciejszklarczyk/Projects/planner/frontend/.gitlab-ci.yml`:

```yaml
variables:
  DEPLOY_DIR: '/home/maciej/docker/apps/planner/frontend'
  DOCKER_DRIVER: overlay2
  DOCKER_TLS_CERTDIR: ""
  NEXT_PUBLIC_API_URL: 'https://api-planner.msolve.it'

stages:
  - quality
  - docker-build
  - deploy

quality-checks:
  stage: quality
  image: node:20-alpine
  tags:
    - docker
  script:
    - cd frontend
    - npm ci
    - npm run lint
    - npx tsc --noEmit
    - npm run test
    - npm run format:check

docker-build:
  stage: docker-build
  image: docker:latest
  tags:
    - docker
  script:
    - cd frontend
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    - docker build --build-arg NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL -t $CI_REGISTRY_IMAGE:latest -t $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA .
    - docker push $CI_REGISTRY_IMAGE:latest
    - docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA

deploy-production:
  stage: deploy
  image: docker:latest
  tags:
    - docker
  before_script:
    - apk add --no-cache docker-compose
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
  script:
    - cd frontend
    - cd $DEPLOY_DIR
    - cp $CI_PROJECT_DIR/frontend/docker-compose.prod.yaml $DEPLOY_DIR/
    - docker compose -f docker-compose.prod.yaml pull
    - docker compose -f docker-compose.prod.yaml down
    - docker compose -f docker-compose.prod.yaml up -d
    - sleep 5
    - docker compose -f docker-compose.prod.yaml ps
  environment:
    name: production
    url: https://planner.msolve.it
  rules:
    - if: '$CI_COMMIT_TAG =~ /^v\d+\.\d+\.\d+$/'
```

Changes from the original: removed `only: [main]` from all 3 jobs (build/test now always run, per spec decision #4); `deploy-production` now uses the same tag rule as backend instead of `only: main`; `docker-build` also loses `only: main` since it's no longer path- or branch-gated — it always builds (matches "zawsze oba pipeline" decision). `docker-compose.prod.yaml` copy path fixed to `frontend/docker-compose.prod.yaml`.

- [ ] **Step 4: Validate YAML syntax locally**

Run:
```bash
python3 -c "import yaml; yaml.safe_load(open('.gitlab-ci.yml'))"
python3 -c "import yaml; yaml.safe_load(open('backend/.gitlab-ci.yml'))"
python3 -c "import yaml; yaml.safe_load(open('frontend/.gitlab-ci.yml'))"
```
Expected: no output, exit 0 for all three (valid YAML). This doesn't validate GitLab-specific semantics (job graph, `include:` resolution) — full validation requires pushing to GitLab and checking the CI Lint page, which is out of scope for local execution.

- [ ] **Step 5: Commit**

```bash
git add .gitlab-ci.yml backend/.gitlab-ci.yml frontend/.gitlab-ci.yml
git commit -m "ci: unify pipelines under root include, gate deploys on tags"
```
Expected: commit succeeds.

---

### Task 4: Fix `herald/herald.sh` for a single root repo

**Files:**
- Modify: `/Users/maciejszklarczyk/Projects/planner/herald/herald.sh:52-100` (functions `dirty` and `cleanup`)

**Interfaces:**
- Consumes: `$ROOT` variable already computed at the top of the script (line 6), now equal to `/Users/maciejszklarczyk/Projects/planner` which is itself the git repo root.
- Produces: `dirty` and `cleanup` commands that report/reset the single monorepo instead of 4 subrepos.

- [ ] **Step 1: Rewrite `dirty()` to operate on `$ROOT` directly**

Replace (herald.sh:52-72):
```bash
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
```

with:
```bash
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
```

- [ ] **Step 2: Rewrite `cleanup()` to operate on `$ROOT` directly**

Replace (herald.sh:74-100):
```bash
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
```

with:
```bash
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
```

- [ ] **Step 3: Manually verify `dirty` against the current repo state**

Run: `bash herald/herald.sh dirty`
Expected: prints `✔ planner (<branch>) — clean` if Task 2/3 commits are all committed, or `✖ planner (<branch>)` with an uncommitted file count otherwise (e.g. right after Task 3 Step 1-3, before the Task 3 Step 5 commit).

- [ ] **Step 4: Commit**

```bash
git add herald/herald.sh
git commit -m "fix(herald): dirty/cleanup operate on single monorepo root"
```
Expected: commit succeeds.

---

### Task 5: End-to-end verification

**Files:** none (verification only).

- [ ] **Step 1: Verify flat structure is intact**

Run: `ls /Users/maciejszklarczyk/Projects/planner`
Expected: `CLAUDE.md  backend  bruno  docs  frontend  herald` (plus `.git`, `.idea`, `.claude`, `.DS_Store`).

- [ ] **Step 2: Verify herald.sh dev workflow still works**

Run:
```bash
bash herald/herald.sh up
bash herald/herald.sh status
bash herald/herald.sh health
```
Expected: containers start, `status` lists them running, `health` reports `✔ backend` and `✔ frontend` reachable (HTTP 2xx-4xx).

- [ ] **Step 3: Verify backend test suite still runs from the monorepo**

Run: `bash herald/herald.sh test`
Expected: PHPUnit runs and exits 0 (or with the same pass/fail state as before migration — this step isn't about fixing pre-existing failures, only confirming the path plumbing still works).

- [ ] **Step 4: Tear down**

Run: `bash herald/herald.sh down`
Expected: containers stop and are removed.

- [ ] **Step 5: Final `git log` sanity check across all 4 merged histories**

Run:
```bash
git log --oneline --follow -- frontend/package.json | wc -l
git log --oneline --follow -- herald/herald.sh | wc -l
git log --oneline --follow -- bruno/*.json 2>/dev/null | wc -l
```
Expected: each prints a non-zero count matching (or close to) the original repos' commit counts for those files — confirms history preservation held for all 4 subprojects, not just backend (checked in Task 2).
