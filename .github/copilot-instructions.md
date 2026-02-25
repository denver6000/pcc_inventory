# Copilot instructions

## Project overview
PCC Inventory system — a Laravel 12 + Inertia.js (Vue 3) SPA for managing stock items, defining products with recipes, restocking inventory, producing finished products from raw ingredients, manually consuming items, and point-of-sale checkout. The system mirrors a day-centric workflow and supports time-travel: "stock on day D" is always a pure function of journal entries through D.

## Stack & architecture
- **Laravel 12 + Inertia.js (Vue 3) SPA**: routes always return `Inertia::render('PageName', $props)`, never `view()`. Single HTML shell: [resources/views/app.blade.php](resources/views/app.blade.php).
- Vue pages in [resources/js/Pages/](resources/js/Pages/) — Inertia resolves by name: `'Items/Index'` → `Pages/Items/Index.vue`. The `@` alias maps to `resources/js/` (set by `laravel-vite-plugin`).
- Tailwind v4 uses `@import 'tailwindcss'` + `@source` directives in [resources/css/app.css](resources/css/app.css). Processed via **`@tailwindcss/postcss`** in [postcss.config.js](postcss.config.js) (NOT `@tailwindcss/vite` — removed to fix Linux/EC2 esbuild deadlock).
- **No authentication guards** on any routes; the app is currently unguarded.
- All Tailwind utility classes are centralised in [resources/js/theme.js](resources/js/theme.js) as the exported `ui` object. Import with `import { ui } from '@/theme'` and apply as `:class="ui.card"` etc. Never inline one-off Tailwind strings for structural elements — extend `ui` instead.

## Developer workflows
| Task | Command |
|---|---|
| Initial setup | `composer run setup` |
| Local dev | `composer run dev` (serves on **port 8001**, starts queue + Vite concurrently) |
| Tests | `composer run test` (uses in-memory SQLite — see [phpunit.xml](phpunit.xml)) |
| Storage symlink (first run) | `php artisan storage:link` |

Tests are minimal — only default `ExampleTest` files exist in `tests/Feature` and `tests/Unit`. No custom test suite yet.

## Event-sourced stock ledger — the core invariant
Stock is **never stored as mutable state**. The immutable, append-only `daily_journal_lines` table is the single source of truth. Every quantity in the system is a pure derivation:

```
stock_on_day_D = Σ(in-lines where journal_date ≤ D) − Σ(out-lines where journal_date ≤ D)
```

The same formula applies to item stock, product stock, and individual restock-batch-item balances. The `current_stock` column on `items`/`products` and `quantity_added` on `restock_batch_items` are **vestigial** — they are never read for business logic. All reads go through `StockLedger`.

### Mutation rules — append-only
- **Restocking** → creates a `DailyJournal` (kind=`restock`) + one `DailyJournalLine` per item (direction=`in`). Also creates `RestockBatch`/`RestockBatchItem` rows for batch-code tracking. **No `$item->increment()`**.
- **Producing** → creates a `DailyJournal` (kind=`produce`) + `out` lines for each ingredient depletion + one `in` line for the product gained. **No `$item->decrement()` or `$product->increment()`**.
- **Manual consumption** → creates a `DailyJournal` (kind=`consume`) + `out` lines for each batch draw-down. **No `$line->decrement()` or `$item->decrement()`**.
- **New item creation** → if `default_stock > 0`, creates an initial restock journal (kind=`restock`) with an `in` line. **Never sets `current_stock` directly**.

**Never** use `increment()`, `decrement()`, or `lockForUpdate()` on stock columns. All concurrency is handled by append-only inserts.

> **⚠ Known violation:** `CheckoutController::store` currently uses `$ing->item->decrement('current_stock', ...)` and creates `Sale` records instead of journal entries. This bypasses the ledger entirely and needs to be migrated to the journal pattern. Do not copy this pattern.

### `StockLedger` service ([app/Services/StockLedger.php](app/Services/StockLedger.php))
Static, pure-function helpers — no side effects:
| Method | Returns |
|---|---|
| `itemStocksAsOf($date)` | `array<int, float>` — all item balances |
| `itemStockAsOf($itemId, $date)` | `float` — single item balance |
| `productStocksAsOf($date)` | `array<int, float>` — all product balances |
| `batchBalancesAsOf($date)` | `array<int, float>` — all batch-line balances |
| `batchBalancesForItemAsOf($itemId, $date)` | `array<int, float>` — batch-line balances for one item |
| `hydrateItems($items, $date)` | `void` — sets `current_stock` and `quantity_added` on loaded models |
| `hydrateProducts($products, $date)` | `void` — sets `current_stock` on loaded models |

All methods use a shared `linesThrough($date)` base query that joins `daily_journal_lines` to `daily_journals` and filters with `DATE(daily_journals.journal_date) <= $date` for cross-driver compatibility (SQLite stores dates as datetime strings).

### Date-aware system
- `currentDate` is shared to **all** Vue pages via [HandleInertiaRequests](app/Http/Middleware/HandleInertiaRequests.php) middleware: `$request->query('date', now()->toDateString())`.
- Every `index` controller action uses this `?date=` param for `StockLedger::hydrate*()` calls.
- Mutation actions (`store`) accept an optional `journal_date` form field for back-dating entries.
- The [DateNavigator](resources/js/Components/DateNavigator.vue) component in [AppLayout](resources/js/Layouts/AppLayout.vue) provides prev/next day buttons and a calendar popup. It navigates by setting `?date=` query param via `router.get()`.
- [AppLayout](resources/js/Layouts/AppLayout.vue) uses a `navHref()` helper to propagate the current date across nav links.

## Backend conventions
- All controller mutations return `back()` — no JSON API responses.
- Images stored on `public` disk (`Storage::disk('public')`), accessible at `/storage/{path}`. Paths stored as `items/filename.jpg` or `products/filename.jpg`.
- Product recipe updates use **full delete + recreate**: `$product->ingredients()->delete()` then recreate each line. No partial patch.
- `computed_cost` is **not** in `$appends` — always append manually after eager-loading:
  ```php
  Product::with('ingredients.item.unit')->get()->each->append('computed_cost')
  ```
- Restock batch codes: regular restocks use `RST-YYYYMMDD-NNN` (daily sequence); initial item stock uses `RST-INIT-YYYYMMDD-{padded_id}`.
- Database seeding order ([DatabaseSeeder](database/seeders/DatabaseSeeder.php)): `UnitSeeder` → `ItemSeeder` → `RestockBatchSeeder` → `ProductSeeder` → `ProductIngredientSeeder`.

## Frontend conventions
- Use `<script setup>` + `defineProps()` for all page components. Never fetch data inside components — all data arrives as Inertia props.
- Use `useForm()` from `@inertiajs/vue3` for all form submissions; use `router` for programmatic navigation.
- Use `<Link>` (from `@inertiajs/vue3`) instead of `<a>` for client-side navigation.
- Active nav detection in [AppLayout.vue](resources/js/Layouts/AppLayout.vue): exact `url === '/' || url.startsWith('/?')` for Items, `url.startsWith('/...')` for all other routes.
- Single-page CRUD panels use a `mode` ref (`null | 'add' | <id>`) — not a router. See [Items/Index.vue](resources/js/Pages/Items/Index.vue) and [Products/Index.vue](resources/js/Pages/Products/Index.vue).
- Every page receives `currentDate` via shared Inertia props (from middleware, not per-controller).

## Domain models

### Unit (`units` table)
`name`, `abbreviation`. Only `store` and `destroy` routes (no `update`). `UnitController::index()` uses `withCount('items')`.

### Item (`items` table) — raw ingredient
`name`, `image_path`, `unit_id`, `cost_per_unit`, `default_stock`, `current_stock` — all numeric fields cast to `float`. Has `restockBatchItems` relation (`hasMany(RestockBatchItem::class)`).
> **Note:** `current_stock` is vestigial. Always use `StockLedger::itemStockAsOf()` or `hydrateItems()`.

### Product (`products` table) — finished goods
`name`, `image_path`, `selling_price` (0 = not set), `markup_percentage` (0 = not used), `current_stock` — all cast to `float`.  
`getComputedCostAttribute(): float` — sums `ingredient.quantity × item.cost_per_unit`. Returns 0.0 if relations not loaded. Has `sales()` hasMany relation.
> **Note:** `current_stock` is vestigial. Always use `StockLedger::productStocksAsOf()` or `hydrateProducts()`.

### DailyJournal (`daily_journals` table) — event header
`journal_date` (date), `kind` (`restock|produce|consume|adjust`), `notes`, `closed_at`. Has `lines()` hasMany.

### DailyJournalLine (`daily_journal_lines` table) — append-only event
`daily_journal_id` FK, `item_id` (nullable FK), `product_id` (nullable FK), `restock_batch_item_id` (nullable FK), `direction` (`in|out`), `quantity` (float), `meta` (JSON).  
**This is the source of truth for all stock.** Lines are never updated or deleted.

### DailySnapshot (`daily_snapshots` table) — cache (planned)
`snapshot_date` (unique date), `captured_at`, `items` (JSON), `products` (JSON), `restock_batch_items` (JSON), `notes`. Not yet used — reserved for performance optimisation of the ledger queries.

### RestockBatch / RestockBatchItem
- `RestockBatch`: `batch_code`, `journal_id` FK, `notes`, `total_cost`. `hasMany(RestockBatchItem)`. Has `journal()` belongsTo.
- `RestockBatchItem`: `restock_batch_id`, `journal_line_id` FK, `item_id`, `quantity_added` (initial amount — **vestigial for reads**), `cost_per_unit` (snapshot), `subtotal`. Has `batch()` and `item()` relations.
> **Note:** `quantity_added` is the initial deposit. The live remaining balance is computed via `StockLedger::batchBalancesAsOf()`.

### ProductIngredient
`product_id`, `item_id`, `quantity` — one recipe line.

### Sale (`sales` table) — POS transaction record
`product_id`, `quantity_sold`, `unit_price`, `total_price`, `notes` — all numeric fields cast to `float`. Has `product()` belongsTo.
> **Note:** Sales are created by `CheckoutController` which currently bypasses the journal system. See known violation warning above.

## Pages & routes
| Route | Controller | Vue Page |
|---|---|---|
| `GET /` | `ItemController@index` | `Items/Index` |
| `POST /items` / `PUT /items/{item}` / `DELETE /items/{item}` | `ItemController` | — |
| `POST /items/{item}/consume` | `ItemConsumptionController@store` | — |
| `GET /products` + mutations | `ProductController` | `Products/Index` |
| `GET /produce` | `ProductionController@index` | `Produce/Index` |
| `POST /produce` | `ProductionController@store` | — |
| `GET /restock` + `POST /restock` | `RestockController` | `Restock/Index` |
| `GET /units` + mutations | `UnitController` | `Units/Index` |

> **Unregistered routes:** `PosController` ([app/Http/Controllers/PosController.php](app/Http/Controllers/PosController.php)) and `CheckoutController` ([app/Http/Controllers/CheckoutController.php](app/Http/Controllers/CheckoutController.php)) exist with a `Pos/Index.vue` page, but their routes are **not yet registered** in [routes/web.php](routes/web.php). The POS page posts to `/products/{product}/checkout`.

Nav order in [AppLayout.vue](resources/js/Layouts/AppLayout.vue): **Items → Products → Produce → Restock → Units** (POS not yet in nav).

All `GET` routes accept `?date=YYYY-MM-DD` for time-travel. All `POST` mutation routes accept `journal_date` for back-dating entries.

## Produce flow (`ProductionController::store`)
1. Validate `product_id`, `quantity` (integer ≥ 1), optional `batch_orders: { [item_id]: [batch_item_id, ...] }`, optional `journal_date`, `notes`.
2. Inside `DB::transaction()`:
   - Create a `DailyJournal` (kind=`produce`).
   - Pre-compute all batch-line balances via `StockLedger::batchBalancesAsOf($journalDate)`.
   - For each ingredient, verify ledger-derived availability ≥ needed quantity.
   - Write `DailyJournalLine` entries: `direction=out` for each batch-item draw-down, `direction=in` for the product output.
   - **No `increment()`, `decrement()`, or `lockForUpdate()`.**
3. Return `back()->withErrors(['produce' => '...'])` on stock failures (via `ValidationException`).
4. Frontend primes default FIFO order in `primeBatchOrders()` (oldest `batch.created_at` first). The `Produce/Index` page requires the deep eager load: `Product::with('ingredients.item.unit', 'ingredients.item.restockBatchItems.batch')`.

## Pricing logic (must stay in sync between PHP and JS)
Three modes — priority: manual > markup > auto. Duplicated in `CheckoutController::store`, `Pos/Index.vue`, and `Products/Index.vue`.
```php
$effectivePrice = $product->selling_price > 0
    ? (float) $product->selling_price
    : ($product->markup_percentage > 0
        ? round($product->computed_cost * (1 + $product->markup_percentage / 100), 2)
        : $product->computed_cost);
```
```js
const sp = parseFloat(product.selling_price);
if (sp > 0) return sp;
const markup = parseFloat(product.markup_percentage ?? 0);
const cost   = parseFloat(product.computed_cost ?? 0);
return markup > 0 ? cost * (1 + markup / 100) : cost;
```
`Products/Index.vue` uses a `pricingMode` ref (`'auto'|'markup'|'manual'`) — inactive fields are zeroed before posting.

