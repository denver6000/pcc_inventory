<?php

namespace Tests\Feature;

use App\Models\DailyJournal;
use App\Models\DailyJournalLine;
use App\Models\Product;
use App\Models\Sale;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(string $name = 'Cake', float $price = 120): Product
    {
        return Product::create([
            'name'              => $name,
            'selling_price'     => $price,
            'markup_percentage' => 0,
            'current_stock'     => 0,
        ]);
    }

    private function seedProductStock(Product $product, float $qty, string $date): void
    {
        $journal = DailyJournal::create([
            'journal_date' => $date,
            'kind'         => 'produce',
            'notes'        => null,
        ]);

        DailyJournalLine::create([
            'daily_journal_id' => $journal->id,
            'product_id'       => $product->id,
            'direction'        => 'in',
            'quantity'         => $qty,
            'meta'             => ['reason' => 'production'],
        ]);
    }

    public function test_pos_index_renders(): void
    {
        $this->get('/pos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pos/Index')
                ->has('products')
            );
    }

    public function test_checkout_creates_sale_and_ledger_out_line(): void
    {
        $product = $this->createProduct('Brownie', 75);
        $this->seedProductStock($product, 5, '2026-02-26');

        $this->post("/products/{$product->id}/checkout", [
            'quantity'     => 2,
            'notes'        => 'Counter sale',
            'journal_date' => '2026-02-26',
        ])->assertRedirect();

        $this->assertDatabaseHas('sales', [
            'product_id'    => $product->id,
            'quantity_sold' => 2,
            'unit_price'    => 75,
            'total_price'   => 150,
            'notes'         => 'Counter sale',
        ]);

        $this->assertEquals('2026-02-26', Sale::firstOrFail()->sale_date?->toDateString());

        $this->assertDatabaseHas('daily_journals', [
            'kind' => 'consume',
        ]);

        $this->assertDatabaseHas('daily_journal_lines', [
            'product_id' => $product->id,
            'direction'  => 'out',
            'quantity'   => 2,
        ]);

        $stock = StockLedger::productStocksAsOf('2026-02-26');
        $this->assertEquals(3.0, $stock[$product->id]);
    }

    public function test_checkout_rejects_when_product_stock_is_insufficient(): void
    {
        $product = $this->createProduct('Cupcake', 40);
        $this->seedProductStock($product, 1, '2026-02-26');

        $this->post("/products/{$product->id}/checkout", [
            'quantity'     => 2,
            'journal_date' => '2026-02-26',
        ])->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('sales', 0);

        $stock = StockLedger::productStocksAsOf('2026-02-26');
        $this->assertEquals(1.0, $stock[$product->id]);
    }

    public function test_checkout_rejects_decimal_quantity(): void
    {
        $product = $this->createProduct('Muffin', 30);
        $this->seedProductStock($product, 10, '2026-02-26');

        $this->post("/products/{$product->id}/checkout", [
            'quantity'     => 1.5,
            'journal_date' => '2026-02-26',
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_bulk_checkout_creates_sales_for_multiple_products(): void
    {
        $brownie = $this->createProduct('Brownie', 75);
        $cookie = $this->createProduct('Cookie', 35);

        $this->seedProductStock($brownie, 10, '2026-02-26');
        $this->seedProductStock($cookie, 10, '2026-02-26');

        $this->post('/checkout/bulk', [
            'journal_date' => '2026-02-26',
            'notes' => 'Bulk POS order',
            'items' => [
                ['product_id' => $brownie->id, 'quantity' => 2],
                ['product_id' => $cookie->id, 'quantity' => 3],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('sales', [
            'product_id' => $brownie->id,
            'quantity_sold' => 2,
            'total_price' => 150,
            'notes' => 'Bulk POS order',
        ]);

        $this->assertDatabaseHas('sales', [
            'product_id' => $cookie->id,
            'quantity_sold' => 3,
            'total_price' => 105,
            'notes' => 'Bulk POS order',
        ]);

        $this->assertEquals(2, DailyJournal::where('kind', 'produce')->count());
        $this->assertEquals(1, DailyJournal::where('kind', 'consume')->count());

        $stocks = StockLedger::productStocksAsOf('2026-02-26');
        $this->assertEquals(8.0, $stocks[$brownie->id]);
        $this->assertEquals(7.0, $stocks[$cookie->id]);
    }

    public function test_bulk_checkout_rejects_when_any_product_has_insufficient_stock(): void
    {
        $brownie = $this->createProduct('Brownie', 75);
        $cookie = $this->createProduct('Cookie', 35);

        $this->seedProductStock($brownie, 1, '2026-02-26');
        $this->seedProductStock($cookie, 10, '2026-02-26');

        $this->post('/checkout/bulk', [
            'journal_date' => '2026-02-26',
            'items' => [
                ['product_id' => $brownie->id, 'quantity' => 2],
                ['product_id' => $cookie->id, 'quantity' => 3],
            ],
        ])->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('sales', 0);
        $this->assertEquals(2, DailyJournal::where('kind', 'produce')->count());
        $this->assertEquals(0, DailyJournal::where('kind', 'consume')->count());
    }
}
