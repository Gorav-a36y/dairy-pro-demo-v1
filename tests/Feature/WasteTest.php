<?php

namespace Tests\Feature;

use App\Models\Waste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DairyTestHelpers;
use Tests\TestCase;

class WasteTest extends TestCase
{
    use RefreshDatabase, DairyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginUser();
    }

    public function test_waste_is_saved_and_stock_goes_down(): void
    {
        $product = $this->makeProduct(['stock_qty' => 10]);

        $this->post(route('waste.store'), [
            'item_key'  => 'product:' . $product->id,
            'quantity'  => 2.5,
            'wasted_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Waste::count());
        $this->assertEquals(7.5, $product->fresh()->stock_qty);
    }

    public function test_note_is_optional_and_empty_note_is_saved_as_null(): void
    {
        $raw = $this->makeRawMaterial(['stock_qty' => 10]);

        $this->post(route('waste.store'), [
            'item_key'  => 'raw_material:' . $raw->id,
            'quantity'  => 1,
            'wasted_at' => now()->toDateString(),
            'note'      => '   ',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Waste::firstOrFail()->note);
    }

    public function test_item_quantity_and_date_are_required(): void
    {
        $this->post(route('waste.store'), [])
            ->assertSessionHasErrors(['item_key', 'quantity', 'wasted_at']);
    }

    public function test_future_date_is_rejected(): void
    {
        $product = $this->makeProduct(['stock_qty' => 10]);

        $this->post(route('waste.store'), [
            'item_key'  => 'product:' . $product->id,
            'quantity'  => 1,
            'wasted_at' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('wasted_at');

        $this->assertSame(0, Waste::count());
    }

    public function test_past_date_is_allowed(): void
    {
        $product = $this->makeProduct(['stock_qty' => 10]);

        $this->post(route('waste.store'), [
            'item_key'  => 'product:' . $product->id,
            'quantity'  => 1,
            'wasted_at' => now()->subDays(3)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Waste::count());
    }

    public function test_unknown_item_type_is_rejected(): void
    {
        $this->post(route('waste.store'), [
            'item_key'  => 'customer:1',
            'quantity'  => 1,
            'wasted_at' => now()->toDateString(),
        ])->assertSessionHasErrors('item_key');

        $this->assertSame(0, Waste::count());
    }

    public function test_waste_more_than_stock_is_rejected(): void
    {
        $product = $this->makeProduct(['stock_qty' => 2]);

        $this->post(route('waste.store'), [
            'item_key'  => 'product:' . $product->id,
            'quantity'  => 5,
            'wasted_at' => now()->toDateString(),
        ])->assertSessionHasErrors('quantity');

        $this->assertEquals(2, $product->fresh()->stock_qty);
        $this->assertSame(0, Waste::count());
    }
}
