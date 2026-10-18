<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected RawMaterial $milk;
    protected RawMaterial $cream;
    protected RawMaterial $oldSugar; // inactive raw material

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::unguarded(fn () => User::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
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
    }

    /** Default valid payload for creating a product. */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name'                 => 'Dahi',
            'unit'                 => 'Liter',
            'selling_price'        => 150,
            'output_qty_per_batch' => 8,
            'is_active'            => 1,
            'raw_materials'        => [
                ['raw_material_id' => $this->milk->id, 'quantity_required' => 10],
            ],
        ], $overrides);
    }

    /** Default valid payload for updating (no unit: it is locked). */
    protected function updatePayload(array $overrides = []): array
    {
        $data = $this->payload($overrides);
        if (!array_key_exists('unit', $overrides)) {
            unset($data['unit']);
        }

        return $data;
    }

    protected function makeProduct(array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'name'                 => 'Yogurt',
            'unit'                 => 'Liter',
            'selling_price'        => 200,
            'stock_qty'            => 5,
            'output_qty_per_batch' => 4,
            'is_active'            => true,
        ], $overrides));

        $product->rawMaterials()->sync([
            $this->milk->id  => ['quantity_required' => 10],
            $this->cream->id => ['quantity_required' => 2],
        ]);

        return $product;
    }

    // ------------------------------------------------------------------
    // Pages
    // ------------------------------------------------------------------

    public function test_create_page_loads_with_editable_unit(): void
    {
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('Save Product')
            ->assertSee('name="unit"', false);
    }

    public function test_edit_page_shows_locked_unit_with_no_unit_field(): void
    {
        $product = $this->makeProduct();

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('Yogurt')
            ->assertSee('Unit cannot be changed after the product is created.')
            ->assertDontSee('name="unit"', false);
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function test_product_is_created_with_recipe_and_zero_stock(): void
    {
        $response = $this->post(route('products.store'), $this->payload([
            'raw_materials' => [
                ['raw_material_id' => $this->milk->id, 'quantity_required' => 10],
                ['raw_material_id' => $this->cream->id, 'quantity_required' => 2.5],
            ],
        ]));

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHasNoErrors();

        $product = Product::where('name', 'Dahi')->firstOrFail();
        $this->assertSame('Liter', $product->unit);
        $this->assertEquals(150, $product->selling_price);
        $this->assertEquals(8, $product->output_qty_per_batch);
        $this->assertEquals(0, $product->stock_qty);
        $this->assertTrue($product->is_active);

        $this->assertCount(2, $product->rawMaterials);
        $this->assertEquals(10, $product->rawMaterials->firstWhere('id', $this->milk->id)->pivot->quantity_required);
        $this->assertEquals(2.5, $product->rawMaterials->firstWhere('id', $this->cream->id)->pivot->quantity_required);
    }

    public function test_creating_a_product_does_not_touch_raw_material_stock(): void
    {
        $this->post(route('products.store'), $this->payload());

        $this->assertEquals(50, $this->milk->fresh()->stock_qty);
    }

    public function test_active_status_is_optional_and_defaults_to_active_on_create(): void
    {
        $data = $this->payload();
        unset($data['is_active']);

        $this->post(route('products.store'), $data)->assertSessionHasNoErrors();

        $this->assertTrue(Product::where('name', 'Dahi')->firstOrFail()->is_active);
    }

    public function test_product_can_be_created_inactive(): void
    {
        $this->post(route('products.store'), $this->payload(['is_active' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(Product::where('name', 'Dahi')->firstOrFail()->is_active);
    }

    public static function requiredFields(): array
    {
        return [
            'name'          => ['name'],
            'unit'          => ['unit'],
            'selling price' => ['selling_price'],
            'recipe output' => ['output_qty_per_batch'],
            'recipe'        => ['raw_materials'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function test_store_requires_field(string $field): void
    {
        $data = $this->payload();
        unset($data[$field]);

        $this->post(route('products.store'), $data)->assertSessionHasErrors($field);

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_empty_recipe_array(): void
    {
        $this->post(route('products.store'), $this->payload(['raw_materials' => []]))
            ->assertSessionHasErrors('raw_materials');

        $this->assertSame(0, Product::count());
    }

    public function test_store_requires_recipe_row_fields(): void
    {
        $this->post(route('products.store'), $this->payload([
            'raw_materials' => [['raw_material_id' => $this->milk->id]],
        ]))->assertSessionHasErrors('raw_materials.0.quantity_required');

        $this->post(route('products.store'), $this->payload([
            'raw_materials' => [['quantity_required' => 5]],
        ]))->assertSessionHasErrors('raw_materials.0.raw_material_id');

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_bad_recipe_values(): void
    {
        foreach ([0, -1, 'abc'] as $qty) {
            $this->post(route('products.store'), $this->payload([
                'raw_materials' => [['raw_material_id' => $this->milk->id, 'quantity_required' => $qty]],
            ]))->assertSessionHasErrors('raw_materials.0.quantity_required');
        }

        $this->post(route('products.store'), $this->payload([
            'raw_materials' => [['raw_material_id' => 99999, 'quantity_required' => 5]],
        ]))->assertSessionHasErrors('raw_materials.0.raw_material_id');

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_same_raw_material_twice(): void
    {
        $this->post(route('products.store'), $this->payload([
            'raw_materials' => [
                ['raw_material_id' => $this->milk->id, 'quantity_required' => 5],
                ['raw_material_id' => $this->milk->id, 'quantity_required' => 6],
            ],
        ]))->assertSessionHasErrors();

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_bad_price_output_and_unit(): void
    {
        foreach ([0, -5, 'abc'] as $value) {
            $this->post(route('products.store'), $this->payload(['selling_price' => $value]))
                ->assertSessionHasErrors('selling_price');

            $this->post(route('products.store'), $this->payload(['output_qty_per_batch' => $value]))
                ->assertSessionHasErrors('output_qty_per_batch');
        }

        $this->post(route('products.store'), $this->payload(['unit' => 'Barrel']))
            ->assertSessionHasErrors('unit');

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_numbers_too_big_for_the_database(): void
    {
        $this->post(route('products.store'), $this->payload(['selling_price' => 99999999999]))
            ->assertSessionHasErrors('selling_price');

        $this->post(route('products.store'), $this->payload(['output_qty_per_batch' => 99999999999]))
            ->assertSessionHasErrors('output_qty_per_batch');

        $this->post(route('products.store'), $this->payload([
            'raw_materials' => [['raw_material_id' => $this->milk->id, 'quantity_required' => 99999999999]],
        ]))->assertSessionHasErrors('raw_materials.0.quantity_required');

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_duplicate_name_ignoring_case_and_spaces(): void
    {
        $this->makeProduct(['name' => 'Dahi']);

        $this->post(route('products.store'), $this->payload(['name' => '  dAHi  ']))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Product::count());
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function test_update_changes_details_and_replaces_recipe(): void
    {
        $product = $this->makeProduct();

        $this->put(route('products.update', $product), $this->updatePayload([
            'name'                 => 'Thick Yogurt',
            'selling_price'        => 275.5,
            'output_qty_per_batch' => 12,
            'raw_materials'        => [
                ['raw_material_id' => $this->cream->id, 'quantity_required' => 3.25],
            ],
        ]))->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Thick Yogurt', $product->name);
        $this->assertEquals(275.5, $product->selling_price);
        $this->assertEquals(12, $product->output_qty_per_batch);

        $this->assertCount(1, $product->rawMaterials);
        $this->assertSame($this->cream->id, $product->rawMaterials->first()->id);
        $this->assertEquals(3.25, $product->rawMaterials->first()->pivot->quantity_required);
    }

    public function test_update_keeps_an_inactive_raw_material_already_in_the_recipe(): void
    {
        $product = $this->makeProduct();
        $product->rawMaterials()->attach($this->oldSugar->id, ['quantity_required' => 1]);

        $this->put(route('products.update', $product), $this->updatePayload([
            'raw_materials' => [
                ['raw_material_id' => $this->milk->id, 'quantity_required' => 10],
                ['raw_material_id' => $this->oldSugar->id, 'quantity_required' => 1],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertTrue($product->fresh()->rawMaterials->contains('id', $this->oldSugar->id));
    }

    public function test_update_ignores_unit_sent_by_a_modified_request(): void
    {
        $product = $this->makeProduct(['unit' => 'Liter']);

        $this->put(route('products.update', $product), $this->updatePayload(['unit' => 'Kilogram']))
            ->assertRedirect(route('products.index'));

        $this->assertSame('Liter', $product->fresh()->unit);

        // Even an invalid unit cannot break or change anything.
        $this->put(route('products.update', $product), $this->updatePayload(['unit' => 'Barrel']))
            ->assertRedirect(route('products.index'));

        $this->assertSame('Liter', $product->fresh()->unit);
    }

    public function test_update_ignores_stock_qty_sent_by_a_modified_request(): void
    {
        $product = $this->makeProduct(['stock_qty' => 5]);

        $this->put(route('products.update', $product), $this->updatePayload(['stock_qty' => 999]))
            ->assertRedirect(route('products.index'));

        $this->assertEquals(5, $product->fresh()->stock_qty);
    }

    #[DataProvider('requiredFields')]
    public function test_update_requires_field(string $field): void
    {
        if ($field === 'unit') {
            $this->markTestSkipped('Unit is not editable, so it is not required on update.');
        }

        $product = $this->makeProduct();
        $data = $this->updatePayload();
        unset($data[$field]);

        $this->put(route('products.update', $product), $data)->assertSessionHasErrors($field);

        $product->refresh();
        $this->assertSame('Yogurt', $product->name);
        $this->assertCount(2, $product->rawMaterials);
    }

    public function test_update_rejects_bad_recipe_and_keeps_old_recipe(): void
    {
        $product = $this->makeProduct();

        $this->put(route('products.update', $product), $this->updatePayload([
            'raw_materials' => [['raw_material_id' => $this->milk->id, 'quantity_required' => 0]],
        ]))->assertSessionHasErrors('raw_materials.0.quantity_required');

        $this->put(route('products.update', $product), $this->updatePayload([
            'raw_materials' => [],
        ]))->assertSessionHasErrors('raw_materials');

        $this->assertCount(2, $product->fresh()->rawMaterials);
    }

    public function test_update_rejects_bad_price_and_output(): void
    {
        $product = $this->makeProduct();

        $this->put(route('products.update', $product), $this->updatePayload(['selling_price' => 0]))
            ->assertSessionHasErrors('selling_price');

        $this->put(route('products.update', $product), $this->updatePayload(['output_qty_per_batch' => 0]))
            ->assertSessionHasErrors('output_qty_per_batch');

        $this->assertEquals(200, $product->fresh()->selling_price);
        $this->assertEquals(4, $product->fresh()->output_qty_per_batch);
    }

    public function test_update_allows_keeping_own_name_but_not_another_products_name(): void
    {
        $product = $this->makeProduct(['name' => 'Yogurt']);
        $other = Product::create([
            'name' => 'Butter', 'unit' => 'Kilogram', 'selling_price' => 900,
            'stock_qty' => 0, 'output_qty_per_batch' => 1, 'is_active' => true,
        ]);

        $this->put(route('products.update', $product), $this->updatePayload(['name' => 'YOGURT']))
            ->assertSessionHasNoErrors();

        $this->put(route('products.update', $product), $this->updatePayload(['name' => ' butter ']))
            ->assertSessionHasErrors('name');

        $this->assertSame('YOGURT', $product->fresh()->name);
        $this->assertSame('Butter', $other->fresh()->name);
    }

    // ------------------------------------------------------------------
    // Active / inactive
    // ------------------------------------------------------------------

    public function test_update_can_deactivate_and_reactivate(): void
    {
        $product = $this->makeProduct(['is_active' => true]);

        $this->put(route('products.update', $product), $this->updatePayload(['is_active' => 0]));
        $this->assertFalse($product->fresh()->is_active);

        $this->put(route('products.update', $product), $this->updatePayload(['is_active' => 1]));
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_update_without_is_active_keeps_current_status(): void
    {
        $inactive = $this->makeProduct(['name' => 'Old Item', 'is_active' => false]);
        $data = $this->updatePayload(['name' => 'Old Item Renamed']);
        unset($data['is_active']);

        $this->put(route('products.update', $inactive), $data)->assertSessionHasNoErrors();

        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_toggle_route_flips_active_status_and_persists(): void
    {
        $product = $this->makeProduct(['is_active' => true]);

        $this->patch(route('products.toggle', $product))->assertSessionHas('success');
        $this->assertFalse($product->fresh()->is_active);

        $this->patch(route('products.toggle', $product))->assertSessionHas('success');
        $this->assertTrue($product->fresh()->is_active);
    }
}