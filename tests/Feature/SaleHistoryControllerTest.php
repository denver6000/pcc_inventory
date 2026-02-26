<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_history_page_renders_with_expected_props(): void
    {
        $product = Product::create([
            'name'              => 'Brownie',
            'selling_price'     => 80,
            'markup_percentage' => 0,
            'current_stock'     => 0,
        ]);

        Sale::create([
            'product_id'    => $product->id,
            'quantity_sold' => 3,
            'unit_price'    => 80,
            'total_price'   => 240,
            'sale_date'     => '2026-02-25',
            'notes'         => 'Afternoon rush',
        ]);

        $this->get('/sales-history?date=2026-02-26')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SalesHistory/Index')
                ->has('sales', 1)
                ->has('trend')
                ->where('summary.sales_count', 1)
                ->where('summary.units_sold', 3)
                ->where('summary.revenue', 240)
            );
    }
}
