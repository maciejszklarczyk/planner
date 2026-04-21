# Changelog

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
