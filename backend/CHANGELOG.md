# Changelog

## 2026-04-11 — 13114d0

- Add `avatar` field to `User` entity with getter/setter
- Add Doctrine migration adding `avatar` column to `user` table
- Add `UserAvatarController` with `POST /user/avatar` (upload + resize/crop to 256x256 WebP) and `DELETE /user/avatar`
- Register `UserAvatarController` in `services.yaml` with `avatarsDir` and `avatarsPublicPath` parameters
- Add `avatar` field to `UserListItemDto` response
- Expose `avatar` in `AuthController` `/me` response
- Add GD extension (`gd` with JPEG/WebP support) to Dockerfile
