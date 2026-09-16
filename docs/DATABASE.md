# MosChat Database

MySQL 8+, InnoDB, utf8mb4. All tenant tables include `company_id` and indexed accordingly.

IDs: `BIGINT UNSIGNED AUTO_INCREMENT` unless noted. Public keys: opaque random strings.

## ER overview

```
users ──< company_members >── companies
companies ──< sites, departments, clients, conversations, …
clients ──< conversations ──< messages
visitors ──? clients (linked when contact known)
conversations.assigned_user_id / assigned_department_id
```

## Core identity

### users
| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| email | VARCHAR(255) UNIQUE | |
| password_hash | VARCHAR(255) | |
| name | VARCHAR(120) | |
| avatar_path | VARCHAR(512) NULL | |
| timezone | VARCHAR(64) | default UTC |
| locale | VARCHAR(16) | ru / en |
| email_verified_at | DATETIME NULL | |
| status | ENUM('active','disabled') | |
| last_login_at | DATETIME NULL | |
| created_at / updated_at | DATETIME | |

### password_resets
token hash, user_id, expires_at, used_at

### email_verifications
token hash, user_id, expires_at

### login_attempts
ip, email, attempted_at (brute-force)

### sessions
id (varchar), user_id, ip, user_agent, payload, last_activity (if DB sessions)

## Tenancy & ACL

### companies
id, name, slug UNIQUE, website, timezone, locale, logo_path, onboarding_step, onboarding_completed_at, created_at, updated_at

### company_members
id, company_id, user_id, role_id, status ENUM('active','invited','disabled'), presence ENUM('online','offline','away'), last_seen_at, created_at  
UNIQUE(company_id, user_id)

### roles
id, company_id NULL (NULL = system role), key, name  
System keys: owner, admin, supervisor, agent

### permissions
id, key UNIQUE, description

### role_permissions
role_id, permission_id

### invitations
id, company_id, email, role_id, token_hash, invited_by, expires_at, accepted_at, created_at

## Sites & widget

### sites
id, company_id, name, domain, public_key UNIQUE, secret_key_hash, secret_key_prefix, is_active, created_at

### site_widget_settings
site_id PK/FK, primary_color, position, launcher_style, company_display_name, welcome_message, offline_message, collect_name/email/phone BOOL, show_departments BOOL, privacy_consent_required BOOL, privacy_text TEXT, logo_path, show_branding BOOL

## Visitors & presence

### visitors
id, company_id, site_id, visitor_key UNIQUE(company), client_id NULL, ip_hash, user_agent, language, timezone, referrer, landing_page, device, browser, country, city, first_seen_at, last_seen_at, page_views INT

### visitor_sessions
id, visitor_id, session_key, current_url, started_at, last_seen_at, ended_at NULL

## CRM

### clients
id, company_id, name, email, phone, company_name, avatar_path, status, category_id NULL, responsible_user_id NULL, department_id NULL, source VARCHAR(64), first_contact_at, last_contact_at, conversations_count INT, notes_summary, created_at, updated_at  
INDEX(company_id, email), INDEX(company_id, phone)

### client_categories
id, company_id, name, color, description

### client_custom_fields / client_custom_field_values
definition + values per client

### tags
id, company_id, name, color

### client_tags
client_id, tag_id

### deals
id, company_id, client_id, pipeline_id, stage_id, title, amount DECIMAL(14,2), currency CHAR(3), responsible_user_id, status, created_at, updated_at

### pipelines / pipeline_stages
default pipeline: New → In progress → Won / Lost

### tasks
id, company_id, client_id, conversation_id NULL, assigned_user_id, title, due_at, status, type ENUM('task','reminder'), created_at

## Messaging

### departments
id, company_id, name, description, color, icon, is_default BOOL, created_at

### department_members
department_id, user_id (company member user)

### conversations
id, company_id, site_id NULL, client_id NULL, visitor_id NULL, channel ENUM('website',…) DEFAULT website, status ENUM('new','open','pending','closed'), assigned_user_id NULL, assigned_department_id NULL, subject NULL, last_message_at, last_message_preview, unread_agent_count, unread_visitor_count, closed_at NULL, created_at, updated_at  
INDEX(company_id, status, last_message_at)

### conversation_participants
conversation_id, participant_type ENUM('user','visitor','client'), participant_id, last_read_at

### conversation_tags
conversation_id, tag_id

### messages
id, conversation_id, company_id, channel, sender_type ENUM('visitor','client','agent','system'), sender_id NULL, message_type ENUM('text','image','file','note','system'), body TEXT, metadata JSON, status ENUM('sending','sent','delivered','read','failed'), created_at, updated_at  
INDEX(conversation_id, id)

### message_attachments
id, message_id, company_id, filename, storage_path, mime, size_bytes

### conversation_notes
(optional denorm — notes also as messages with message_type=note)

### saved_replies
id, company_id, shortcut VARCHAR(64), title, body, department_id NULL, created_by

## Notifications & audit

### notifications
id, company_id, user_id, type, title, body, data JSON, read_at NULL, created_at

### audit_logs
id, company_id NULL, user_id NULL, action, entity_type, entity_id, ip, meta JSON, created_at

## API & webhooks

### api_tokens
id, company_id, name, token_prefix, token_hash, scopes JSON, last_used_at, revoked_at, created_by, created_at

### webhooks
id, company_id, url, secret_hash, secret_prefix, events JSON, is_active, created_at

### webhook_deliveries
id, webhook_id, event, payload JSON, status, attempts, next_retry_at, response_code, response_body, created_at

## Billing

### plans
id, key UNIQUE (free, pro), name, price_monthly, currency, features JSON, limits JSON

### subscriptions
id, company_id, plan_id, status ENUM('active','trialing','past_due','cancelled','expired'), trial_ends_at, current_period_end, cancelled_at, created_at

### payment_history
id, company_id, subscription_id, amount, currency, status, provider_ref, created_at

## Settings

### company_settings
company_id, key, value JSON — flexible KV for privacy text, retention, notification prefs

## Indexes (critical)

- All `company_id` foreign lookups
- `conversations (company_id, status, last_message_at)`
- `messages (conversation_id, created_at)`
- `clients (company_id, email)`, `(company_id, phone)`
- `visitors (company_id, last_seen_at)` for online list
- `sites.public_key`
- `api_tokens.token_prefix`

## Soft delete policy

MVP: hard delete with confirmation + audit for clients/conversations; members deactivated (`disabled`) rather than deleted when possible. Cascade rules defined per migration carefully (no accidental wipe of other tenants).
