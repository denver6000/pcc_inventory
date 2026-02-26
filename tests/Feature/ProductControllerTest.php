<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUnit(): Unit
    {
        return Unit::create(['name' => 'Kilogram', 'abbreviation' => 'kg']);
    }

    private function createItem(Unit $unit, string $name = 'Flour', float $cpu = 50): Item
    {
        return Item::create([
            'name'          => $name,
            'unit_id'       => $unit->id,
            'cost_per_unit' => $cpu,
            'default_stock' => 0,
            'current_stock' => 0,
        ]);
    }

    // ── index ───────────────────────────────────────────────

    public function test_products_index_renders_inertia_page(): void
    {
        $this->get('/products')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Products/Index')
                ->has('products')
                ->has('items')
            );
    }

    // ── store ───────────────────────────────────────────────

    public function test_store_creates_product_with_ingredients(): void
    {
        $unit  = $this->createUnit();
        $item1 = $this->createItem($unit, 'Flour', 50);
        $item2 = $this->createItem($unit, 'Sugar', 60);

        $response = $this->post('/products', [
            'name'              => 'Cake',
            'selling_price'     => 200,
            'markup_percentage' => 0,
            'ingredients'       => [
                ['item_id' => $item1->id, 'quantity' => 2],
                ['item_id' => $item2->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Cake', 'selling_price' => 200]);

        $product = Product::where('name', 'Cake')->first();
        $this->assertCount(2, $product->ingredients);
    }

    public function test_store_creates_product_without_ingredients(): void
    {
        $this->post('/products', [
            'name'              => 'Simple Product',
            'selling_price'     => 100,
            'markup_percentage' => 0,
        ])->assertRedirect();

        $product = Product::where('name', 'Simple Product')->first();
        $this->assertNotNull($product);
        $this->assertCount(0, $product->ingredients);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->post('/products', [])
            ->assertSessionHasErrors('name');
    }

    // ── update (recipe replace-all) ─────────────────────────

    public function test_update_replaces_all_ingredients(): void
    {
        $unit  = $this->createUnit();
        $item1 = $this->createItem($unit, 'Flour', 50);
        $item2 = $this->createItem($unit, 'Sugar', 60);
        $item3 = $this->createItem($unit, 'Butter', 200);

        $product = Product::create([
            'name'              => 'Cake',
            'selling_price'     => 0,
            'markup_percentage' => 30,
        ]);

        // Original recipe: flour + sugar
        $product->ingredients()->create(['item_id' => $item1->id, 'quantity' => 2]);
        $product->ingredients()->create(['item_id' => $item2->id, 'quantity' => 1]);

        // Update: replace with flour + butter (different recipe)
        $this->put("/products/{$product->id}", [
            'name'              => 'Cake v2',
            'selling_price'     => 0,
            'markup_percentage' => 30,
            'ingredients'       => [
                ['item_id' => $item1->id, 'quantity' => 3],
                ['item_id' => $item3->id, 'quantity' => 0.5],
            ],
        ])->assertRedirect();

        $product->refresh();
        $this->assertEquals('Cake v2', $product->name);

        $ingredients = $product->ingredients()->get();
        $this->assertCount(2, $ingredients);

        // Old sugar ingredient is gone
        $this->assertDatabaseMissing('product_ingredients', [
            'product_id' => $product->id,
            'item_id'    => $item2->id,
        ]);

        // New ingredients are present
        $this->assertDatabaseHas('product_ingredients', [
            'product_id' => $product->id,
            'item_id'    => $item1->id,
            'quantity'    => 3,
        ]);
        $this->assertDatabaseHas('product_ingredients', [
            'product_id' => $product->id,
            'item_id'    => $item3->id,
            'quantity'    => 0.5,
        ]);
    }

    // ── computed_cost accessor ───────────────────────────────

    public function test_computed_cost_sums_ingredient_costs(): void
    {
        $unit  = $this->createUnit();
        $item1 = $this->createItem($unit, 'Flour', 50);
        $item2 = $this->createItem($unit, 'Sugar', 60);

        $product = Product::create([
            'name'              => 'Cake',
            'selling_price'     => 0,
            'markup_percentage' => 0,
        ]);

        $product->ingredients()->create(['item_id' => $item1->id, 'quantity' => 2]);
        $product->ingredients()->create(['item_id' => $item2->id, 'quantity' => 1.5]);

        $product->load('ingredients.item');

        // 2*50 + 1.5*60 = 100 + 90 = 190
        $this->assertEquals(190.0, $product->computed_cost);
    }

    // ── destroy ─────────────────────────────────────────────

    public function test_destroy_deletes_product(): void
    {
        $product = Product::create([
            'name'              => 'Cake',
            'selling_price'     => 100,
            'markup_percentage' => 0,
        ]);

        $this->delete("/products/{$product->id}")->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
