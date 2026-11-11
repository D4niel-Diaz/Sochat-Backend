# SORSU TALK Backend API

Laravel 12 REST API for an anonymous one-to-one chat platform: guest sessions, matching,
messages, reports with auto-ban, moderation, and an admin panel. Real-time delivery is handled
by the separate `webscoket` Socket.IO server, which calls this API.

## Stack

- PHP 8.2 (Composer platform is pinned to 8.2 to match the Docker image)
- Laravel 12, Laravel Sanctum (admin tokens)
- PostgreSQL in production (MySQL/MariaDB and SQLite also migrate; tests use SQLite in memory)
- Redis or the database for the cache used by rate limiting

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='a-long-password' php artisan db:seed   # first admin
php artisan serve
php artisan schedule:work      # runs chats:cleanup-stale every 5 minutes
```

There is no default admin password. The seeder does nothing unless `ADMIN_EMAIL` and a 12+
character `ADMIN_PASSWORD` are set.

## Tests

```bash
php artisan test
```

## Authentication

- **Guests:** `POST /api/v1/guest/create` returns a `session_token`; send it as
  `Authorization: Bearer <token>`. Sessions last 24h and can be refreshed.
- **Admins:** `POST /api/v1/admin/login` returns `data.token` (Sanctum). Send it as
  `Authorization: Bearer <token>` to `/api/v1/admin/*`. Tokens expire after
  `SANCTUM_EXPIRATION` minutes (default 720); `POST /api/v1/admin/logout` revokes it.

## API (v1)

| Method | Path | Notes |
|---|---|---|
| POST | `/guest/create` | New anonymous session |
| GET | `/guest/validate` | Read-only session check (used by the WebSocket server) |
| POST | `/guest/refresh` | Extend session 24h |
| POST | `/presence/opt-in` | `{role: tutor\|learner, subject, availability: [0-23...]}`; 409 if already in a chat |
| POST | `/presence/opt-out`, `/presence/heartbeat`, `/presence/disconnect` | |
| GET | `/presence/status` | |
| POST | `/chat/start` | Try to match; returns `matched`, `already_matched` or `waiting` |
| POST | `/chat/end` | `{chat_id}` |
| GET | `/chat/{id}/messages?limit=&cursor=` | Newest page first; `next_cursor` is a `message_id`; includes `chat_status` |
| POST | `/chat/message` | `{chat_id, content}` (max 1000 chars) |
| POST | `/report` | `{chat_id, reason}` (10–500 chars); one report per chat per reporter |
| POST | `/admin/login`, `/admin/logout` | |
| GET | `/admin/metrics`, `/admin/chats`, `/admin/reports?status=all\|pending`, `/admin/banned-guests`, `/admin/flagged-messages` | |
| POST | `/admin/ban`, `/admin/unban` (`{guest_id}`), `/admin/report/resolve` (`{report_id}`) | |
| GET | `/api/health` | DB, Redis (if configured) and queue checks |

Errors are JSON: `{ "success": false, "message": "...", "errors": {...} }`.

## Moderation (`config/moderation.php`)

- `personal_info_patterns` (emails, phone numbers): message **rejected** with 400.
- `flag_patterns` (social handles, profile links): message delivered and **flagged** for admins.
- `banned_words`: masked as whole words. **The shipped list is a placeholder; replace it.**
- `report_threshold` (env `REPORT_THRESHOLD`, default 3): distinct reporters before auto-ban.

## Rate limits (`AppServiceProvider`)

Guest limits are keyed by session token, not IP, because the WebSocket server calls the API
for all users from one IP and campus users share NAT addresses.

## Deployment (Render, Docker)

The Dockerfile installs production dependencies, runs migrations, starts the scheduler in the
background and serves with PHP's built-in server. Set at least: `APP_KEY`, `APP_ENV=production`,
`APP_DEBUG=false`, `DB_*` (Postgres), `CACHE_STORE` (`redis` or `database`), and `FRONTEND_URL`
if the frontend is not on a `sochat*.vercel.app` domain.

**Production server:** `php -S` handles one request at a time. It works for low traffic; for
more, switch the image to php-fpm + nginx or FrankenPHP.
