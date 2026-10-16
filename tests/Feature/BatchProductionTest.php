<?php

namespace Tests\Feature;

use App\Models\BatchProduction;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BatchProductionTest extends TestCase
{
    use RefreshDatabase;

    protected RawMaterial $milk;
    protected RawMaterial $cream;
    protected RawMaterial $oldSugar; // inactive raw material
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::unguarded(fn () => User::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
        ])));

        $this->milk = RawMaterial::create([
            'name' => 'Raw Milk', 'unit' => 'Liter', 'selling_price' => 100,
            'stock_qty' => 50, 'is_milk' => true, 'is_active' => true,
        ]);

        $this->cream = RawMaterial::create([
            'name' => 'Cream', 'unit' => 'Kilogram', 'selling_price' => 400,
            'stock_qty' => 20, 'is_milk' => false, 'is_active' => true,
        ]);

        $this->oldSugar = RawMaterial::create([
            'name' => 'Old Sugar', 'unit' => 'Kilogram', 'selling_price' => 150,
            'stock_qty' => 5, 'is_milk' => false, 'is_active' => false,
        ]);

        // Recipe: 10 Liter milk + 2 Kg cream  ->  makes 8 Liter of Yogurt.
        $this->product = Product::create([
            'name' => 'Yogurt', 'unit' => 'Liter', 'selling_price' => 200,
            'stock_qty' => 5, 'output_qty_per_batch' => 8, 'is_active' => true,
        ]);

        $this->product->rawMaterials()->sync([
            $this->milk->id  => ['quantity_required' => 10],
            $this->cream->id => ['quantity_required' => 2],
        ]);
    }

    protected function run_batch($multiplier, ?Product $product = null)
    {
        return $this->post(route('batches.store', $product ?? $this->product), ['multiplier' => $multiplier]);
    }

    /** Asserts that no stock and no batch history changed. */
    protected function assertNothingChanged(): void
    {
        $this->assertEquals(50, $this->milk->fresh()->stock_qty);
        $this->assertEquals(20, $this->cream->fresh()->stock_qty);
        $this->assertEquals(5, $this->product->fresh()->stock_qty);
        $this->assertSame(0, BatchProduction::count());
    }

    // ------------------------------------------------------------------
    // Happy path
    // ------------------------------------------------------------------

    public function test_batch_deducts_raw_materials_and_adds_product_stock(): void
    {
        $this->run_batch(2)
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $this->assertEquals(30, $this->milk->fresh()->stock_qty);   // 50 - 10*2
        $this->assertEquals(16, $this->cream->fresh()->stock_qty);  // 20 - 2*2
        $this->assertEquals(21, $this->product->fresh()->stock_qty); // 5 + 8*2

        $batch = BatchProduction::firstOrFail();
        $this->assertSame($this->product->id, $batch->product_id);
        $this->assertSame(auth()->id(), $batch->user_id);
        $this->assertEquals(2, $batch->multiplier);
        $this->assertEquals(16, $batch->output_qty);
    }

    public function test_decimal_multiplier_scales_everything(): void
    {
        $this->run_batch(0.5)->assertSessionHas('success');

        $this->assertEquals(45, $this->milk->fresh()->stock_qty);   // 50 - 5
        $this->assertEquals(19, $this->cream->fresh()->stock_qty);  // 20 - 1
        $this->assertEquals(9, $this->product->fresh()->stock_qty); // 5 + 4
        $this->assertEquals(4, BatchProduction::firstOrFail()->output_qty);
    }

    public function test_batch_can_use_all_remaining_stock_exactly(): void
    {
        $this->cream->update(['stock_qty' => 4]); // exactly what multiplier 2 needs

        $this->run_batch(2)->assertSessionHas('success');

        $this->assertEquals(0, $this->cream->fresh()->stock_qty);
    }

    public function test_two_batches_in_a_row_use_stock_correctly(): void
    {
        $this->run_batch(1);
        $this->run_batch(1);

        $this->assertEquals(30, $this->milk->fresh()->stock_qty);
        $this->assertEquals(16, $this->cream->fresh()->stock_qty);
        $this->assertEquals(21, $this->product->fresh()->stock_qty);
        $this->assertSame(2, BatchProduction::count());
    }

    public function test_inactive_raw_material_already_in_recipe_can_still_be_used(): void
    {
        $this->product->rawMaterials()->sync([
            $this->oldSugar->id => ['quantity_required' => 1],
        ]);

        $this->run_batch(2)->assertSessionHas('success');

        $this->assertEquals(3, $this->oldSugar->fresh()->stock_qty);
    }

    // ------------------------------------------------------------------
    // Blocked batches: nothing may change
    // ------------------------------------------------------------------

    public function test_not_enough_stock_blocks_the_whole_batch(): void
    {
        $this->cream->update(['stock_qty' => 3]); // multiplier 2 needs 4

        $this->run_batch(2)->assertSessionHas('error');

        // Milk had enough, but must NOT be deducted if cream is short.
        $this->assertEquals(50, $this->milk->fresh()->stock_qty);
        $this->assertEquals(3, $this->cream->fresh()->stock_qty);
        $this->assertEquals(5, $this->product->fresh()->stock_qty);
        $this->assertSame(0, BatchProduction::count());
    }

    public static function badMultipliers(): array
    {
        return [
            'missing'   => [null],
            'zero'      => [0],
            'too small' => [0.09],
            'negative'  => [-1],
            'text'      => ['abc'],
            'too big'   => [100000],
        ];
    }

    #[DataProvider('badMultipliers')]
    public function test_bad_multiplier_is_rejected(mixed $multiplier): void
    {
        $payload = $multiplier === null ? [] : ['multiplier' => $multiplier];

        $this->post(route('batches.store', $this->product), $payload)
            ->assertSessionHasErrors('multiplier');

        $this->assertNothingChanged();
    }

    public function test_inactive_product_cannot_run_a_batch(): void
    {
        $this->product->update(['is_active' => false]);

        $this->run_batch(1)->assertSessionHas('error');

        $this->assertNothingChanged();
    }

    public function test_product_without_recipe_cannot_run_a_batch(): void
    {
        $this->product->rawMaterials()->sync([]);

        $this->run_batch(1)->assertSessionHas('error');

        $this->assertNothingChanged();
    }

    public function test_batch_is_rejected_when_a_raw_material_amount_rounds_to_zero(): void
    {
        // 0.01 x 0.1 = 0.001 -> would round to 0.00 and take the ingredient for free.
        $this->product->rawMaterials()->sync([
            $this->milk->id => ['quantity_required' => 0.01],
        ]);

        $this->run_batch(0.1)->assertSessionHas('error');

        $this->assertNothingChanged();
    }

    public function test_batch_is_rejected_when_output_rounds_to_zero(): void
    {
        $this->product->update(['output_qty_per_batch' => 0.01]);

        $this->run_batch(0.1)->assertSessionHas('error');

        $this->assertNothingChanged();
    }

    // ------------------------------------------------------------------
    // Products page + CSV
    // ------------------------------------------------------------------

    public function test_products_page_shows_empty_message_then_the_batch(): void
    {
        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('No batches run yet');

        $this->run_batch(2);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('No batches run yet')
            ->assertSee('Recent Batches')
            ->assertSee('2x');
    }

    public function test_products_page_has_no_cost_columns(): void
    {
        $this->run_batch(2);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Cost / Unit')
            ->assertDontSee('Batch Cost');
    }

    public function test_csv_export_has_plain_numbers_no_cost_and_respects_date_filter(): void
    {
        $this->run_batch(2);

        $content = $this->get(route('batches.exportCsv'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Product,Multiplier,Output,Date', $content);
        $this->assertStringContainsString('Yogurt,2,16,', $content);
        $this->assertStringNotContainsString('Cost', $content);

        $tomorrow = now()->addDay()->format('Y-m-d');
        $filtered = $this->get(route('batches.exportCsv', ['from' => $tomorrow]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringNotContainsString('Yogurt', $filtered);
    }
}
