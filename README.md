# MosChat

SaaS inbox + CRM for SMB. Domain: [moschat.online](https://moschat.online)

## Stack

PHP 8.2+, MySQL 8+, vanilla HTML/CSS/JS. No Laravel/React/Vue.

## Setup

```bash
cp .env.example .env
# edit DB credentials and APP_KEY
composer dump-autoload
mysql -u root -p -e "CREATE DATABASE moschat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php bin/migrate.php
php -S localhost:8080 -t public
```

Document root must be `public/`.

## Docs

See `/docs`: ARCHITECTURE, DATABASE, API, DESIGN_SYSTEM, ROADMAP, SECURITY.

## Widget

```html
<script src="https://moschat.online/widget.js" data-site="YOUR_PUBLIC_KEY"></script>
```
