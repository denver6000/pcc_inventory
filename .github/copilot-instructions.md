# Copilot instructions

## Architecture
- **Laravel 12 + Inertia.js (React 18, JSX) SPA.** Single shell `resources/views/app.blade.php`; Vite entry `resources/js/app.jsx`. Controllers always `Inertia::render(...)`, never `view()`.
- **Stock is ledger-derived and time-travelled:** "stock as-of date D" = Σ(in) − Σ(out) from `daily_journal_lines` where `journal_date ≤ D`. The `current_stock`/`quantity_added` model columns are **vestigial display slots** populated by `StockLedger::hydrateItems()`/`hydrateProducts()` — never write to them directly.
- **No auth guards.** POS (`PosController`, `CheckoutController`, `Pages/Pos/`), Timeline (`TimelineController`, `Pages/Timeline/`), and `Welcome.jsx` exist as code but are **not routed** — ignore them.

## Dev workflow
- **Setup:** `composer run setup` then `php artisan storage:link` on first run.
- **Dev:** `composer run dev` — Laravel on port 8001 + queue:listen + Vite HMR via `concurrently`.
- **Tests:** `composer run test` — PHPUnit 11, in-memory SQLite (`:memory:` in `phpunit.xml`).
- **CSS:** Tailwind v4 via `@tailwindcss/postcss` in `postcss.config.js` (not the Vite plugin). No `tailwind.config.js`; theme font set in `resources/css/app.css`.
- **Scenario seeders:** `php artisan migrate:fresh --seed --seeder="Database\Seeders\Scenarios\CarryoverScenarioSeeder"` (or `ShortageScenarioSeeder`) for targeted manual testing.

## Routing & pages
- Routes: `/ (Items)`, `/products`, `/produce`, `/restock`, `/units`. Mutations all `return back()`.
- Each section is one `Index.jsx` under `resources/js/Pages/<Section>/`. CRUD toggles via `mode` state (`null | 'add' | recordId`), not sub-routes. Exception: `Produce/` is decomposed into 5 files (`Index.jsx`, `ProductCards.jsx`, `IngredientCheck.jsx`, `BatchConfigPanel.jsx`, `useBatchConfig.js`, `produceUtils.js`).
- `AppLayout` is auto-applied to all pages in `app.jsx`; pages never import it.

## Ledger rules (critical — append-only)
- **Never** mutate stock columns or use `increment()`/`decrement()`. Only append `DailyJournalLine` rows.
- **Restock:** `DailyJournal` kind `restock` + `in` lines, mirrored to `RestockBatch`/`RestockBatchItem`. Batch codes: `RST-YYYYMMDD-NNN`; initial item stock: `RST-INIT-YYYYMMDD-{padded_id}`.
- **Produce:** `DailyJournal` kind `produce`; `out` lines per ingredient batch (user-ordered or FIFO), then one `in` line for the product. Pre-check via `StockLedger::batchBalancesAsOf()`; throw `ValidationException` on shortage.
- **Consume:** `DailyJournal` kind `consume` with `out` lines per user-ordered batch.
- **Anti-pattern:** `CheckoutController` bypasses the ledger (raw `current_stock` decrement). Do not copy.
- All mutations that create journal entries wrap in `DB::transaction()`.

## StockLedger (`app/Services/StockLedger.php`)
- Pure static helpers. `linesThrough($date)` joins lines to journals with `DATE(journal_date) <= $date` (SQLite & SQL Server safe).
- Key methods: `itemStocksAsOf`, `itemStockAsOf`, `productStocksAsOf`, `batchBalancesAsOf`, `batchBalancesForItemAsOf`.
- `hydrateItems($items, $date)` sets `current_stock` and prunes zero-balance batch items. `hydrateProducts($products, $date)` sets `current_stock`.

## Date / time-travel propagation
- `HandleInertiaRequests` middleware shares `currentDate` from `?date=` (default: today) into every page.
- Controllers: GET reads `$request->query('date', now()->toDateString())`; POST reads `$validated['journal_date'] ?? now()->toDateString()`.
- Frontend: `usePage().props.currentDate` is set into `form.journal_date` before submit. `AppLayout.navHref()` preserves `?date=` across navigation. `DateNavigator` component drives prev/next/calendar.

## Frontend conventions
- React 18 functional components with hooks. Data comes exclusively from Inertia props — no client-side fetch (except Timeline's partial reload, which is dormant).
- `useForm` for mutations, `<Link>` for navigation, `router.delete()`/`router.get()` for programmatic nav.
- **UI tokens:** `resources/js/theme.js` exports a `ui` map (`ui.card`, `ui.button.primary`, `ui.input`, `ui.label`, `ui.table`, `ui.td`, `ui.pill(active)`, etc.). Always compose these instead of ad-hoc Tailwind. Slate-based monochrome palette.
- Validation is inline `$request->validate()` in all controllers — no FormRequest classes.

## Pricing (keep PHP & JS in sync)
- Effective price cascade: manual `selling_price` > 0 → `computed_cost × (1 + markup_percentage/100)` if markup > 0 → `computed_cost`. Duplicated in `Products/Index.jsx` and `Pos/Index.jsx` — keep in sync.
- `Product::getComputedCostAttribute()` sums `ingredient.quantity × item.cost_per_unit`. Appended via `->each->append('computed_cost')` after eager loading.

## Backend patterns
- Images on `public` disk (`/storage/items/`, `/storage/products/`); old images deleted on update/destroy.
- Product recipe updates: replace-all (`$product->ingredients()->delete()` then recreate).
- All models use `$fillable` (no `$guarded`). Casts use the `casts()` method (Laravel 12 style).
- `DailySnapshot` model exists but is unused — reserved for future caching.

## Testing
- **Run:** `composer run test`. PHPUnit 11, in-memory SQLite, `RefreshDatabase` trait in every DB-touching test.
- **Structure:** `tests/Feature/` (~35 tests across `ItemControllerTest`, `ProductControllerTest`, `RestockControllerTest`, `ProductionControllerTest`, `ItemConsumptionControllerTest`). `tests/Unit/StockLedgerTest` (~14 tests, extends `Tests\TestCase` with `RefreshDatabase` despite living in `Unit/`).
- **No domain factories.** Only `UserFactory` exists. Tests create data inline via `Model::create()`.
- **Helper duplication:** Each test file defines private helpers (`createUnit()`, `createItem()`, `restockItem()`, `consumeItem()`) — there is no shared trait. Follow the existing per-file pattern when adding tests.
- **Fixture pattern:** Create journal entries via the full chain (Journal → Batch → BatchItem → JournalLine), then assert via `StockLedger::itemStockAsOf()` / `batchBalancesAsOf()`. Never set `current_stock` directly in tests.
- **Inertia assertions:** `assertInertia(fn ($page) => $page->component('Items/Index')->has('items'))`. Mutations assert `assertRedirect`.
- **Time-travel tests:** Pass `?date=` on GET, `journal_date` in POST body.
- **Untested areas:** Units CRUD, image upload/deletion, pricing cascade, `HandleInertiaRequests` date sharing. No frontend/JS tests exist.
- **Do NOT test:** Unrouted POS/Checkout/Timeline controllers.
