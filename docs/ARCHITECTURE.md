# MosChat Architecture

**Product:** MosChat  
**Domain:** moschat.online  
**Stack:** PHP 8.2+, MySQL 8+, HTML/CSS/JS (no React/Vue/Laravel/Symfony)

## Product intent

MosChat is a multi-tenant SaaS inbox + light CRM for SMB: website chat → unified Inbox → CRM client → assignment, tags, notes, deals.

Future channels (Telegram, VK, WhatsApp, Email) plug in via a shared Channel interface without rewriting Inbox/CRM.

## High-level topology

```
Browser (app.moschat.online)
  → public/index.php (router)
  → Controllers / Modules
  → Services
  → Models / Database

Website visitor
  → widget.js (Shadow DOM)
  → /api/widget/* + SSE /realtime/widget

Operator app
  → /api/internal/* + SSE /realtime/app

Integrations
  → /api/v1/* (Bearer API tokens)
  → Webhooks outbound (signed HMAC)
```

## Directory layout

```
/app
  /Core          # Router, Request, Response, DB, Auth, ACL, Session, CSRF, RateLimit
  /Controllers   # HTTP controllers (web + internal API)
  /Middleware
  /Models
  /Services      # Domain services (Conversation, Client, Billing, FeatureGate…)
  /Channels      # ChannelInterface + WebsiteChannel (+ stubs)
  /Support
  /Views         # PHP templates
/api             # Public REST API v1 entry (thin, delegates to modules)
/modules         # Feature modules (Auth, Inbox, Crm, Widget, Billing…)
/config
/public          # Document root
/widget          # Widget source (built/copied to public)
/routes
/database
/storage
/docs
```

## Request flow

1. `public/index.php` boots autoloader, config, session.
2. Router matches path → middleware stack → controller/action.
3. Controllers validate input, call Services, return View or JSON.
4. Services own business rules and emit domain events.
5. Event bus fans out to: realtime publisher, webhooks, notifications, audit log.

## Multi-tenancy

- Tenant key: `company_id` (workspace).
- Every tenant-owned row includes `company_id`.
- Active company stored in session (`company_id`) after membership check.
- Authorization always: membership → role permissions → resource.company_id match.
- Never trust UI alone; every query scoped by company.

## Roles & permissions

Roles: `owner`, `admin`, `supervisor`, `agent`.

Permissions are string keys (`inbox.read`, `clients.write`, `settings.manage`, …) stored in DB and checked via `Permission::can($user, $company, $key)`.

Role → permission map is data-driven (seeded), not scattered `if ($role === 'admin')`.

## Channel abstraction

```php
interface ChannelInterface {
    public function key(): string;
    public function receiveMessage(array $payload): NormalizedMessage;
    public function sendMessage(Conversation $c, OutboundMessage $m): SendResult;
    public function normalizeVisitor(array $payload): VisitorIdentity;
    public function createConversation(Company $co, array $ctx): Conversation;
}
```

MVP implements `WebsiteChannel` only. Other channels are stubs registering in `ChannelRegistry`.

Unified model: Conversation → Messages (any channel) → Client/Visitor.

## Realtime

Primary: **SSE** (Server-Sent Events) — reliable with PHP-FPM + Redis pub/sub (or DB-backed queue fallback).

Channels:
- `/realtime/app` — operator (auth session)
- `/realtime/widget` — visitor (site public key + visitor token)

Events: `message.created`, `conversation.updated`, `typing`, `presence`, `read`, `assignment`.

Fallback: short-interval long-poll (`/realtime/poll`) if SSE drops; exponential reconnect on client.

Optional later: dedicated WebSocket worker (Ratchet) behind same event publisher — clients stay compatible via event schema.

## Widget

- Single script: `https://moschat.online/widget.js`
- Attr: `data-site="{public_key}"`
- Shadow DOM isolation
- Global API: `MosChat.open()`, `close()`, `show()`, `hide()`, `setUser()`, `setAttributes()`, `on()`, `isOpen()`, `startConversation()`
- Talks to widget API + SSE only (never secret_key)

## API layers

| Layer | Auth | Purpose |
|-------|------|---------|
| Web UI | Session + CSRF | HTML app |
| Internal JSON | Session + CSRF | SPA-like app fetches |
| Widget API | public_key + visitor token | Visitor chat |
| Public REST `/api/v1` | Bearer `sk_live_…` | Integrations |

Consistent JSON envelope:

```json
{ "success": true, "data": {}, "meta": { "request_id": "…" } }
```

## Billing / feature gating

Module `Billing` owns plans FREE / PRO.

Central gate: `FeatureGate::can($company, 'webhooks')` — used by services, not templates.

Payment provider is pluggable later; schema already has subscriptions + payment_history.

## Security principles

- Prepared statements only
- CSRF on state-changing web/internal routes
- password_hash / password_verify
- Rate limits on auth, widget, API
- API tokens: prefix + bcrypt/argon hash of secret
- Webhook HMAC-SHA256 signatures
- Uploads: MIME allowlist, size limits, random storage names
- Audit log for sensitive actions

## Deployment target

- Nginx + PHP-FPM 8.2+
- MySQL 8
- Redis recommended (sessions optional, realtime pub/sub, rate limits)
- Document root: `public/`
- Domain: `moschat.online` (app + API + widget same origin or `app.` subdomain later)

## Non-goals (MVP)

- Marketing landing
- Telegram/VK/WhatsApp live connectors
- Real payment checkout
- AI assistants
- System admin panel (schema reserved only)
