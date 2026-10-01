# Digital Shop — Backend API

A production-grade **multi-vendor digital product marketplace** backend built with
**Laravel 13** and **PHP 8.3**. Sellers open shops and list digital products;
buyers purchase and download them; the platform takes a commission per sale.

Payments start with a **manual bank-transfer flow** (buyer uploads proof, admin
verifies) and are structured behind a gateway interface so Duitku or Midtrans can
be switched on later without touching the checkout code.

---

## 1. Requirements

| Requirement | Version |
| ----------- | ------- |
| PHP         | 8.3+    |
| Composer    | 2.x     |
| MySQL       | 8.0+    |
| Node.js     | 20+ (only for the frontend) |

### Required PHP extensions

| Extension     | Needed for                              |
| ------------- | --------------------------------------- |
| `pdo_mysql`   | the real MySQL database                 |
| `pdo_sqlite`  | the test suite (SQLite in-memory)       |
| `mbstring`    | string handling, PHPUnit                |
| `dom`, `xmlwriter`, `xml`, `xmlreader`, `simplexml` | PHPUnit, `artisan` |
| `gd`          | image validation / fake image uploads   |
| `openssl`, `tokenizer`, `ctype`, `json`, `fileinfo`, `bcmath`, `curl`, `zip`, `intl` | framework |

**Ubuntu / Debian / Parrot** (adjust `8.4` to your PHP version):

```sh
sudo apt update
sudo apt install -y php8.4-cli php8.4-mysql php8.4-sqlite3 php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-gd php8.4-intl
```

Verify:

```sh
php -m | grep -E 'pdo_mysql|pdo_sqlite|mbstring|dom|xmlwriter|bcmath|gd'
```

> **No sudo?** The test suite only needs the extensions loaded for the CLI.
> You can download the `.deb` packages with `apt-get download` and load them
> per-invocation with `php -d extension=/path/to/dom.so …` without installing
> system-wide.

---

## 2. Install

```sh
cd backend

# 1. Dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Edit .env — set the database credentials and the initial admin account.
#    DB_DATABASE=digital_shop
#    DB_USERNAME=digital_shop
#    DB_PASSWORD=secret
#    ADMIN_EMAIL=you@example.com
#    ADMIN_PASSWORD=a-strong-password
```

### Create the database and user

```sh
sudo mysql <<'SQL'
CREATE DATABASE digital_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'digital_shop'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON digital_shop.* TO 'digital_shop'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### Migrate and seed

```sh
php artisan migrate --seed
```

This creates the schema, the initial admin (from `ADMIN_*` in `.env`), and the
category tree. To also load demo sellers/products, set `SEED_DEMO_DATA=true` and
keep `APP_ENV=local`.

### Run

```sh
php artisan serve            # http://localhost:8000
```

The scheduler (order expiry) runs via:

```sh
php artisan schedule:work    # or a cron entry in production
```

---

## 3. Architecture at a glance

```
Buyer places order ──► CheckoutService (DB::transaction)
                          ├─ MoneyCalculator (integer cents, largest-remainder)
                          ├─ InvoiceNumberGenerator (unique, retry on collision)
                          └─ Payment (status: pending)
                                  │
        ┌─────────────────────────┼─────────────────────────┐
        ▼                         ▼                         ▼
   Manual proof            Duitku/Midtrans             orders:expire
   (buyer uploads)         (signed webhook)            (scheduled)
        │                         │                         │
        └──────────► PaymentService.settleWithinLock ◄──────┘
                          ├─ OrderStateMachine (validated transition)
                          ├─ DeliverableService (grant entitlements)
                          └─ seller balance credited (commission split)
                                  │
                                  ▼
                          DownloadService (private disk, audited)
```

### The five invariants

1. **Money is integer cents in code, `DECIMAL(15,2)` in the DB** — never a float.
2. **Every money-moving operation runs in `DB::transaction`** with `lockForUpdate`
   and an idempotency re-check, so a double webhook is a no-op.
3. **Order status changes only through `OrderStateMachine`.**
4. **Product files live on the `private` disk** — streamed through an authorised,
   audited endpoint; never a public URL.
5. **Authorization is layered** — `role:` middleware for the area, a Policy for
   the record, both must pass.

---

## 4. API surface

All routes are prefixed `/api/v1`. 43 routes across 17 controllers.

### Public

| Method | Path                       | Notes |
| ------ | -------------------------- | ----- |
| POST   | `/auth/register`           | buyer or seller only |
| POST   | `/auth/login`              | enumeration-safe |
| GET    | `/products`                | search, filter, paginate |
| GET    | `/products/{slug}`         | |
| GET    | `/categories`              | tree |
| GET    | `/shops/{slug}`            | |
| POST   | `/webhooks/payments/{gw}`  | signature-verified |

### Authenticated (buyer)

| Method | Path                          |
| ------ | ----------------------------- |
| GET    | `/auth/me` · POST `/auth/logout` |
| POST   | `/checkout`                   |
| GET    | `/orders` · `/orders/{invoice}` |
| POST   | `/orders/{invoice}/cancel`    |
| POST   | `/payments/{order}/proof`     |
| GET    | `/downloads` · `/downloads/{id}/file` |

### Seller (`role:seller`)

| Method | Path |
| ------ | ---- |
| GET/PUT | `/seller/shop` |
| CRUD   | `/seller/products` (+ `/files`) |
| GET    | `/seller/orders` |

### Admin (`role:admin`)

| Method | Path |
| ------ | ---- |
| GET    | `/admin/dashboard` |
| GET/PATCH | `/admin/shops` · `/admin/products` (+ `/status`) |
| GET/PATCH | `/admin/payments` (+ `/verify`, `/reject`) |

---

## 5. Testing

```sh
php artisan test
```

The suite runs against **SQLite in-memory** (configured in `phpunit.xml`) so it
needs no MySQL server. Coverage:

| File | Focus |
| ---- | ----- |
| `Unit/MoneyCalculatorTest` | discount allocation, commission split, rounding |
| `Unit/OrderStatusTest` | legal and illegal status transitions |
| `Feature/AuthTest` | role escalation, password hashing, enumeration |
| `Feature/CheckoutTest` | price forgery, transactional rollback, invoice uniqueness |
| `Feature/PaymentTest` | proof verification, balance credit, refund revocation |
| `Feature/WebhookSecurityTest` | forged/replayed/cross-merchant callbacks |
| `Feature/SellerProductTest` | cross-seller authorization, private uploads |
| `Feature/DownloadTest` | entitlement checks, audit logging, 404 on missing file |

Run one group:

```sh
php artisan test --filter=WebhookSecurity
```

---

## 6. Going live

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a strong `APP_KEY`.
- Configure a real mailer and queue worker.
- Set `PAYMENT_GATEWAY=duitku` (or `midtrans`) and fill the matching credentials.
- Point the payment provider's webhook at
  `https://your-domain/api/v1/webhooks/payments/duitku`.
- Register the scheduler:
  `* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1`
- Serve over HTTPS and keep the `private` disk outside the web root.
