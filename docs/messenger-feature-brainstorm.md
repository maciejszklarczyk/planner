# Symfony Messenger — feature brainstorm

## Context

Goal: learn Symfony Messenger (messages + queues) by implementing a real feature in this project.

Redis already in stack. `symfony/messenger` not yet in `composer.json` — needs install.

---

## Option A — Async invitation email (recommended first)

**Problem:** `InvitationMailer::sendInvitation()` runs synchronously in HTTP request. Slow SMTP = admin waits.

**Flow:**
1. Controller dispatches `SendInvitationEmailMessage(email, token)`
2. Handler calls `InvitationMailer`
3. Email sent in background worker — HTTP response returns immediately

**Teaches:** Message class, MessageHandler, transport config, `messenger:consume` worker.

**Scope:** small, focused, fixes a real bottleneck.

---

## Option B — Async activity logging (follow-up, broader scope)

**Problem:** `UserActivityLog` entity exists but nothing writes to it — dormant.

**Flow:**
1. Key controllers dispatch `LogUserActivityMessage(userId, eventType)`
2. Handler persists `UserActivityLog` record
3. No DB write blocks the HTTP response

**Teaches:** everything from Option A + fan-out pattern (one action dispatched from many places).

**Scope:** medium — touches multiple controllers.

---

## Plan

1. Start with **Option A**
2. Install `symfony/messenger`
3. Configure Redis transport in `messenger.yaml`
4. Create `SendInvitationEmailMessage` + handler
5. Dispatch from `InvitationController`
6. Run worker: `php bin/console messenger:consume async`
7. Follow up with **Option B**
