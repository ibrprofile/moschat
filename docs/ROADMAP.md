# MosChat Roadmap

Domain: **moschat.online** · Product: **MosChat**

## Stage 1 — Architecture ✅ (this doc set)
ARCHITECTURE, DATABASE, API, DESIGN_SYSTEM, ROADMAP, SECURITY

## Stage 2 — Database + skeleton
- Migrations for all MVP tables
- Seeds: plans, roles, permissions
- PHP core: Router, DB, Config, Autoload
- `.env.example`, README

## Stage 3 — Auth + company + roles
- Register / login / logout / email verify / password reset
- Onboarding company creation
- Membership, roles, permission middleware
- CSRF, rate limit, session hardening

## Stage 4 — App shell + design system
- CSS tokens + components
- Sidebar shell, routing for all `/app/*` pages
- Profile, company switcher
- Onboarding UI (4 steps)

## Stage 5 — Website widget
- `widget.js` Shadow DOM
- Bootstrap + contact + messages API
- MosChat JS API
- Widget settings + live preview

## Stage 6 — Realtime
- SSE publisher (Redis or DB fallback)
- App + widget subscribers
- Typing, presence, reconnect + long-poll fallback

## Stage 7 — Inbox
- Conversation list filters/views
- Thread + composer + notes
- Assignment, tags, status
- Attachments, saved replies (`/shortcut`)

## Stage 8 — CRM
- Clients list/detail
- Auto-create / link on identify
- Tags, categories, notes, tasks, deals (simple pipeline)
- Merge clients

## Stage 9 — Employees + departments
- Invite flow
- Departments CRUD + members
- Presence online/offline

## Stage 10 — Settings
- General, chat, widget, team, CRM, security, privacy, billing, API

## Stage 11 — Public API + webhooks
- `/api/v1` resources
- Token hash storage
- Webhook dispatch + retries + logs

## Stage 12 — In-app documentation
- `/app/docs` developer docs UI

## Stage 13 — Billing gating
- FREE / PRO limits
- FeatureGate everywhere needed
- Subscription records (no live payment)

## Stage 14 — Hardening
- Security pass, N+1 fixes, widget size, UX polish, empty/error states

## MVP acceptance (must all work end-to-end)

Register → company → widget code → visitor message → operator realtime reply → CRM client → assign/tag/note/close → API token call → webhook delivery → FREE vs PRO limits.

## Post-MVP (not now)
Telegram / VK / WhatsApp channels, OAuth/2FA, payment provider, system admin, AI, marketing site.
