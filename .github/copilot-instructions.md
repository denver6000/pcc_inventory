# Copilot instructions

## Project overview
PCC Inventory & POS system — a Laravel 12 + Inertia.js (Vue 3) SPA for managing stock items, defining products with recipes, processing sales, and restocking inventory.

## Stack & architecture
- **Laravel 12 + Inertia.js (Vue 3) SPA**: routes always return `Inertia::render('PageName', $props)`, never `view()`. Single HTML shell: [resources/views/app.blade.php](resources/views/app.blade.php).
- Vue pages in [resources/js/Pages/](resources/js/Pages/) — Inertia resolves by name: `'Items/Index'` → `Pages/Items/Index.vue`. The `@` alias maps to `resources/js/` (set by `laravel-vite-plugin`).
- Shared props (auth user) flow via `HandleInertiaRequests::share()` → accessed as `usePage().props.auth.user`.
- Tailwind v4 uses `@import 'tailwindcss'` + `@source` directives in [resources/css/app.css](resources/css/app.css). Processed via **`@tailwindcss/postcss`** (NOT `@tailwindcss/vite` — removed to fix Linux/EC2 esbuild deadlock).
- **No authentication guards** on any routes; the app is currently unguarded.

## Developer workflows
| Task | Command |
|---|---|
| Initial setup | `composer run setup` |
| Local dev | `composer run dev` (serves on **port 8001**, starts queue + Vite concurrently) |
| Tests | `composer run test` |
| Storage symlink (first run) | `php artisan storage:link` |

## Conventions

### Backend
- All controller mutations return `back()` — no JSON API responses.
- Images are stored on the `public` disk (`Storage::disk('public')`), accessible at `/storage/{image_path}`. Paths are stored as `items/filename.jpg` or `products/filename.jpg`.
- When creating an `Item`, always seed `current_stock = default_stock`.
- Product recipe updates use **full delete + recreate**: `$product->ingredients()->delete()` then recreate each line. No partial patch.
- `computed_cost` is **not** in `$appends` — always append manually after eager-loading `ingredients.item.unit`:
  ```php
  Product::with('ingredients.item.unit')->get()->each->append('computed_cost')
  ```
- Restock batches use a human-readable code `RST-YYYYMMDD-NNN` (daily sequence), generated with `lockForUpdate()` to prevent duplicates. `cost_per_unit` and `subtotal` are **snapshots** captured at restock time from `item->cost_per_unit`.

### Frontend
- Use `<script setup>` + `defineProps()` for all page components. Never fetch data inside components — all data arrives as Inertia props.
- Use `useForm()` from `@inertiajs/vue3` for all form submissions; use `router` for programmatic navigation.
- Use `<Link>` (from `@inertiajs/vue3`) instead of `<a>` for client-side navigation.
- Active nav link detection in [AppLayout.vue](resources/js/Layouts/AppLayout.vue): exact `url === '/'` for Items, `url.startsWith('/...')` for all other routes.
- Single-page CRUD panels use a `mode` ref (`null | 'add' | <id>`) — not a router. See [Restock/Index.vue](resources/js/Pages/Restock/Index.vue) and [Products/Index.vue](resources/js/Pages/Products/Index.vue).

## Domain models

### Unit (`units` table)
- `name`, `abbreviation`. `hasMany(Item::class)`.
- Only `store` and `destroy` routes exist (no `update`). `UnitController::index()` uses `withCount('items')`.

### Item (`items` table) — stock / raw ingredient
- `name`, `image_path`, `unit_id` (FK → units), `cost_per_unit`, `default_stock`, `current_stock` — all numeric fields cast to `float`.

### Product (`products` table) — sellable item with a recipe
- `name`, `image_path`, `selling_price` (0 = not manually set), `markup_percentage` (0 = not used), cast to `float`.
- `getComputedCostAttribute(): float` — sums `ingredient.quantity × item.cost_per_unit`. Returns 0.0 if `ingredients` relation or `item` sub-relation is not loaded.

### ProductIngredient / Sale
- `ProductIngredient`: `product_id`, `item_id`, `quantity` — one recipe line.
- `Sale`: `product_id`, `quantity_sold`, `unit_price` (price at time of sale), `total_price`, `notes` — all numeric cast to `float`.

### RestockBatch / RestockBatchItem
- `RestockBatch`: `batch_code` (e.g. `RST-20260224-001`), `notes`, `total_cost`. `hasMany(RestockBatchItem::class)`.
- `RestockBatchItem`: `restock_batch_id`, `item_id`, `quantity_added`, `cost_per_unit` (snapshot), `subtotal` — all numeric cast to `float`.
- ⚠️ `batch_code` column is in `$fillable` and used by the controller but **missing from the `restock_batches` migration** — add it if the column is absent.

## Pricing logic (must stay in sync between PHP and JS)
Three modes — priority: manual > markup > auto.
```php
// CheckoutController
$effectivePrice = $product->selling_price > 0
    ? (float) $product->selling_price
    : ($product->markup_percentage > 0
        ? round($product->computed_cost * (1 + $product->markup_percentage / 100), 2)
        : $product->computed_cost);
```
```js
// Pos/Index.vue
const sp = parseFloat(product.selling_price);
if (sp > 0) return sp;
const markup = parseFloat(product.markup_percentage ?? 0);
const cost   = parseFloat(product.computed_cost ?? 0);
return markup > 0 ? cost * (1 + markup / 100) : cost;
```
- `Products/Index.vue` uses a `pricingMode` ref (`'auto'|'markup'|'manual'`) to drive which form fields are active. On submit, the inactive fields are zeroed before posting.

## Pages & routes
| Route | Controller | Vue Page |
|---|---|---|
| `GET /` | `ItemController@index` | `Items/Index` |
| `GET /products` | `ProductController@index` | `Products/Index` |
| `GET /restock` | `RestockController@index` | `Restock/Index` |
| `GET /units` | `UnitController@index` | `Units/Index` |
| `POST /products/{product}/checkout` | `CheckoutController@store` | — |

Nav order in [AppLayout.vue](resources/js/Layouts/AppLayout.vue): **Items → Products → Restock → Units**.  
`PosController` and `CheckoutController` exist but `/pos` and `POST /products/{product}/checkout` are **not registered in [routes/web.php](routes/web.php)** — add them if POS functionality is needed.

## Checkout flow (`CheckoutController::store`)
1. Validate `quantity` (numeric, min 0.01) and `notes`.
2. Eager-load `$product->load('ingredients.item.unit')`.
3. Pre-flight stock check (before transaction): collect all failures, return `back()->withErrors(['checkout' => '...'])` if any.
4. Compute `$effectivePrice`; wrap stock decrements + `Sale::create()` in `DB::transaction()`.

## Restock flow (`RestockController::store`)
1. Validate `items` array (each needs `item_id`, `quantity_added` ≥ 0.0001).
2. Inside `DB::transaction()`: snapshot each item's current `cost_per_unit`, compute `subtotal`, accumulate `total_cost`.
3. Generate `batch_code` with `lockForUpdate()` on a daily count query before creating the batch.
4. Use `$batch->items()->create(...)` for each line; `Item::increment('current_stock', $qty)` updates stock.

