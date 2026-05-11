# Lumina POS — System Architecture

**Version:** 1.0  
**Type:** Internal Technical Documentation  
**Audience:** Developers, maintainers

---

## 1. Project Overview

Lumina POS is a production-ready Point of Sale system built for hardware and construction supply stores. It handles the full retail workflow: product inventory, order placement, delivery tracking, sales reporting, and user management.

**Architecture style:** Modular monolithic PHP application — no framework, structured with a layered Repository/Service pattern.

**Tech stack:**

| Layer | Technology |
|---|---|
| Backend | PHP 8.0+ |
| Database | MySQL (via MySQLi) |
| Frontend | Bootstrap 5.3, Bootstrap Icons 1.11 |
| Charts | Chart.js 4.4 |
| Searchable selects | Tom Select 2.3 |
| Client interactions | Vanilla JS + AJAX (fetch API) |
| Local assets | All vendor files served locally (offline-capable) |

---

## 2. Directory Structure

```
lumina-pos/
├── public/                  ← Web root — all browser-accessible files
│   ├── api/                 ← AJAX JSON endpoints
│   │   ├── delivery_api.php
│   │   ├── locations.php
│   │   ├── orders_api.php
│   │   ├── products_api.php
│   │   └── users_api.php
│   └── *.php                ← Page files (21 total)
│
├── app/                     ← Application internals (not web-accessible)
│   ├── core/                ← Core system classes
│   │   ├── Database.php     ← MySQLi connection factory
│   │   ├── Auth.php         ← Authentication & authorization
│   │   ├── Audit.php        ← Audit log writer
│   │   ├── Cart.php         ← Session-based cart functions
│   │   └── ProductHelper.php← Product CRUD + CSV import functions
│   ├── helpers/
│   │   └── error_handler.php← Global error/exception handler → app.log
│   ├── Repositories/        ← Data access layer (SQL only)
│   │   ├── CustomerRepository.php
│   │   ├── OrderRepository.php
│   │   └── ProductRepository.php
│   ├── Services/            ← Business logic layer
│   │   ├── BackupService.php
│   │   ├── ExportService.php
│   │   ├── InventoryMovementService.php
│   │   ├── OrderService.php
│   │   └── ReportService.php
│   ├── bootstrap.php        ← Single application entry point
│   ├── layout.php           ← HTML layout wrapper (layoutStart/End/Header)
│   ├── sidebar.php          ← Navigation sidebar
│   └── *.php                ← Backward-compat shims (db.php, auth_guard.php, etc.)
│
├── assets/
│   └── vendor/              ← Locally served vendor assets
│       ├── bootstrap/
│       ├── bootstrap-icons/
│       ├── chartjs/
│       └── tom-select/
│
├── config/
│   ├── database.php         ← DB credentials (gitignored)
│   └── database.example.php ← Template for new environments
│
├── storage/
│   ├── backups/             ← mysqldump .sql files
│   ├── data/ph-json/        ← Source data for one-time location import
│   └── logs/app.log         ← Runtime error log
│
├── scripts/
│   └── import_locations.php ← One-time CLI: imports PH municipalities/barangays
│
└── docs/
    └── architecture.md      ← This document
```

---

## 3. Application Bootstrap Flow

Every public page starts with a single require:

```php
require_once __DIR__ . '/../app/bootstrap.php';
```

`app/bootstrap.php` does the following in order:

1. Defines `APP_ROOT` as the project root (one level above `public/`)
2. Loads `app/helpers/error_handler.php` — registers global error/exception handlers
3. Loads `app/core/Database.php` — makes `getConnection()` available
4. Loads `app/core/Auth.php` — makes `requireLogin()`, `requireRole()`, `requireAnyRole()` available
5. Loads `app/core/Audit.php` — makes `logAction()` available
6. Loads `app/core/Cart.php` — makes session cart functions available
7. Loads `app/core/ProductHelper.php` — makes `getAllProducts()`, `addProduct()`, `importProductsFromCSV()` available
8. Calls `session_start()` — ensures `$_SESSION` is available on every page

**Request lifecycle:**

```
Browser
  └── public/dashboard.php
        └── require app/bootstrap.php
              ├── APP_ROOT defined
              ├── session started
              └── core classes loaded
        └── requireRole('owner')          ← Auth check
        └── new ReportService($conn)      ← Service layer
              └── SQL queries via conn    ← Repository layer
        └── require app/layout.php        ← HTML output
              └── require app/sidebar.php
        └── HTML response to browser
```

---

## 4. Authentication & Authorization

**Class:** `app/core/Auth.php`

Three access levels are enforced via global functions:

| Function | Behavior |
|---|---|
| `requireLogin()` | Redirects to `login.php` if no active session |
| `requireRole('owner')` | Owner-only pages; redirects to `index.php` if role mismatch |
| `requireAnyRole()` | Allows both `owner` and `cashier`; redirects to `login.php` otherwise |

**Roles:**

- `owner` — full access: dashboard, reports, products, users, audit logs, backups, deliveries
- `cashier` — restricted access: POS terminal and deliveries only

**Session lifecycle:**

1. `login.php` validates credentials against `users` table (bcrypt via `password_verify()`)
2. On success: `session_regenerate_id(true)` prevents session fixation
3. `$_SESSION` stores: `user_id`, `username`, `full_name`, `role`, `force_password_change`
4. `logout.php` calls `session_unset()` + `session_destroy()`

**Forced password reset:**

If `users.force_password_change = 1`, `Auth::requireLogin()` intercepts every request and redirects to `change_password.php` until the user sets a new password. This is triggered by admin password resets via `api/users_api.php`.

---

## 5. Database Architecture

**Connection:** `app/core/Database.php` reads credentials from `config/database.php` and returns a `mysqli` instance via `getConnection()`.

### Key Tables

**`users`**
Stores staff accounts. `role` is an enum (`owner`, `cashier`). `force_password_change` triggers the password reset flow. `username` has a UNIQUE constraint.

**`customers`**
Auto-created or matched on each order by name + address. Not a user account — purely a record for order history.

**`products`**
Inventory items. Soft-deleted via `deleted = 1` AND `deleted_at = NOW()`. Active queries always filter `deleted = 0 AND deleted_at IS NULL`. `sku` is UNIQUE.

**`orders`**
Central transaction record. Key columns:

| Column | Purpose |
|---|---|
| `order_code` | Human-readable ID e.g. `LPO-20260511-000028`, generated in PHP post-insert |
| `request_token` | UNIQUE idempotency token — prevents duplicate orders on retry |
| `status` | `completed`, `void`, `cancelled` |
| `delivery_type` | `pickup` or `delivery` |
| `delivery_status` | `pending → preparing → ready → out_for_delivery → delivered → cancelled` |
| `municipality_id` / `barangay_id` | FK to geographic tables for clean delivery sorting |

**`order_items`**
Line items per order. Stores `unit_price` at time of sale (price history preserved even if product price changes later).

**`inventory_movements`**
Append-only log of every stock change: `STOCK_ADD`, `STOCK_REMOVE`, `STOCK_RESTORE`, `PRODUCT_CREATED`, `PRODUCT_UPDATED`, `PRODUCT_DELETED`.

**`audit_logs`**
Append-only log of user actions: logins, order creation, cancellations, delivery status changes, backups, user management.

**`municipalities` / `barangays`**
Philippines geographic data (Bohol-filtered). Populated once via `scripts/import_locations.php`. Used for delivery address cascade in POS and delivery sorting/grouping.

### Relationships

```
users ──────────────────────── audit_logs.user_id (SET NULL on delete)
users ──────────────────────── inventory_movements.user_id (SET NULL on delete)
customers ──────────────────── orders.customer_id (RESTRICT)
orders ─────────────────────── order_items.order_id (RESTRICT)
products ───────────────────── order_items.product_id (RESTRICT)
products ───────────────────── inventory_movements.product_id (RESTRICT)
municipalities ─────────────── orders.municipality_id (SET NULL on delete)
municipalities ─────────────── barangays.municipality_id (RESTRICT)
barangays ──────────────────── orders.barangay_id (SET NULL on delete)
```

### Integrity Features

- **Soft deletes** — products are never hard-deleted; `deleted_at` timestamp preserved
- **Foreign keys** — all major relations enforced at DB level
- **Indexes** — on `orders.status`, `orders.delivery_status`, `orders.order_date`, `orders.payment_method`, `products.stock`, `products.category`
- **Idempotency** — `orders.request_token` UNIQUE prevents duplicate order inserts
- **Concurrency-safe stock** — `UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?` is atomic

---

## 6. Repository & Service Pattern

### Repositories — Data Access Only

Repositories contain only SQL. No business logic, no validation.

| Repository | Responsibility |
|---|---|
| `OrderRepository` | Insert/query orders and order items, delivery status updates, export queries |
| `ProductRepository` | Stock deduction/restore, active product lookup, `existsActive()` check |
| `CustomerRepository` | Find-or-create customer by name + address |

### Services — Business Logic

Services orchestrate repositories, enforce rules, and manage transactions.

| Service | Responsibility |
|---|---|
| `OrderService` | Full order placement (validation → transaction → stock deduction → receipt), order cancellation with stock restore |
| `ReportService` | Dashboard metrics, best sellers, revenue trends, low stock, payment breakdowns |
| `ExportService` | CSV generation for orders, products, reports, audit logs |
| `BackupService` | `mysqldump` execution, backup file management, size formatting |
| `InventoryMovementService` | Append-only movement log writer; failures are silently caught to never block business operations |

---

## 7. Order Flow

### Full Checkout Lifecycle

```
1. Cashier builds cart in public/index.php (JS, session-backed)
2. "Place Order" → fetch POST to public/save_order.php
   - X-Requested-With: XMLHttpRequest header required
   - JSON payload: customer, delivery, payment, cart[], request_token
3. save_order.php
   - Validates session (user must be logged in)
   - Sanitizes request_token
   - Calls OrderService::placeOrder()
4. OrderService::placeOrder()
   a. Idempotency check — if request_token already exists, return existing order
   b. Validate cart items (qty > 0, price >= 0)
   c. Validate customer, delivery type, payment method
   d. BEGIN TRANSACTION
   e. Find or create customer record
   f. INSERT into orders (with request_token, municipality_id, barangay_id)
   g. Generate order_code in PHP: LPO-{YYYYMMDD}-{000000}
   h. For each cart item:
      - INSERT into order_items
      - UPDATE products SET stock = stock - qty WHERE stock >= qty (atomic)
      - Throws RuntimeException if stock insufficient → ROLLBACK
   i. COMMIT
5. save_order.php
   - Calls clearCart()
   - Calls logAction('ORDER_CREATED', order_id)
   - Returns JSON: { success, order_id, order_code, receipt_url }
6. Browser redirects to public/receipt.php?id=X&printed=0
   - Auto-prints once (printed=0 → printed=1 via history.replaceState)
```

---

## 8. Delivery Workflow

Delivery orders are tracked through a 6-state lifecycle managed in `public/deliveries.php` and `public/api/delivery_api.php`.

### Status Lifecycle

```
pending → preparing → ready → out_for_delivery → delivered
                                                ↘ cancelled (any stage)
```

### Status Transitions & Audit Events

| Transition | Action Button | Audit Event |
|---|---|---|
| pending → preparing | Start Preparing | `DELIVERY_PREPARING` |
| preparing → ready | Mark Ready | `DELIVERY_READY` |
| ready → out_for_delivery | Dispatch | `DELIVERY_DISPATCHED` |
| out_for_delivery → delivered | Mark Delivered | `DELIVERY_COMPLETED` |

### Key Features

- **AJAX updates** — status changes update in-place without full page reload (table view); board view reloads for accuracy
- **Kanban board** — toggle between table and board view via `?view=board`
- **Sorting/grouping** — three modes: by status priority, by municipality (alphabetical), by barangay (grouped with headers)
- **Municipality/barangay** — stored as FK IDs on `orders` at checkout time; LEFT JOINed for display. Old orders without IDs show `—`
- **Delivery slip** — `public/delivery_slip.php` — printable slip with auto-print once pattern (same as receipt)
- **Delivery notes** — `orders.delivery_notes TEXT NULL` — displayed on slip and as truncated badge in table/board

---

## 9. Reporting & Exports

### Dashboard (`public/dashboard.php`)

Owner-only. Powered by `ReportService`. Displays:
- Today's and monthly summary cards (orders, revenue, cost, gross profit)
- Business insights (weekly growth %, top payment method, best seller, out-of-stock count)
- 4 Chart.js charts: 7-day revenue trend, payment method doughnut, daily revenue bar, top 5 products horizontal bar
- Best sellers today table, low stock alert table
- System health checks (DB connection, writable dirs, PHP version, server time)

### Reports (`public/report.php`)

Date-range filterable. Shows today's summary, payment breakdown, range summary, best sellers, low stock.

### Sales History (`public/sales_history.php`)

Filterable by date range, payment method, delivery type, search. Supports order detail modal, receipt reprint, and order cancellation (with stock restore).

### Inventory History (`public/inventory_history.php`)

Filterable movement log from `inventory_movements`. Shows before/after stock, action type, user, notes.

### Exports

All exports handled by `ExportService::exportToCSV()`:

| Export | Trigger |
|---|---|
| Orders (today/range/filtered) | `public/export_orders.php` |
| Reports (summary, best sellers, low stock, all products, audit logs) | `public/export_reports.php` |

---

## 10. Backup & Error Handling

### Backup (`app/Services/BackupService.php`)

- Executes `mysqldump` via `shell_exec()` with `--single-transaction` (non-blocking)
- Output written to `storage/backups/lumina_backup_{YYYYMMDD}_{HHMMSS}.sql` with `LOCK_EX`
- Filenames are timestamp-based — guaranteed unique
- Download protected by strict regex whitelist: `^lumina_backup_\d{8}_\d{6}\.sql$`
- Path traversal impossible — `basename()` applied before path construction

### Error Handling (`app/helpers/error_handler.php`)

Registered globally via `set_error_handler`, `set_exception_handler`, `register_shutdown_function`.

- All errors and exceptions logged to `storage/logs/app.log` with timestamp, type, file, line
- Users see only a generic error message — no stack traces exposed
- API endpoints (`public/api/*.php`) output JSON errors — the error handler outputs HTML only for page requests

---

## 11. Offline Support

All vendor assets are served locally from `assets/vendor/`. No CDN dependency at runtime.

| Asset | Local Path |
|---|---|
| Bootstrap 5.3.3 CSS | `assets/vendor/bootstrap/css/bootstrap.min.css` |
| Bootstrap 5.3.3 JS | `assets/vendor/bootstrap/js/bootstrap.bundle.min.js` |
| Bootstrap Icons 1.11.3 | `assets/vendor/bootstrap-icons/bootstrap-icons.min.css` + fonts |
| Chart.js 4.4.1 | `assets/vendor/chartjs/chart.umd.min.js` |
| Tom Select 2.3.1 | `assets/vendor/tom-select/tom-select.complete.min.js` + CSS |

The system is fully functional on a local XAMPP installation with no internet access.

---

## 12. Security Features

| Feature | Implementation |
|---|---|
| Password hashing | `password_hash()` / `password_verify()` (bcrypt) |
| Session fixation prevention | `session_regenerate_id(true)` on login |
| Role-based access control | `Auth::requireRole()` / `requireAnyRole()` on every page |
| SQL injection prevention | 100% prepared statements with `bind_param()` throughout |
| Soft deletes | Products never hard-deleted; historical order data preserved |
| Audit logging | All critical actions logged to `audit_logs` with user, role, timestamp |
| Idempotency | `request_token` UNIQUE constraint prevents duplicate order inserts |
| Concurrency-safe stock | Atomic `UPDATE ... WHERE stock >= qty` prevents overselling |
| Transaction safety | `placeOrder()` and `cancelOrder()` wrapped in `BEGIN/COMMIT/ROLLBACK` |
| Backup path safety | Strict filename regex + `basename()` prevents path traversal |
| Error concealment | Raw errors logged only; generic message shown to users |
| Input sanitization | `htmlspecialchars()` on all output; `trim()` + type casting on all input |

---

## 13. Future Expansion Areas

The following are **not currently implemented** — listed as potential future development areas:

- **Supplier management** — track suppliers per product, purchase orders
- **Purchase/receiving workflow** — formal stock-in process with supplier invoices
- **Quotation system** — generate price quotes for customers before order placement
- **Multi-branch support** — separate inventory and reporting per store location
- **Demand forecasting** — predict reorder points based on sales velocity
- **Advanced analytics** — cohort analysis, customer lifetime value, margin trends
- **Installer/setup wizard** — guided first-run configuration for new deployments
- **SMS/email notifications** — delivery status updates to customers
- **Barcode scanner integration** — hardware scanner support for faster POS entry
- **Returns/refunds workflow** — formal return merchandise authorization process

---

## 14. Development Notes

### Backward Compatibility Shims

During the restructure from flat-root to `public/` layout, the following shim files were created in `app/`:

```
app/db.php          → requires app/core/Database.php
app/auth_guard.php  → requires app/core/Auth.php
app/audit.php       → requires app/core/Audit.php
app/cart.php        → requires app/core/Cart.php
app/product.php     → requires app/core/ProductHelper.php
```

These exist to support any external scripts or legacy references. They add no overhead and can be removed once all references are confirmed updated.

### Why No Framework

The system was built without a framework (no Laravel, Symfony, etc.) intentionally:

- Runs on a basic XAMPP installation — no Composer, no build tools required
- Deployable by non-technical staff on a local machine
- Zero dependency management overhead
- Full control over every layer without framework conventions

The Repository/Service pattern provides the same separation of concerns as a framework would enforce, without the dependency chain.

### Modular Monolith Rationale

A single deployable unit is appropriate for a single-store POS system. The layered architecture (core → repositories → services → public pages) means individual components can be extracted into microservices in the future if multi-branch or cloud deployment becomes a requirement — without rewriting business logic.

### Deployment URL

```
http://localhost/lumina-pos/public/
```

All asset paths use root-relative URLs (`/lumina-pos/assets/vendor/...`) to work correctly from any page depth within `public/`.

### Database Credentials

Never committed to version control. Copy `config/database.example.php` to `config/database.php` and fill in credentials for each environment.
