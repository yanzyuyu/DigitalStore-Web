# AGENTS.md

See [CLAUDE.md](./CLAUDE.md) for the full project guide. The essentials:

## Before you change anything

1. `composer install`
2. `php artisan key:generate`
3. `php artisan migrate --seed`
4. `php artisan test` — establish a green baseline

## The five rules you must not break

1. **Money is integer cents in code, `DECIMAL(15,2)` in the DB.** Never a float.
2. **Every money-moving operation is inside `DB::transaction`** with `lockForUpdate`
   and an idempotency re-check.
3. **Order status only changes through `OrderStateMachine`.**
4. **Product files stay on the `private` disk** — no public URLs, no `serve`.
5. **Authorize twice:** `role:` middleware for the area, a Policy for the record.

## When you add something

| You are adding…        | Do this                                                       |
| ---------------------- | ------------------------------------------------------------- |
| A write endpoint       | FormRequest → Policy → Service → Resource → route with middleware |
| Money logic            | a `MoneyCalculator` unit test that proves the lines sum to the total |
| An order/payment state | extend the allowed-transition table **and** a feature test     |
| A model                | `declare(strict_types=1)`, `casts()` method, factory + states  |
| A response field       | confirm the Resource never leaks `password`, `bank_*`, balance |

## Verify before you finish

```sh
php artisan test
```

If the suite is red, the change is not done. Report which test failed and why.
