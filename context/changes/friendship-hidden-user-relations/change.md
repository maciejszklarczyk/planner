---
change_id: friendship-hidden-user-relations
title: Apply the hidden-user rule to existing friend requests and friendships
status: new
created: 2026-11-04
updated: 2026-11-04
archived_at: null
---

## Notes

Follow-up from impl-review F2 of `testing-business-rule-correctness` (2026-11-04).

Since `c54d63e`, nobody can *send* a friend request to an `inactive`/`blocked`/`deleted`
user (`UserStatusEnum::hiddenFromOtherUsers()`, answered as `USER_NOT_FOUND`). Existing
relations are not covered:

- the addressee can still **accept** a pending request sent by a user who was hidden
  afterwards (`FriendshipService::acceptRequest`);
- hidden users still appear in **pending lists** (`listPending`) and **friend lists**
  (`listFriends`).

Product decisions needed before planning: hide vs. show greyed out; what happens to
existing friendships and pending requests when an account is blocked (keep, hide,
auto-cancel); whether this applies to `inactive` the same as to `blocked`.
