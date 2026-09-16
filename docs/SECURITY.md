# MosChat Security

## Threat model (summary)

| Asset | Risk | Control |
|-------|------|---------|
| Tenant data | Cross-tenant read/write | company_id scoping + membership ACL on every query |
| Passwords | Theft | password_hash (PASSWORD_DEFAULT), reset tokens hashed |
| Sessions | Hijack | regenerate on login, secure/httponly/samesite cookies |
| CSRF | State change | token on mutating web/internal requests |
| XSS | Stored/reflected | escape on output, CSP-friendly widget Shadow DOM |
| SQLi | Injection | PDO prepared statements only |
| API secrets | Leak | prefix + hash storage; secret shown once |
| Uploads | Malware/XSS | MIME allowlist, size cap, no executable serve |
| Brute force | Account takeover | rate limit + lockout counters |
| Webhooks | Spoofing | HMAC signature + secret |
| Widget abuse | Spam | rate limit per visitor/IP/site |

## Authentication

- Email + password
- Optional future: 2FA, OAuth (hooks reserved, not implemented)
- Email verification required before full access (configurable soft-force in MVP)
- Password reset: single-use hashed tokens, short TTL

## Authorization

1. Authenticated?
2. Member of active company?
3. Permission key allowed for role?
4. Resource.company_id === active company?

FeatureGate (plan) is separate from ACL and checked in services.

## Session policy

- Name: `moschat_session`
- `HttpOnly`, `Secure` (prod), `SameSite=Lax`
- Idle timeout + absolute lifetime
- Store: files or Redis

## CSRF

- Synchronizer token in session
- Meta tag + `X-CSRF-Token` for fetch
- Exempt: public API bearer, widget endpoints (use site key + visitor token instead)

## Rate limiting

Keyed by IP / user / visitor / API token. Storage: Redis or DB table `rate_limits`.

## Data isolation tests (manual checklist)

- User A cannot fetch conversation ID from company B by ID enumeration
- API token of A cannot list B clients
- Widget public key of A cannot post into B site

## Secrets

- `.env` never committed
- `APP_KEY` for signing visitor tokens
- Site `secret_key` never sent to browser
- Webhook secrets hashed at rest when possible; signing key derived securely

## Audit

Log: login/logout, invites, role changes, settings, token create/revoke, webhook create, subscription change, destructive deletes.

## Privacy

- Hash IPs for visitors where feasible
- Widget privacy consent when enabled
- Client/conversation delete for operators with permission
- Export endpoint planned for PRO (structure ready)

## Headers (prod)

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY` (app); widget embeddable separately
- `Referrer-Policy: strict-origin-when-cross-origin`
- Strict CSP on app origin (iterate carefully with inline needs)

## File uploads

Allow: jpeg, png, gif, webp, pdf, plain text, common office (optional).  
Max size: 10MB FREE / 25MB PRO. Store outside public with authenticated download route.
