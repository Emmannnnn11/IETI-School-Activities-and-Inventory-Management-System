# IETI School Activities Scheduling and Inventory Management System

Web app for scheduling school activities, approving events, and tracking shared inventory at **IETI College of Science and Technology Inc.**

**Live demo:** [https://ieti-school-activities-and-inventory-management-system.vercel.app](https://ieti-school-activities-and-inventory-management-system.vercel.app)

## About this project

**Emmanuel Alarilla** — solo full-stack developer (capstone / portfolio project).

- Designed and built the Laravel backend, PostgreSQL (Supabase) data layer, and Blade + Bootstrap UI
- Implemented role-based access, event approval workflow, inventory borrowing and returns, exports, and calendar views
- Deployed on **Vercel** with environment-based configuration (no secrets in the repository)

Demo accounts are created in-app via Admin user management; do not commit real passwords or personal data.

## Screenshots

Add captures under [`docs/screenshots/`](docs/screenshots/) (see that folder’s README). Once present, they render here:

| Calendar | Inventory |
| --- | --- |
| ![Events calendar](docs/screenshots/calendar.png) | ![Inventory management](docs/screenshots/inventory.png) |

| Events | Dashboard |
| --- | --- |
| ![Event management](docs/screenshots/events.png) | ![Dashboard](docs/screenshots/dashboard.png) |

Branding reference: ![IETI logo](public/Logo.png)

## Features

### Event management
- **Role-based access:** Admin, College Head, Senior Head, Junior Head, Teacher, Staff, Head Maintenance
- **Event creation** with date, time, location, and inventory requirements
- **Approval workflow:** Admin approves or rejects events with status indicators
- **Calendar view:** FullCalendar with color-coded status

### Inventory management
- **Categories:** Sound system, instruments, sports, furniture, electronics, and more
- **Quantity tracking:** Available vs total; available, maintenance, unavailable
- **Event integration:** Link items to events; returns and damage reporting
- **Category restrictions** per user where configured

### Data safety
- **Soft deletes** on users and inventory items

### UI
- IETI brand colors (yellow, green, black, white)
- Responsive layout for desktop, tablet, and mobile

## Technology stack

| Layer | Tools |
| --- | --- |
| Backend | PHP 8.1+, Laravel 10 |
| Database | PostgreSQL on [Supabase](https://supabase.com) (session pooler) |
| Frontend | Blade, HTML5, CSS3, JavaScript, Bootstrap 5 |
| Calendar | FullCalendar.js |
| Icons | Font Awesome 6 |
| Hosting | Vercel |

## Installation (local)

### Prerequisites
- PHP 8.1+ with `pdo_pgsql`
- [Composer](https://getcomposer.org)
- A Supabase project (or any PostgreSQL 14+ database)

### 1. Clone and install

```bash
git clone https://github.com/Emmannnnn11/IETI-School-Activities-and-Inventory-Management-System.git
cd IETI-School-Activities-and-Inventory-Management-System
composer install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

In Supabase: **Connect → URI → Shared pooler (session, port 5432)**. Map to Laravel:

```env
DB_CONNECTION=pgsql
DB_HOST=aws-0-<region>.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.<your-project-ref>
DB_PASSWORD=<your-database-password>
DB_SCHEMA=public
DB_SSLMODE=require
```

Use the **session pooler** (port 5432), not the transaction pooler (6543), for migrations and normal app use.

### 3. Migrate and run

```bash
php artisan migrate
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

There is no committed database seeder; create an admin user through your database or registration flow, then use **User Management** (`/users`) for demo accounts.

### Windows note

On Windows, ensure `storage` and `bootstrap/cache` are writable by the PHP process (no `chmod` required if you run as your user).

## User roles (summary)

| Role | Highlights |
| --- | --- |
| **admin** | Full access; approve/reject events; users; inventory |
| **college_head / senior_head / junior_head** | Create and edit events; view calendar |
| **staff / Head Maintenance** | Inventory and returns; view events |
| **teacher** | View events per policy |

See code in `app/Models/User.php` and policies/middleware for full rules.

## Troubleshooting

1. **Database connection failed** — Check pooler host, `postgres.<ref>` username, `DB_SSLMODE=require`, and password (reset in Supabase → Database settings if needed).
2. **Pooler auth right after password reset** — Wait 1–2 minutes and retry ([Supabase docs](https://supabase.com/docs/guides/troubleshooting/fatal-password-authentication-failed)).
3. **Permission errors (Linux/macOS)** — `chmod -R 775 storage bootstrap/cache`
4. **PostgreSQL duplicate key on `inventory_items` or `event_items`** — Run pending migrations; see existing sequence-sync migrations in `database/migrations/`.
5. **Calendar blank** — Check browser console and FullCalendar script loads.

## Related docs

- [`SOFTWARE_USED.md`](SOFTWARE_USED.md)
- [`docs/DATAFLOW_AND_FLOWCHARTS.md`](docs/DATAFLOW_AND_FLOWCHARTS.md)

## License

Developed for **IETI College of Science and Technology Inc.** (proprietary / academic use).

---

**IETI College of Science and Technology Inc.** — *Since 1974*
