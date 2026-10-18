<?php

namespace Tests\Feature;

use App\Models\CustomerTransaction;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\DairyTestHelpers;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase, DairyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginUser();
        $this->makePaymentMethods();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'submit_token'    => (string) Str::uuid(),
            'payment_method'  => 'cash',
            'amount_received' => 1000,
            'action'          => 'save',
            'items'           => [],
        ], $overrides);
    }

    public function test_sale_date_is_always_today_even_if_browser_sends_another_date(): void
    {
        $product = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);

        $this->post(route('sales.store'), $this->payload([
            'sale_date' => '2020-01-01',
            'items'     => [['item_key' => 'product:' . $product->id, 'quantity' => 2]],
        ]))->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        $this->assertSame(now()->toDateString(), $sale->sale_date->toDateString());
    }

    public function test_cash_sale_reduces_stock_and_creates_no_khata(): void
    {
        $product = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);

        $this->post(route('sales.store'), $this->payload([
            'amount_received' => 500,
            'items'           => [['item_key' => 'product:' . $product->id, 'quantity' => 3]],
        ]))->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        $this->assertEquals(300, $sale->total_amount);
        $this->assertEquals(300, $sale->paid_amount);   // change is not revenue
        $this->assertEquals(17, $product->fresh()->stock_qty);
        $this->assertSame(0, CustomerTransaction::count());
    }

    public function test_sales_table_has_no_payment_status_column(): void
    {
        $this->assertFalse(Schema::hasColumn('sales', 'payment_status'));
    }

    public function test_khata_sale_needs_a_customer(): void
    {
        $product = $this->makeProduct();

        $this->post(route('sales.store'), $this->payload([
            'payment_method' => 'khata',
            'items'          => [['item_key' => 'product:' . $product->id, 'quantity' => 1]],
        ]))->assertSessionHas('error');

        $this->assertSame(0, Sale::count());
    }

    public function test_khata_sale_is_fully_unpaid_and_adds_to_customer_balance(): void
    {
        $product  = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $customer = $this->makeCustomer();

        $this->post(route('sales.store'), $this->payload([
            'customer_id'     => $customer->id,
            'payment_method'  => 'khata',
            'amount_received' => 999, // must be ignored
            'items'           => [['item_key' => 'product:' . $product->id, 'quantity' => 2]],
        ]))->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        $this->assertEquals(0, $sale->paid_amount);
        $this->assertEquals(200, $customer->fresh()->currentBalance());
        $this->assertDatabaseHas('customer_transactions', ['sale_id' => $sale->id, 'type' => 'khata']);
    }

    public function test_partial_khata_only_puts_the_rest_on_khata(): void
    {
        $product  = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $customer = $this->makeCustomer();

        $this->post(route('sales.store'), $this->payload([
            'customer_id'     => $customer->id,
            'payment_method'  => 'partial_khata',
            'amount_received' => 150,
            'items'           => [['item_key' => 'product:' . $product->id, 'quantity' => 2]],
        ]))->assertSessionHasNoErrors();

        $this->assertEquals(50, $customer->fresh()->currentBalance());
    }

    public function test_partial_khata_paying_everything_is_rejected(): void
    {
        $product  = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $customer = $this->makeCustomer();

        $this->post(route('sales.store'), $this->payload([
            'customer_id'     => $customer->id,
            'payment_method'  => 'partial_khata',
            'amount_received' => 200,
            'items'           => [['item_key' => 'product:' . $product->id, 'quantity' => 2]],
        ]))->assertSessionHas('error');

        $this->assertSame(0, Sale::count());
    }

    public function test_cash_less_than_total_is_rejected(): void
    {
        $product = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);

        $this->post(route('sales.store'), $this->payload([
            'amount_received' => 50,
            'items'           => [['item_key' => 'product:' . $product->id, 'quantity' => 2]],
        ]))->assertSessionHas('error');

        $this->assertSame(0, Sale::count());
        $this->assertEquals(20, $product->fresh()->stock_qty);
    }

    public function test_same_item_on_two_rows_is_added_up_for_the_stock_check(): void
    {
        $product = $this->makeProduct(['selling_price' => 10, 'stock_qty' => 5]);

        $this->post(route('sales.store'), $this->payload([
            'items' => [
                ['item_key' => 'product:' . $product->id, 'quantity' => 3],
                ['item_key' => 'product:' . $product->id, 'quantity' => 3],
            ],
        ]))->assertSessionHas('error');

        $this->assertSame(0, Sale::count());
        $this->assertEquals(5, $product->fresh()->stock_qty);
    }

    public function test_inactive_item_cannot_be_sold(): void
    {
        $product = $this->makeProduct(['is_active' => false]);

        $this->post(route('sales.store'), $this->payload([
            'items' => [['item_key' => 'product:' . $product->id, 'quantity' => 1]],
        ]))->assertSessionHas('error');

        $this->assertSame(0, Sale::count());
    }

    public function test_discount_equal_to_the_line_amount_is_rejected(): void
    {
        $product = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);

        $this->post(route('sales.store'), $this->payload([
            'items' => [['item_key' => 'product:' . $product->id, 'quantity' => 1, 'discount' => 100]],
        ]))->assertSessionHas('error');

        $this->assertSame(0, Sale::count());
    }
}
