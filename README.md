# SORSU TALK Backend API

An anonymous real-time chat platform backend built with Laravel 11 and MySQL.

## Features

- **Anonymous Chat Sessions**: Create anonymous sessions with UUID-based identification
- **Auto-Matching**: Automatic pairing of waiting users
- **Real-Time Messaging**: Chat functionality with message history
- **Content Moderation**: Automatic profanity filtering and personal information detection
- **User Reporting**: Report system with automatic bans for repeat offenders (3 reports = ban)
- **Admin Panel**: Full moderation dashboard for admins
- **Rate Limiting**: Comprehensive rate limiting to prevent abuse
- **Session Management**: Automatic session expiration (24 hours)
- **IP Tracking**: Logs IP addresses for abuse prevention

## Tech Stack

- **PHP**: 8.2+
- **Framework**: Laravel 11
- **Database**: MySQL
- **Authentication**: Session-based (Laravel Auth)
- **Architecture**: Repository Pattern + Service Layer

## Requirements

- PHP 8.2 or higher
- Composer
- MySQL 8.0 or higher
- Redis (for queue and caching)
- Node.js 18+ (for Reverb WebSocket server)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd sorsu-talk-backend
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Copy environment file**
   ```bash
   cp .env.example .env
   ```

4. **Generate application key**
   ```bash
   php artisan key:generate
   ```

5. **Configure environment variables**
   Edit `.env` and set database credentials:
   ```env
   DB_DATABASE=sorsu_talk
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

6. **Run migrations**
   ```bash
   php artisan migrate
   ```

7. **Create admin account**
   ```bash
   php artisan tinker
   ```
   In tinker:
   ```php
   use App\Models\Admin;
   Admin::create([
       'name' => 'Admin User',
       'email' => 'admin@example.com',
       'password' => bcrypt('your_password'),
       'role' => 'super_admin',
   ]);
   ```

8. **Start the development server**
   ```bash
   php artisan serve
   ```

9. **Start the queue worker (CRITICAL for real-time features)**
   ```bash
   php artisan queue:work redis --sleep=3 --tries=3 --timeout=90
   ```

10. **Start Reverb WebSocket server**
   ```bash
   php artisan reverb:start
   ```

## Production Deployment

### Queue Worker Setup (Required)

The queue worker is **essential** for real-time features to work. Without it:
- WebSocket events (matches, messages, reports) will NOT broadcast
- Background jobs (report processing) will NOT execute

**Using Supervisor (Recommended):**
```bash
# Install Supervisor
sudo apt-get install supervisor

# Copy supervisor configuration
sudo cp supervisor.conf /etc/supervisor/conf.d/sorsu-talk-worker.conf

# Update paths in the config to match your deployment directory

# Start Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sorsu-talk-worker:*

# Check status
sudo supervisorctl status
```

**Manual Queue Worker (Development):**
```bash
php artisan queue:work redis --sleep=3 --tries=3 --timeout=90
```

### Reverb WebSocket Server

Reverb handles real-time WebSocket connections. It must be running for:
- Real-time match notifications
- Instant message delivery
- Typing indicators
- Admin report notifications

**Development:**
```bash
php artisan reverb:start
```

**Production:**
Use systemd or Supervisor to keep Reverb running.

### Environment Configuration

Ensure these are set in `.env`:
```env
# Queue and Cache
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Broadcasting (Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

## API Documentation

Base URL: `http://127.0.0.1:8000/api/v1`

### Authentication

#### Admin Login

```http
POST /api/v1/admin/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "your_password"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "admin_id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "role": "admin"
  }
}
```

**Note:** Session cookie is automatically set. All admin endpoints require this session.

---

### Guest Endpoints

#### Create Guest Session

```http
POST /api/v1/guest/create
```

**Response:**
```json
{
  "success": true,
  "data": {
    "guest_id": "uuid-string",
    "session_token": "64-char-token",
    "expires_at": "2026-01-03T03:20:46.000000Z"
  },
  "message": "Guest session created successfully"
}
```

#### Refresh Guest Session

```http
POST /api/v1/guest/refresh
Authorization: Bearer {session_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "guest_id": "uuid-string",
    "expires_at": "2026-01-03T03:20:46.000000Z"
  },
  "message": "Session refreshed successfully"
}
```

---

### Chat Endpoints

All chat endpoints require `Authorization: Bearer {session_token}` header.

#### Start Chat (Find Match)

```http
POST /api/v1/chat/start
Authorization: Bearer {session_token}
```

**Response (Matched):**
```json
{
  "success": true,
  "data": {
    "chat_id": 1,
    "partner_id": "partner-uuid",
    "status": "matched"
  },
  "message": "Chat started successfully"
}
```

**Response (Waiting):**
```json
{
  "success": true,
  "data": {
    "status": "waiting"
  },
  "message": "Waiting for a match"
}
```

#### End Chat

```http
POST /api/v1/chat/end
Authorization: Bearer {session_token}
Content-Type: application/json

{
  "chat_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Chat ended successfully"
}
```

#### Get Chat Messages

```http
GET /api/v1/chat/{chat_id}/messages?limit=100
Authorization: Bearer {session_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "messages": [
      {
        "message_id": 1,
        "sender": "you",
        "content": "Hello!",
        "created_at": "2026-01-02T03:20:46.000000Z",
        "is_flagged": false
      },
      {
        "message_id": 2,
        "sender": "partner",
        "content": "Hi there!",
        "created_at": "2026-01-02T03:21:00.000000Z",
        "is_flagged": false
      }
    ]
  },
  "message": "Messages retrieved successfully"
}
```

#### Send Message

```http
POST /api/v1/chat/message
Authorization: Bearer {session_token}
Content-Type: application/json

{
  "chat_id": 1,
  "content": "Hello, how are you?"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message_id": 1,
    "content": "Hello, how are you?",
    "created_at": "2026-01-02T03:20:46.000000Z",
    "is_flagged": false
  },
  "message": "Message sent successfully"
}
```

---

### Report Endpoints

#### Submit Report

```http
POST /api/v1/report
Authorization: Bearer {session_token}
Content-Type: application/json

{
  "chat_id": 1,
  "reason": "Inappropriate behavior and harassment"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "report_id": 1,
    "status": "pending",
    "auto_banned": false
  },
  "message": "Report submitted successfully"
}
```

**Note:** Users are automatically banned after 3 reports.

---

### Admin Endpoints

All admin endpoints require admin session from login.

#### Get Metrics

```http
GET /api/v1/admin/metrics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "online_users": 5,
    "active_chats": 2,
    "total_reports": 3,
    "banned_users": 10
  }
}
```

#### Get Active Chats

```http
GET /api/v1/admin/chats
```

#### Get Reports

```http
GET /api/v1/admin/reports?status=all
```

Query params:
- `status`: `all` (default) or `pending`

#### Ban Guest

```http
POST /api/v1/admin/ban
Content-Type: application/json

{
  "guest_id": "uuid-string"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Guest banned successfully"
}
```

#### Unban Guest

```http
POST /api/v1/admin/unban
Content-Type: application/json

{
  "guest_id": "uuid-string"
}
```

#### Resolve Report

```http
POST /api/v1/admin/report/resolve
Content-Type: application/json

{
  "report_id": 1
}
```

#### Get Banned Guests

```http
GET /api/v1/admin/banned-guests
```

#### Get Flagged Messages

```http
GET /api/v1/admin/flagged-messages
```

---

## Database Schema

### guests

| Column | Type | Description |
|--------|------|-------------|
| guest_id | string (UUID) | Primary key |
| session_token | string (64) | Auth token |
| ip_address | string | Guest IP |
| status | enum | waiting, active, banned |
| expires_at | timestamp | Session expiry |
| created_at | timestamp |  |
| updated_at | timestamp |  |

### admins

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Admin name |
| email | string | Unique email |
| password | string | Hashed password |
| role | enum | admin, super_admin |
| remember_token | string |  |
| created_at | timestamp |  |
| updated_at | timestamp |  |

### chats

| Column | Type | Description |
|--------|------|-------------|
| chat_id | bigint | Primary key |
| guest_id_1 | string | First guest UUID |
| guest_id_2 | string | Second guest UUID |
| started_at | timestamp | Chat start time |
| ended_at | timestamp | Chat end time (nullable) |
| status | enum | active, ended |
| ended_by | string | Who ended the chat |
| created_at | timestamp |  |
| updated_at | timestamp |  |

### messages

| Column | Type | Description |
|--------|------|-------------|
| message_id | bigint | Primary key |
| chat_id | bigint | FK to chats |
| sender_guest_id | string | Sender UUID |
| content | text | Message content |
| is_flagged | boolean | PII detected |
| created_at | timestamp |  |
| updated_at | timestamp |  |

### reports

| Column | Type | Description |
|--------|------|-------------|
| report_id | bigint | Primary key |
| chat_id | bigint | FK to chats |
| reporter_guest_id | string | Reporter UUID |
| reported_guest_id | string | Reported UUID |
| reason | text | Report reason |
| ip_address | string | Reporter IP |
| status | enum | pending, resolved |
| created_at | timestamp |  |
| updated_at | timestamp |  |

---

## Architecture

### Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── ChatController.php
│   │   ├── GuestController.php
│   │   └── ReportController.php
│   ├── Middleware/
│   │   ├── AuthAdmin.php
│   │   ├── AuthGuest.php
│   │   ├── ContentFilter.php
│   │   ├── RateLimitByIP.php
│   │   └── BlockAbusiveIP.php
│   └── Requests/
│       └── BanGuestRequest.php
├── Models/
│   ├── Admin.php
│   ├── Chat.php
│   ├── Guest.php
│   ├── Message.php
│   └── Report.php
├── Repositories/
│   ├── ChatRepository.php
│   ├── GuestRepository.php
│   ├── MessageRepository.php
│   └── ReportRepository.php
└── Services/
    ├── AdminService.php
    ├── ChatService.php
    ├── MessageService.php
    └── ReportService.php
```

### Design Patterns

- **Repository Pattern**: Data access abstraction
- **Service Layer**: Business logic separation
- **Middleware**: Cross-cutting concerns (auth, rate limiting, content filtering)

### Flow

1. **Guest Creation**: `GuestController` → `GuestRepository` → `Guest` model
2. **Chat Matching**: `ChatController` → `ChatService` → `ChatRepository`
3. **Messaging**: `ChatController` → `MessageService` → `MessageRepository`
4. **Reporting**: `ReportController` → `ReportService` → `ReportRepository`
5. **Admin Actions**: `AdminController` → `AdminService` → Repositories

---

## Configuration

### Rate Limiting

Edit `config/throttle.php` or define in routes:

```php
->middleware('throttle:session-create')  // Limit guest creation
->middleware('throttle:send-message')    // Limit message sending
->middleware('throttle:report-submit')   // Limit report submissions
```

### Content Moderation

Add banned words and PII patterns in `config/moderation.php`:

```php
return [
    'banned_words' => ['spam', 'abuse'],
    'personal_info_patterns' => [
        '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/',  // Phone numbers
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',  // Emails
    ],
];
```

---

## Testing with Postman

### 1. Admin Login

```
POST http://127.0.0.1:8000/api/v1/admin/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "your_password"
}
```

**Important:** Enable cookie handling in Postman Settings → Cookies

### 2. Create Guest

```
POST http://127.0.0.1:8000/api/v1/guest/create
```

Save the `session_token` from response.

### 3. Start Chat

```
POST http://127.0.0.1:8000/api/v1/chat/start
Authorization: Bearer {session_token}
```

### 4. Send Message

```
POST http://127.0.0.1:8000/api/v1/chat/message
Authorization: Bearer {session_token}
Content-Type: application/json

{
  "chat_id": 1,
  "content": "Hello!"
}
```

### 5. Ban Guest (Admin)

```
POST http://127.0.0.1:8000/api/v1/admin/ban
Content-Type: application/json

{
  "guest_id": "uuid-string"
}
```

---

## Security Features

- **Session-based Authentication**: Secure admin and guest sessions
- **Rate Limiting**: Prevents abuse and spam
- **Content Filtering**: Automatic removal of banned words
- **PII Detection**: Flags messages containing personal information
- **IP Tracking**: Logs IP addresses for abuse prevention
- **Auto-Ban**: Users banned after 3 reports
- **Session Expiration**: Guest sessions expire after 24 hours

---

## Error Responses

All endpoints return consistent error format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error"]
  }
}
```

**HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## License

Daniel Diaz
