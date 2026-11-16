# Sovereign Grooming Co. — Admin (PHP 8 + MySQL + vanilla JS + Tailwind CDN)

Built from the "Barber" Figma file (Admin Dashboard, Appointments, Service Management, Inventory & Stock, Admin Login).

## Public site vs Admin
| Area | URL | Access |
|---|---|---|
| Home, Services & Products, About & Branches, Book Now | `index.php`, `services.php`, `branches.php`, `book.php` | Public |
| Public data (branches, barbers, services, products, open time slots, booking requests) | `api/public/*.php` | Public (no login check; booking POST needs CSRF) |
| Admin Dashboard, Appointments, Services, Inventory | `admin/index.php` | Admin login (redirects to `admin/login.php`) |
| Admin CRUD + stock adjustments | `api/*.php` | Admin session (401 otherwise) + POST + CSRF for writes |

Auth is enforced in exactly two places in `lib/core.php`: `require_admin_page()` for admin pages and `api_boot()` for admin endpoints.
Public endpoints use `public_boot()`, which never checks a login (public reads don't even start a session).

## Setup
1. Fresh install: `mysql -u root -p < schema.sql`. The complete schema includes branches,
   staff roles, booking tokens, audit logs, availability, inventory ledgers, and reorder
   requests; the migration files are retained only as historical upgrade references.
2. Set DB credentials via env vars `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (or edit `db.php`). Use a dedicated MySQL user.
3. Create your admin: `php seed_admin.php admin "a-long-password" "Your Name"`
4. For an existing pre-consolidation database, apply the migration files in dependency order
   instead of rerunning `schema.sql`; `schema.sql` is intended for a fresh database.
5. Serve: `php -S localhost:8000` from this folder. Public site: `/`. Admin: `/admin/login.php`.
6. Replace the sample branch addresses/phones in the `branches` table with your real ones.

## Booking flow
Barber (by branch) → services (multi-select, scheduled back-to-back) → date & open time → name/phone → request saved as `pending` with a reference code.
Customers can use the reference, phone number, and private token to reschedule the full booking (branch, barber, services, date, and time) or cancel it. Rescheduling is available for pending/confirmed bookings until 20 minutes before the appointment and preserves the current status.
Admins confirm or edit it under Appointments. The same hour/overlap rules apply as in the admin, inside one transaction (all services book, or none).
Payment is collected in-store; online bookings do not require a deposit. Arriving 15 minutes late may result in the appointment time being forfeited.

## Staff access
Global admins can add or deactivate barbers, assign them to branches, create unavailable date/time ranges, and create branch-manager accounts. A branch manager can manage appointments, barbers, and inventory only for the assigned branch. Deactivating a barber preserves appointment history and removes the barber from new public bookings.
Abuse controls: CSRF token, hidden honeypot field, 5 requests/hour per session, 30-minute lead time, 60-day window.

## Layout
- `db.php` — PDO connection (try/catch, exceptions, native prepared statements)
- `lib/core.php` — session, CSRF, JSON/error handling, server-side validation
- `lib/resources.php` — one registry defining fields, filters and business rules per resource
- `lib/crud.php` — generic read / save / remove used by every endpoint
- `api/read|create|update|delete.php` — admin JSON CRUD for `appointments`, `services`, `inventory` (+ `barbers` read-only)
- `api/public/catalog.php`, `slots.php`, `book.php` — public endpoints
- `api/adjust_stock.php`, `api/stats.php`, `api/auth.php`
- `lib/layout.php` — shared public navbar/footer; `assets/public.js` — public pages + booking flow
- `admin/index.php`, `admin/login.php`, `assets/admin.js` — admin UI (modals, Fetch API, DOM updates without reloads)

## Rules enforced in PHP
- Appointments: end time derived from service duration; must fit 09:00–18:00; 15-minute slots; no past bookings;
  no overlapping pending/confirmed bookings for one barber (row-locked inside a transaction, so simultaneous requests can't double-book).
- Inventory: stock never goes negative; every change is written to `stock_ledger`; stock is only editable through adjustments.
- Services in use by appointments can't be deleted (hide them instead).
- All writes require POST + session + CSRF token; login is rate-limited per session.
