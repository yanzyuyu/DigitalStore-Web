# CLAUDE.md — Digital Shop Backend

Guidance for Claude Code (and any engineer) working in this repository.

## What this is

A production-grade **multi-vendor digital product marketplace** API built on
**Laravel 13 / PHP 8.3**. Sellers open shops, list digital products, and buyers
purchase and download them. Payments run through a **manual bank-transfer flow**
first (payment proof upload + admin verification), with Duitku/Midtrans drivers
already wired behind the same interface for later.

## Stack

| Concern        | Choice                                             |
| -------------- | -------------------------------------------------- |
| Runtime        | PHP 8.3 (strict types everywhere)                  |
| Framework      | Laravel 13                                          |
| Database       | MySQL 8 (SQLite is used only by the test suite)     |
| Auth           | Laravel Sanctum (SPA cookie + bearer tokens)        |
| Payments       | `manual` (active), `duitku`, `midtrans` (drivers)   |
| Money          | `DECIMAL(15,2)` in the DB, integer cents in code    |

## Non-negotiable conventions

These are enforced by design and by the test suite. Do not break them.

1. **Money is never a float in storage or arithmetic.**
   - Database columns are `DECIMAL(15,2)`.
   - All calculation happens in **integer cents** inside `MoneyCalculator`.
   - Discounts are split across lines with the **largest-remainder** method so
     that the per-line amounts always sum back to the order total exactly.
   - Never `round()` a currency value to fix a mismatch — find the allocation bug.

2. **Every money-moving operation runs inside `DB::transaction`.**
   - Checkout, payment settlement, refunds, and expiry all lock the rows they
     read (`lockForUpdate`) and re-check state inside the lock.
   - A webhook that arrives twice must be a no-op the second time (idempotency).

3. **Order state changes go through `OrderStateMachine`.**
   - Directly writing `$order->status = ...` outside the state machine is a bug.
   - Allowed transitions are declared in `App\Enums\OrderStatus::allowedTransitions()`.

4. **Product files live on the `private` disk.**
   - Never expose a public URL, never use `Storage::url()`, never `serve`.
   - Downloads stream through `DownloadService`, which authorises the buyer,
     writes an audit row, and only then streams the bytes.

5. **Authorization is layered.**
   - `role:` middleware gates the area (buyer / seller / admin).
   - A Policy gates the specific record (ownership). Both must pass.
   - `User::isAdmin()` short-circuits via `before()` in every policy.

6. **Validation lives in FormRequests, not controllers.**
   - Normalisation happens in `prepareForValidation()`.
   - Never trust a client-supplied price, total, or role.

## Directory map

```
app/
├── Console/Commands/        ExpireOrdersCommand (orders:expire)
├── Enums/                   UserRole, ShopStatus, ProductStatus,
│                            OrderStatus, PaymentStatus, PaymentMethod, DiscountType
├── Exceptions/              DomainException + Checkout/Payment/Download exceptions
├── Http/
│   ├── Controllers/Api/     Auth, Product, Category, Shop, Checkout, Order,
│   │                        Payment, Download, Webhook (+ Seller/ + Admin/)
│   ├── Requests/            one FormRequest per write endpoint
│   ├── Resources/           API transformers (never leak bank/balance/hash)
│   ├── Responses/           ApiResponse envelope trait
│   └── Middleware/          EnsureUserIsActive, EnsureUserHasRole
├── Models/                  Eloquent models (strict types, casts() method)
├── Policies/                ProductPolicy, ShopPolicy, OrderPolicy, PaymentPolicy
├── Services/
│   ├── Auth/                AuthService (token issuance, enumeration-safe login)
│   ├── Checkout/            MoneyCalculator, CheckoutService
│   ├── Order/               OrderStateMachine, InvoiceNumberGenerator,
│   │                        DeliverableService, DownloadService, OrderExpiryService
│   ├── Payments/            PaymentService, PaymentGatewayManager,
│   │                        Gateways/{Manual,Duitku,Midtrans}Gateway
│   ├── Product/             ProductService
│   └── Shop/                ShopService
└── Support/                 CheckoutItem, LineItemTotals, OrderTotals, MediaUrl

routes/
├── api.php                  all v1 routes, layered middleware
└── console.php              scheduled orders:expire + token pruning

database/
├── migrations/              14 migrations, FK + indexes + soft deletes
├── factories/               one per model, with meaningful states
└── seeders/                 AdminUserSeeder, CategorySeeder, DemoDataSeeder

tests/
├── Feature/                 Auth, Checkout, Payment, WebhookSecurity,
│                            SellerProduct, Download
└── Unit/                    MoneyCalculator, OrderStatus
```

## Commands

```sh
composer install                 # install dependencies
php artisan key:generate         # app key
php artisan migrate --seed       # schema + admin + categories (+ demo data if local)
php artisan serve                # dev server on :8000
php artisan orders:expire        # expire unpaid orders (scheduled every 5 min)
php artisan test                 # run the full suite (SQLite in-memory)
php artisan test --filter=Money  # run one group
```

## Required PHP extensions

The environment must provide: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`,
`xml`, `ctype`, `json`, `bcmath`, `curl`, `fileinfo`, `dom`, `zip`.
See `README.md` for the exact `apt` commands.

## Rules for agents

- Run `php artisan test` before claiming a change is done.
- Never introduce a placeholder implementation; this codebase is held to
  production standards.
- When adding a write endpoint: FormRequest → Policy → Service → Resource.
- When adding money logic: add a `MoneyCalculator` unit test.
- When touching order/payment state: add or extend a feature test in `tests/Feature`.
