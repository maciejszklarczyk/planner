# Changelog

## 2026-04-11 — strict-types

- Add `declare_strict_types` rule to `.php-cs-fixer.dist.php`
- Add `declare(strict_types=1)` to all files missing it (CS Fixer)
- Fix GD functions in `UserAvatarController` — add `GdImage` instance checks after `imagecreatefromX()` and `imagecreatetruecolor()`
- Fix `parse_url()` nullable chain in `UserAvatarController::delete()`
- Fix nullable `getId()` in `UserAvatarController::upload()`
- Fix nullable→non-nullable mismatches in `fromEntity()` methods: `UserListItemDto`, `EditUserDto`, `GroupListItemDto`, `GroupMembershipDto`
- Fix `UserGroupService::addUserToGroup()` — replace missing `$this->isMember()` with `$user->isMemberOf()`
- Fix `UserHasGroup::getRole()` return type — remove unnecessary `?`

## 2026-04-11 — 13114d0

- Add `avatar` field to `User` entity with getter/setter
- Add Doctrine migration adding `avatar` column to `user` table
- Add `UserAvatarController` with `POST /user/avatar` (upload + resize/crop to 256x256 WebP) and `DELETE /user/avatar`
- Register `UserAvatarController` in `services.yaml` with `avatarsDir` and `avatarsPublicPath` parameters
- Add `avatar` field to `UserListItemDto` response
- Expose `avatar` in `AuthController` `/me` response
- Add GD extension (`gd` with JPEG/WebP support) to Dockerfile
