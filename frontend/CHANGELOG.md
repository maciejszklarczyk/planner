# Changelog

## 2026-08-01

- [2b721ee] fix(friends): use native disabled prop on parked dropdown menu items
- [e23a36f] fix(sidebar): guard NavUser initials against null user.name

## 2026-07-23

- [10c3e04] docs(context): add cicd-rework plan and foundation notes; refresh package-lock.json

## 2026-07-12

- [1440ee1] feat(settings): redesign settings page as unified card layout, add IDE gitignore entries
- [cdb7d4c] chore: bump displayed version to v0.0.4

## 2026-05-03

- [0ae7716] feat(events): replace mock data with real API query and add skeleton loading

## 2026-04-29

- [cda5dc8] style(responsive): make settings nav horizontal on mobile, fix card layouts to stack on mobile

## 2026-04-28

- [0b7289a] docs(claude): trim CLAUDE.md and add api.patch, auth query key, missing conventions

## 2026-04-25

- [21a3dff] chore: bump version to v0.0.3, update deps, add frontend docker-compose
- [572e584] feat(events): add new event page and link "Nowe wydarzenie" button

## 2026-04-22

- [dc1c6a3] chore: exclude tooling dirs and docs from Docker build context
- [f41a730] chore: ignore .claude/ and .superpowers/ personal tooling dirs

- [defa1f2] feat(events): add EventsView component with mock data and page metadata

## [b9a4180] — 2026-04-21

- Link NavUser Notifications dropdown item to `/settings?tab=notifications` — reads `tab` search param in `SettingsTabs` for initial active tab; wrap in `Suspense` for Next.js App Router compatibility

## [fde7936] — 2026-04-21

- Apply shadcn/ui design rule fixes: `FieldGroup`/`FieldError` in forms, `Skeleton` for loading states, `Badge` for status, `data-icon` on icons, remove raw colors and `space-y-*`

## [1480c7f] — 2026-04-12

- Remove `API_URL` prefix from avatar URL in `CurrentUserEditForm` — backend now returns full S3 public URL
- Remove `API_URL` prefix and `avatarSrc` variable from `NavUser` — use `user.avatar` directly

## [PLF-7] — 2026-04-11

- Add avatar upload and delete functionality (`useUploadAvatar`, `useDeleteAvatar` hooks)
- Add `postFormData` method to API client for multipart form data requests
- Refactor `CurrentUserEditForm`: avatar section with upload/delete, improved layout, password change placeholder
- Refactor `SettingsTabs`: replace Radix Tabs with custom sidebar nav, add Notifications and Logs placeholder sections
