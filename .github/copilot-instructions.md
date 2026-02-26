# Copilot instructions

## Overview
- Laravel 12 + Inertia.js (**React 18**, JSX) SPA; single shell [resources/views/app.blade.php](resources/views/app.blade.php). Vite entry is `resources/js/app.jsx` (ignore dead `app.js` Vue file). Controllers always `Inertia::render(...)`, never `view()`.
- Stock is **time-travelled and ledger-derived**: "as-of date D" = sum(in) - sum(out) from `daily_journal_lines` where `journal_date <= D`. The `current_stock` and `quantity_added` columns on models are vestigial display slots set via `StockLedger::hydrateItems()`/`hydrateProducts()` — never write to them directly.
- No auth guards. POS (`PosController`, `CheckoutController`, `Pages/Pos/`) and Timeline (`TimelineController`, `Pages/Timeline/`) exist as code but are **not routed** in [routes/web.php](routes/web.php).

## Dev workflow
- **Setup:** `composer run setup` (installs deps, copies .env, migrates, npm install + build). First run also needs `php artisan storage:link`.
- **Dev:** `composer run dev` — serves on port 8001 + queue:listen + Vite HMR via `concurrently`; stop with Ctrl+C.
- **Tests:** `composer run test` — PHPUnit with in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in phpunit.xml). See [Testing](#testing) below.
- **CSS:** Tailwind v4 via PostCSS (`@tailwindcss/postcss` in [postcss.config.js](postcss.config.js)), **not** the Vite plugin.
- **UI tokens:** [resources/js/theme.js](resources/js/theme.js) exports a `ui` map (e.g. `ui.card`, `ui.button.primary`, `ui.input`). Compose these classes instead of ad-hoc Tailwind strings.

## Routing and pages
- [routes/web.php](routes/web.php): `/ (Items)`, `/products`, `/produce`, `/restock`, `/units`. Mutations (`store`/`update`/`destroy`) all `return back()`.
- Each page is a single `Index.jsx` under `resources/js/Pages/<Section>/`. CRUD panes toggle via a `mode` state (`null | 'add' | recordId`) rather than sub-routes — see [Pages/Items/Index.jsx](resources/js/Pages/Items/Index.jsx) and [Pages/Products/Index.jsx](resources/js/Pages/Products/Index.jsx).

## Ledger rules (append-only — critical)
- **Never** mutate stock columns or use `increment()`/`decrement()` for quantities. Only append `DailyJournalLine` rows.
- **Restock:** `DailyJournal` kind `restock` + `in` lines, mirrored to `RestockBatch`/`RestockBatchItem`. Codes: `RST-YYYYMMDD-NNN`; initial item stock uses `RST-INIT-YYYYMMDD-{padded_id}`.
- **Produce:** `DailyJournal` kind `produce`; write `out` lines per ingredient batch (user-specified order or FIFO), then one `in` line for the product. Pre-check via `StockLedger::batchBalancesAsOf()`; throw `ValidationException` on shortage.
- **Consume:** `DailyJournal` kind `consume` with `out` lines per user-ordered batch.
- **Anti-pattern:** `CheckoutController` bypasses the ledger (raw `current_stock` decrement + `Sale` rows). Do not copy this pattern.

## StockLedger ([app/Services/StockLedger.php](app/Services/StockLedger.php))
- Pure static helpers; `linesThrough($date)` joins `daily_journal_lines` to `daily_journals` filtering `DATE(journal_date) <= $date` (SQLite/SQL Server safe).
- Key methods: `itemStocksAsOf`, `productStocksAsOf`, `batchBalancesAsOf`, `batchBalancesForItemAsOf`.
- `hydrateItems($items, $date)` sets `current_stock` on items and `quantity_added` on nested `restockBatchItems`, pruning zero-balance batches. `hydrateProducts($products, $date)` sets `current_stock` on products.

## Date propagation / time travel
- [HandleInertiaRequests](app/Http/Middleware/HandleInertiaRequests.php) shares `currentDate` from `?date=` query param (default: today) into every Inertia page.
- Controllers read `$request->query('date')` and pass it to `StockLedger`. Mutation forms include optional `journal_date`; UI pages set it from `usePage().props.currentDate` before posting.
- [AppLayout.jsx](resources/js/Layouts/AppLayout.jsx) preserves date on nav links via `navHref()`. [DateNavigator.jsx](resources/js/Components/DateNavigator.jsx) provides prev/next/calendar/today using `router.get` with `?date=`.

## Frontend conventions
- All pages are **React functional components** (`.jsx`) using hooks (`useState`, `useMemo`, `useForm` from `@inertiajs/react`). No in-component fetch — all data comes via Inertia props from controllers.
- Use `useForm` for mutations, `<Link>` for navigation, `router.delete()`/`router.get()` for programmatic nav.
- `AppLayout` is auto-applied to all pages via the `resolve` function in `app.jsx`; pages do not import it.

## Pricing (keep PHP/JS aligned)
- Effective price cascade: manual `selling_price` if >0 → `computed_cost * (1 + markup_percentage/100)` if markup >0 → `computed_cost`. Duplicated in `CheckoutController`, `Products/Index.jsx`, and `Pos/Index.jsx` — keep in sync.
- `computed_cost` is a model accessor on `Product` (`getComputedCostAttribute`) summing `ingredient.quantity * item.cost_per_unit`. Appended via `->each->append('computed_cost')` after eager loading.

## Misc backend notes
- Images stored on `public` disk (`/storage/items/...`, `/storage/products/...`); deleted via `Storage::disk('public')->delete()` on model removal.
- Product recipe updates are replace-all: `$product->ingredients()->delete()` then recreate all rows.
- All models use `$fillable` (no guarded). Casts use the `casts()` method (Laravel 12 style).
- Restock index filters batches to those with `journal_date <= viewed date` and hydrates remaining qty via `StockLedger::batchBalancesAsOf()`.

## Testing
- **Run:** `composer run test` (clears config cache, then `php artisan test`). PHPUnit 11 with in-memory SQLite — see [phpunit.xml](phpunit.xml).
- **Structure:** `tests/Feature/` for HTTP/controller tests (extend `Tests\TestCase`), `tests/Unit/` for pure logic (can extend plain `PHPUnit\Framework\TestCase`). Currently only scaffold example tests exist; new tests are needed.
- **Database:** Use `Illuminate\Foundation\Testing\RefreshDatabase` trait in every feature test that touches the DB. This runs all migrations per-test against `:memory:` SQLite, ensuring a clean ledger.
- **No model factories yet:** Only `UserFactory` exists. Tests should create data directly via models or by calling seeders. When creating items/products, follow the same ledger-append pattern the controllers use — create a `DailyJournal` + `DailyJournalLine` rows, never set `current_stock` directly.
- **Ledger test helper pattern:** Build test fixtures by creating journal entries, then assert via `StockLedger::itemStocksAsOf($date)` / `batchBalancesAsOf($date)`. This verifies the single source of truth. Example flow:
  1. Create a `Unit`, then an `Item`.
  2. Create a `DailyJournal` (kind `restock`) + `RestockBatch` + `RestockBatchItem` + `DailyJournalLine` (direction `in`).
  3. Assert `StockLedger::itemStockAsOf($item->id, $date)` equals the expected balance.
  4. Create a consume journal with `out` lines and re-assert the decreased balance.
- **Controller / Inertia tests:** Use `$this->get('/')` / `$this->post('/items', [...])` etc. Assert `assertInertia(fn ($page) => $page->component('Items/Index')->has('items'))` for index pages. Mutations should redirect back (`assertRedirect`).
- **Time-travel tests:** Pass `?date=YYYY-MM-DD` on GET requests and `journal_date` in POST bodies to verify stock is correctly scoped to the requested date.
- **Key areas to cover:** `StockLedger` computation accuracy, restock store (journal + batch creation), produce store (batch depletion + shortage validation), consume store (batch-order depletion), item CRUD, product recipe replace-all updates, and date-scoped hydration.
- **What NOT to test:** The unrouted POS/Checkout/Timeline controllers — these are dormant code.
