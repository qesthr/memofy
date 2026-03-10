<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About BukSU Memofy

BukSU Memofy is a department memo management system built with Laravel and MongoDB. It provides a comprehensive platform for managing departmental communications, memos, and notifications.

### Features

- **Memo Management**: Create, send, archive, and track memos
- **Role-Based Access Control**: Admin, Secretary, and Faculty roles with granular permissions
- **Google Integration**: OAuth, Calendar, Drive, and Analytics APIs
- **Email Notifications**: SMTP-based email notifications for memo activities
- **Calendar Integration**: Schedule memos and events with Google Calendar sync

---

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd server
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure `.env`** with your database and API credentials

5. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

---

## Database Seeders

### Quick Start - Seed All Data

Run all seeders in the correct order:

```bash
php artisan db:seed
```

This will run:
1. `DepartmentTableSeeder` - Creates departments
2. `UsersTableSeeder` - Creates test users
3. `RBACSeeder` - Creates roles and permissions

### Individual Seeders

#### 1. Departments Seeder

Creates 4 departments:
- Food Technology (FT)
- Automotive Technology (AT)
- Electronics Technology (ET)
- Information Technology/EMC (IT)

```bash
php artisan db:seed --class=DepartmentTableSeeder
```

#### 2. Users Seeder

Creates 9 test users (3 of each role):

| Role | Email | Password |
|------|-------|----------|
| Admin | Admin1@buksu.edu.ph to Admin3@buksu.edu.ph | Admin123! |
| Secretary | Secretary1@buksu.edu.ph to Secretary3@buksu.edu.ph | Secretary123! |
| Faculty | Faculty1@buksu.edu.ph to Faculty3@buksu.edu.ph | Faculty123! |

```bash
php artisan db:seed --class=UsersTableSeeder
```

#### 3. RBAC (Roles & Permissions) Seeder

Creates roles and permissions:
- **Admin**: Full system access
- **Secretary**: Department management, memo creation, faculty management
- **Faculty**: View memos, acknowledge memos, read-only calendar

```bash
php artisan db:seed --class=RBACSeeder
```

#### 4. Memo Seeder

Creates random memos for testing. Uses custom artisan command for flexibility.

**Basic usage (100 memos):**
```bash
php artisan memos:seed
```

**Custom count:**
```bash
php artisan memos:seed --count=150
```

**Clear existing memos first:**
```bash
php artisan memos:seed --count=200 --truncate
```

**Alternative via db:seed:**
```bash
php artisan db:seed --class=MemoSeeder
```

**Memo Status Distribution:**
| Status | Percentage | Description |
|--------|------------|-------------|
| `pending_approval` | 20% | For Review |
| `sent` | 60% | Approved/Disseminated |
| `read` | 20% | Acknowledged |

**Note:** Draft status is excluded. All memos have realistic content with randomized subjects, messages, priorities, and recipients.

---

## Custom Artisan Commands

### Server & Status Commands

#### Start server with service status check
```bash
php artisan serve:status
```
Shows status of all configured APIs before starting the server.

#### Check service status only
```bash
php artisan services:check
```
Displays connection status for:
- Database (MongoDB)
- Google OAuth
- Google Drive API
- Google Calendar API
- Google Analytics API
- SMTP Mail
- reCAPTCHA
- Frontend URL

### Memo Commands

#### Seed random memos
```bash
php artisan memos:seed [--count=NUMBER] [--truncate]
```

#### Send scheduled memos
```bash
php artisan memos:send-scheduled
```
Sends memos that have reached their scheduled send time.

---

## Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter MemoTest
```

### Test Data Setup

For testing, run the seeders in order:

```bash
# Fresh database with all test data
php artisan migrate:fresh --seed

# Or step by step
php artisan migrate:fresh
php artisan db:seed --class=DepartmentTableSeeder
php artisan db:seed --class=UsersTableSeeder
php artisan db:seed --class=RBACSeeder
php artisan memos:seed --count=50
```

### Test User Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | Admin1@buksu.edu.ph | Admin123! |
| Secretary | Secretary1@buksu.edu.ph | Secretary123! |
| Faculty | Faculty1@buksu.edu.ph | Faculty123! |

---

## API Configuration

The following APIs are configured in `.env`:

| Service | Required Env Variables |
|---------|----------------------|
| MongoDB | `MONGODB_URI`, `MONGODB_DATABASE` |
| Google OAuth | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_CALLBACK_URL` |
| Google Drive | `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_FOLDER_ID` |
| Google Calendar | `GOOGLE_CALENDAR_CLIENT_ID`, `GOOGLE_CALENDAR_CLIENT_SECRET`, `GOOGLE_CALENDAR_API_KEY` |
| Google Analytics | `GOOGLE_ANALYTICS_CLIENT_ID`, `GOOGLE_ANALYTICS_CLIENT_SECRET`, `GOOGLE_ANALYTICS_PROPERTY_ID` |
| SMTP Mail | `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` |
| reCAPTCHA | `RECAPTCHA_SECRET`, `RECAPTCHA_SITE_KEY` |

---

## Development

### Start Development Server

```bash
# With service status check
php artisan serve:status

# Standard Laravel serve
php artisan serve
```

### Queue Processing

For scheduled memos and background jobs:

```bash
php artisan queue:work
```

### Schedule Runner

For cron-based scheduled tasks:

```bash
php artisan schedule:run
```

Or add to crontab:
```
* * * * * cd /path-to-project/server && php artisan schedule:run >> /dev/null 2>&1
```

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
