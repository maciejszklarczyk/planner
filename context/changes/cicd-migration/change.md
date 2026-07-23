---
change_id: cicd-migration
title: Migrate CI/CD from GitLab to GitHub Actions (backend + frontend, no deployment)
status: impl_reviewed
created: 2026-07-23
updated: 2026-07-23
archived_at: null
---

## Notes

we need to migrate cicd from gitlab to github, old cicds are in backend and frontend, we totally skipp deployment for now

Cross-project reference: `../car-repair-tracker` already uses GitHub Actions with a custom self-hosted runner (used only for its deploy job, not CI). See `research.md` follow-up section for details — not needed for this migration since deployment is skipped.

Verified live: opened https://github.com/maciejszklarczyk/planner/pull/1 from branch `ci-migration-verify` to exercise both workflows on real GitHub Actions runners. All 7 jobs (backend-ci: composer-audit, lint, php-cs-fixer, phpstan, phpunit, secret-scan; frontend-ci: quality-checks) pass on both push and pull_request events. Impl-review triage fixed F1 (unpinned gitleaks tag), F2 (missing permissions:), F4 (composer-audit consistency), F5 (concurrency group), and F6 (gitleaks false positives on placeholder secrets — added .gitleaks.toml allowlist), all discovered/verified via this real PR run.

Remaining before merge: update GitHub branch protection required-status-checks to the new job names (3.5, still a manual repo-settings action).
