<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DairyTestHelpers;
use Tests\TestCase;

class CustomerAndSupplierPhoneTest extends TestCase
{
    use RefreshDatabase, DairyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginUser();
    }

    public function test_customer_phone_is_saved_in_standard_form(): void
    {
        $this->post(route('customers.store'), [
            'name'  => 'Ali Traders',
            'phone' => '0300-1234567',
        ])->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', ['name' => 'Ali Traders', 'phone' => '03001234567']);
    }

    public function test_same_customer_phone_in_other_format_is_blocked(): void
    {
        $this->makeCustomer(['phone' => '03001234567']);

        $this->post(route('customers.store'), [
            'name'  => 'Second Customer',
            'phone' => '+92 300 1234567',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(1, Customer::count());
    }

    public function test_bad_customer_phone_is_rejected(): void
    {
        $this->post(route('customers.store'), ['name' => 'Bad Phone', 'phone' => 'abc'])
            ->assertSessionHasErrors('phone');

        $this->post(route('customers.store'), ['name' => 'Short Phone', 'phone' => '123'])
            ->assertSessionHasErrors('phone');
    }

    public function test_customer_and_supplier_can_share_one_number(): void
    {
        $this->makeSupplier(['phone' => '03001234567']);

        $this->post(route('customers.store'), [
            'name'  => 'Same Number Customer',
            'phone' => '0300-1234567',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', ['phone' => '03001234567']);

        $this->post(route('suppliers.store'), [
            'name'  => 'Other Supplier',
            'phone' => '03111234567',
        ])->assertSessionHasNoErrors();

        // and the reverse: a new supplier may use a number a customer already has
        $this->makeCustomer(['phone' => '03221234567']);

        $this->post(route('suppliers.store'), [
            'name'  => 'Reverse Supplier',
            'phone' => '0322-1234567',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['phone' => '03221234567']);
    }

    public function test_supplier_phone_must_be_unique_among_suppliers(): void
    {
        $this->makeSupplier(['phone' => '03001234567']);

        $this->post(route('suppliers.store'), [
            'name'  => 'Duplicate Supplier',
            'phone' => '0300 1234567',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(1, Supplier::count());
    }
}
