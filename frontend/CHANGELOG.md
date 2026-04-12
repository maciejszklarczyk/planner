# Changelog

## [1480c7f] — 2026-04-12

- Remove `API_URL` prefix from avatar URL in `CurrentUserEditForm` — backend now returns full S3 public URL
- Remove `API_URL` prefix and `avatarSrc` variable from `NavUser` — use `user.avatar` directly

## [PLF-7] — 2026-04-11

- Add avatar upload and delete functionality (`useUploadAvatar`, `useDeleteAvatar` hooks)
- Add `postFormData` method to API client for multipart form data requests
- Refactor `CurrentUserEditForm`: avatar section with upload/delete, improved layout, password change placeholder
- Refactor `SettingsTabs`: replace Radix Tabs with custom sidebar nav, add Notifications and Logs placeholder sections
