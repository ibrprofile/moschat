# MosChat API

Base URL: `https://moschat.online`

## Envelopes

Success:
```json
{
  "success": true,
  "data": {},
  "meta": {
    "request_id": "req_…",
    "page": 1,
    "per_page": 25,
    "total": 100
  }
}
```

Error:
```json
{
  "success": false,
  "error": {
    "code": "INVALID_REQUEST",
    "message": "Human readable message",
    "details": {}
  },
  "meta": { "request_id": "req_…" }
}
```

Common codes: `UNAUTHORIZED`, `FORBIDDEN`, `NOT_FOUND`, `VALIDATION_ERROR`, `RATE_LIMITED`, `PLAN_LIMIT`, `CONFLICT`, `SERVER_ERROR`

## Auth modes

### Session (web / internal)
Cookie session + `X-CSRF-Token` header for mutating requests.

### Public REST
`Authorization: Bearer sk_live_{prefix}_{secret}`  
Server looks up by prefix, verifies hash.

### Widget
`X-Site-Key: {public_key}` + `X-Visitor-Token: {visitor_jwt_or_opaque}`

---

## Internal app API (`/api/internal`)

Used by dashboard JS. Session auth.

| Method | Path | Description |
|--------|------|-------------|
| GET | /me | Current user + companies |
| POST | /auth/login | Login |
| POST | /auth/register | Register |
| POST | /auth/logout | Logout |
| POST | /auth/forgot-password | Request reset |
| POST | /auth/reset-password | Reset |
| GET/PATCH | /profile | Profile |
| POST | /onboarding/* | Onboarding steps |
| GET | /inbox/conversations | List + filters |
| GET | /inbox/conversations/:id | Detail |
| POST | /inbox/conversations/:id/messages | Reply / note |
| PATCH | /inbox/conversations/:id | Status, assign, tags |
| GET | /crm/clients | List |
| GET/PATCH | /crm/clients/:id | Detail |
| POST | /crm/clients/:id/merge | Merge |
| GET | /visitors | Online/recent |
| POST | /visitors/:id/start-chat | Proactive chat |
| CRUD | /employees, /departments, /tags, /saved-replies | |
| GET | /analytics/summary | Metrics |
| CRUD | /settings/*, /sites, /api-tokens, /webhooks | |
| GET | /notifications | |
| POST | /billing/plan | Switch plan (no payment yet) |

Realtime: `GET /realtime/app` (SSE)

---

## Widget API (`/api/widget`)

| Method | Path | Description |
|--------|------|-------------|
| POST | /bootstrap | Config + visitor upsert |
| POST | /messages | Visitor message |
| GET | /messages | History (paginated) |
| POST | /typing | Typing signal |
| POST | /contact | Contact form / identify |
| POST | /upload | Attachment |
| GET | /realtime | SSE |

---

## Public REST API v1 (`/api/v1`)

Bearer API token. Company scoped by token.

### Clients
- `GET /api/v1/clients`
- `POST /api/v1/clients`
- `GET /api/v1/clients/:id`
- `PATCH /api/v1/clients/:id`
- `DELETE /api/v1/clients/:id`

### Conversations
- `GET /api/v1/conversations`
- `GET /api/v1/conversations/:id`
- `PATCH /api/v1/conversations/:id` (status, assignee)

### Messages
- `GET /api/v1/conversations/:id/messages`
- `POST /api/v1/conversations/:id/messages`

### Employees / Departments / Tags / Deals / Sites
Standard REST CRUD with plan gating where applicable.

### Webhooks
- `GET/POST /api/v1/webhooks`
- `GET/PATCH/DELETE /api/v1/webhooks/:id`
- `GET /api/v1/webhooks/:id/deliveries`

## Pagination

Query: `page`, `per_page` (max 100), optional `cursor` for messages.

## Filtering & sorting

Examples: `?status=open&assigned_user_id=1&q=acme&sort=-last_message_at`

## Rate limits

| Surface | Default |
|---------|---------|
| Auth | 10 / min / IP |
| Widget | 60 / min / visitor |
| API v1 FREE | 60 / min |
| API v1 PRO | 600 / min |

Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`

## Webhook delivery

POST JSON to customer URL:

Headers:
- `X-MosChat-Event: message.created`
- `X-MosChat-Signature: sha256=…`
- `X-MosChat-Delivery: del_…`

Body includes `event`, `created_at`, `data`.

Retry: exponential backoff (1m, 5m, 30m, 2h, 12h), max 5 attempts.

## Events

`conversation.created|updated|closed`  
`message.created|updated`  
`client.created|updated`  
`employee.created`  
`deal.created|updated`

## JS Widget API

```js
MosChat.open();
MosChat.close();
MosChat.show();
MosChat.hide();
MosChat.isOpen();
MosChat.startConversation();
MosChat.setUser({ name, email, phone, user_id });
MosChat.setAttributes({ plan: 'pro' });
MosChat.on('ready', fn);
MosChat.on('message', fn);
```

Loaded via:
```html
<script src="https://moschat.online/widget.js" data-site="pk_…"></script>
```
