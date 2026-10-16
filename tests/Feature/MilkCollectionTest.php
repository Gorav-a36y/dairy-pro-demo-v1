<?php

namespace Tests\Feature;

use App\Models\MilkCollection;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MilkCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected Supplier $supplier;
    protected RawMaterial $milk;
    protected RawMaterial $eggs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::unguarded(fn () => User::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
        ])));

        $this->supplier = Supplier::create(['name' => 'Test Farmer', 'phone' => '03001234567']);

        $this->milk = RawMaterial::create([
            'name' => 'Raw Milk', 'unit' => 'Liter', 'selling_price' => 100,
            'stock_qty' => 10, 'is_milk' => true, 'is_active' => true,
        ]);

        $this->eggs = RawMaterial::create([
            'name' => 'Eggs', 'unit' => 'Dozen', 'selling_price' => 300,
            'stock_qty' => 10, 'is_milk' => false, 'is_active' => true,
        ]);
    }

    /** Default valid payload. Total = 10 x 10 = 100.00 (cash, exact payment). */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier_id'     => $this->supplier->id,
            'raw_material_id' => $this->milk->id,
            'quantity'        => 10,
            'purchase_price'  => 10,
            'shift'           => 'morning',
            'payment_method'  => 'cash',
            'paid_amount'     => 100,
        ], $overrides);
    }

    protected function submit(array $overrides = [])
    {
        return $this->post(route('milk-collections.store'), $this->payload($overrides));
    }

    protected function assertRejected($response, string $field): void
    {
        $response->assertSessionHasErrors($field);
        $this->assertSame(0, MilkCollection::count());
        $this->assertEquals('10.00', $this->milk->fresh()->stock_qty);
        $this->assertDatabaseCount('supplier_transactions', 0);
    }

    protected function addPaymentMethod(string $code, bool $active): void
    {
        DB::table('payment_methods')->insert([
            'name' => ucfirst($code), 'code' => $code, 'is_system' => false,
            'is_active' => $active, 'sort_order' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- page

    public function test_suppliers_and_milk_flag_are_sent_to_the_form(): void
    {
        $response = $this->get(route('milk-collections.index'));

        $response->assertOk();
        $this->assertTrue($response->viewData('suppliers')->contains('id', $this->supplier->id));

        $rawMaterials = $response->viewData('rawMaterials');
        $this->assertTrue($rawMaterials->firstWhere('id', $this->milk->id)['is_milk']);
        $this->assertFalse($rawMaterials->firstWhere('id', $this->eggs->id)['is_milk']);
    }

    // ---------------------------------------------------------------- valid

    public function test_cash_exact_payment_is_saved_and_stock_increases(): void
    {
        $this->submit()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', [
            'supplier_id' => $this->supplier->id,
            'raw_material_id' => $this->milk->id,
            'payment_method' => 'cash',
            'total_amount' => 100,
            'paid_amount' => 100,
            'amount_given' => 100,
            'return_amount' => 0,
        ]);
        $this->assertEquals('20.00', $this->milk->fresh()->stock_qty);
        $this->assertDatabaseCount('supplier_transactions', 0);
    }

    public function test_cash_overpayment_records_the_change(): void
    {
        $this->submit(['paid_amount' => 150])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', [
            'paid_amount' => 100, 'amount_given' => 150, 'return_amount' => 50,
        ]);
    }

    public function test_decimal_total_boundary_is_exact(): void
    {
        // 3 x 1.15 = 3.45
        $this->submit(['quantity' => 3, 'purchase_price' => 1.15, 'paid_amount' => 3.45])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', ['total_amount' => 3.45, 'return_amount' => 0]);
    }

    public function test_khata_with_zero_paid_goes_fully_to_the_supplier_ledger(): void
    {
        $this->submit(['payment_method' => 'khata', 'paid_amount' => 0])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', ['payment_method' => 'khata', 'paid_amount' => 0]);
        $this->assertDatabaseHas('supplier_transactions', [
            'supplier_id' => $this->supplier->id, 'type' => 'purchase', 'amount' => 100,
        ]);
        $this->assertEquals('20.00', $this->milk->fresh()->stock_qty);
    }

    public static function validPartialAmounts(): array
    {
        return [
            'smallest amount (0.01)' => [0.01, 99.99],
            'middle'                 => [40, 60],
            'just under total'       => [99.99, 0.01],
        ];
    }

    #[DataProvider('validPartialAmounts')]
    public function test_partial_khata_between_zero_and_total_is_saved(float $paid, float $remaining): void
    {
        $this->submit(['payment_method' => 'partial_khata', 'paid_amount' => $paid])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', [
            'payment_method' => 'partial_khata',
            'paid_amount' => $paid,
            'amount_given' => $paid,
        ]);
        $this->assertDatabaseHas('supplier_transactions', ['type' => 'purchase', 'amount' => $remaining]);
    }

    public function test_csv_exports_milk_quality_values_as_utf8_numeric_columns(): void
    {
        $this->submit(['fat' => 4.5, 'snf' => 8.5])->assertSessionDoesntHaveErrors();

        $response = $this->get(route('milk-collections.exportCsv'));

        $response->assertOk();
        $this->assertStringContainsString("\xEF\xBB\xBF", $response->streamedContent());
        $this->assertStringContainsString('4.50,8.50,13.00', $response->streamedContent());
    }

    public function test_active_custom_payment_method_works_like_cash(): void
    {
        $this->addPaymentMethod('bank', true);

        $this->submit(['payment_method' => 'bank', 'paid_amount' => 100])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', ['payment_method' => 'bank', 'paid_amount' => 100]);
    }

    public function test_milk_without_fat_snf_is_accepted(): void
    {
        $this->submit()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', ['fat' => null, 'snf' => null, 'total_solids' => null]);
    }

    public function test_milk_with_fat_and_snf_calculates_total_solids(): void
    {
        $this->submit(['fat' => 4.5, 'snf' => 8.5])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', ['fat' => 4.5, 'snf' => 8.5, 'total_solids' => 13]);
    }

    public function test_milk_with_only_fat_leaves_total_solids_empty(): void
    {
        $this->submit(['fat' => 4.5])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', ['fat' => 4.5, 'snf' => null, 'total_solids' => null]);
    }

    public function test_non_milk_material_never_stores_fat_or_snf(): void
    {
        // The backend must not trust the frontend to hide these fields.
        $this->submit(['raw_material_id' => $this->eggs->id, 'fat' => 4, 'snf' => 8])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('milk_collections', [
            'raw_material_id' => $this->eggs->id, 'fat' => null, 'snf' => null, 'total_solids' => null,
        ]);
    }

    // ---------------------------------------------------------------- invalid

    public static function requiredFields(): array
    {
        return [
            'supplier'        => ['supplier_id'],
            'raw material'    => ['raw_material_id'],
            'quantity'        => ['quantity'],
            'purchase price'  => ['purchase_price'],
            'shift'           => ['shift'],
            'payment method'  => ['payment_method'],
            'cash given'      => ['paid_amount'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function test_required_fields_are_enforced(string $field): void
    {
        $payload = $this->payload();
        unset($payload[$field]);

        $this->assertRejected($this->post(route('milk-collections.store'), $payload), $field);
    }

    public static function invalidSubmissions(): array
    {
        return [
            'negative cash given'              => [['paid_amount' => -1], 'paid_amount'],
            'cash short by 0.01'               => [['paid_amount' => 99.99], 'paid_amount'],
            'cash given zero'                  => [['paid_amount' => 0], 'paid_amount'],
            'cash given too many decimals'     => [['paid_amount' => 100.001], 'paid_amount'],
            'khata with money paid'            => [['payment_method' => 'khata', 'paid_amount' => 1], 'paid_amount'],
            'partial khata zero'               => [['payment_method' => 'partial_khata', 'paid_amount' => 0], 'paid_amount'],
            'partial khata equals total'       => [['payment_method' => 'partial_khata', 'paid_amount' => 100], 'paid_amount'],
            'partial khata above total'        => [['payment_method' => 'partial_khata', 'paid_amount' => 150], 'paid_amount'],
            'quantity zero'                    => [['quantity' => 0], 'quantity'],
            'quantity negative'                => [['quantity' => -5], 'quantity'],
            'quantity three decimals'          => [['quantity' => 1.005], 'quantity'],
            'purchase price negative'          => [['purchase_price' => -1], 'purchase_price'],
            'purchase price zero'              => [['purchase_price' => 0], 'purchase_price'],
            'invalid shift'                    => [['shift' => 'midnight'], 'shift'],
            'unknown payment method'           => [['payment_method' => 'bitcoin'], 'payment_method'],
            'unknown supplier'                 => [['supplier_id' => 999999], 'supplier_id'],
            'unknown raw material'             => [['raw_material_id' => 999999], 'raw_material_id'],
            'fat above 100'                    => [['fat' => 100.01], 'fat'],
            'snf negative'                     => [['snf' => -1], 'snf'],
        ];
    }

    #[DataProvider('invalidSubmissions')]
    public function test_invalid_submissions_are_rejected(array $overrides, string $field): void
    {
        $this->assertRejected($this->submit($overrides), $field);
    }

    public function test_cash_short_by_one_cent_on_a_decimal_total_is_rejected(): void
    {
        // 3 x 1.15 = 3.45, so 3.44 is one cent short.
        $response = $this->submit(['quantity' => 3, 'purchase_price' => 1.15, 'paid_amount' => 3.44]);

        $this->assertRejected($response, 'paid_amount');
    }

    public function test_inactive_payment_method_is_rejected(): void
    {
        $this->addPaymentMethod('bank', false);

        $this->assertRejected($this->submit(['payment_method' => 'bank']), 'payment_method');
    }

    public function test_cash_is_rejected_after_it_is_deactivated(): void
    {
        DB::table('payment_methods')->where('code', 'cash')->update(['is_active' => false]);

        $this->assertRejected($this->submit(), 'payment_method');
    }

    public function test_khata_is_rejected_if_it_is_inactive_in_the_database(): void
    {
        DB::table('payment_methods')->where('code', 'khata')->update(['is_active' => false]);

        $this->assertRejected(
            $this->submit(['payment_method' => 'khata', 'paid_amount' => 0]),
            'payment_method'
        );
    }
}
