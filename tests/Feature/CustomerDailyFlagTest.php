<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DairyTestHelpers;
use Tests\TestCase;

class CustomerDailyFlagTest extends TestCase
{
    use RefreshDatabase, DairyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginUser();
    }

    public function test_new_customer_is_not_daily_when_box_is_not_ticked(): void
    {
        $product = $this->makeProduct();

        // The browser sends no is_daily_customer field when the box is unticked.
        // An empty item row may still be sent.
        $this->post(route('customers.store'), [
            'name'        => 'Walk Up Customer',
            'phone'       => '03001112223',
            'daily_items' => [['item_key' => '', 'quantity' => '']],
        ])->assertSessionHasNoErrors();

        $customer = Customer::where('name', 'Walk Up Customer')->firstOrFail();

        $this->assertFalse($customer->is_daily_customer);
        $this->assertSame(0, $customer->dailyItems()->count());
    }

    public function test_daily_customer_needs_at_least_one_item(): void
    {
        $this->post(route('customers.store'), [
            'name'              => 'Daily No Items',
            'phone'             => '03001112224',
            'is_daily_customer' => '1',
        ])->assertSessionHasErrors('daily_items');

        $this->assertDatabaseMissing('customers', ['name' => 'Daily No Items']);
    }

    public function test_daily_customer_is_saved_with_items(): void
    {
        $product = $this->makeProduct();

        $this->post(route('customers.store'), [
            'name'              => 'Daily Milk Customer',
            'phone'             => '03001112225',
            'is_daily_customer' => '1',
            'daily_items'       => [['item_key' => 'product:' . $product->id, 'quantity' => '2']],
        ])->assertSessionHasNoErrors();

        $customer = Customer::where('name', 'Daily Milk Customer')->firstOrFail();

        $this->assertTrue($customer->is_daily_customer);
        $this->assertSame(1, $customer->dailyItems()->count());
    }

    public function test_edit_page_does_not_tick_the_box_for_a_normal_customer(): void
    {
        $customer = $this->makeCustomer(['is_daily_customer' => false]);

        $this->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee('isDaily: false', false);
    }

    public function test_edit_page_ticks_the_box_for_a_daily_customer(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer(['is_daily_customer' => true]);
        $customer->syncDailyItems([['item_type' => 'product', 'item_id' => $product->id, 'quantity' => 1]]);

        $this->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee('isDaily: true', false);
    }
}
