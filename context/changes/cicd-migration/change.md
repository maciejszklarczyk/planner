---
change_id: cicd-migration
title: Migrate CI/CD from GitLab to GitHub Actions (backend + frontend, no deployment)
status: implemented
created: 2026-07-23
updated: 2026-07-23
archived_at: null
---

## Notes

we need to migrate cicd from gitlab to github, old cicds are in backend and frontend, we totally skipp deployment for now

Cross-project reference: `../car-repair-tracker` already uses GitHub Actions with a custom self-hosted runner (used only for its deploy job, not CI). See `research.md` follow-up section for details — not needed for this migration since deployment is skipped.
