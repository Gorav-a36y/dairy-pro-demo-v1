<?php

namespace Tests\Feature;

use App\Models\CustomerTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DairyTestHelpers;
use Tests\TestCase;

class KhataTest extends TestCase
{
    use RefreshDatabase, DairyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginUser();
    }

    private function khata($customer, float $amount, string $date): CustomerTransaction
    {
        return CustomerTransaction::create([
            'customer_id'      => $customer->id,
            'type'             => CustomerTransaction::TYPE_KHATA,
            'amount'           => $amount,
            'invoice_no'       => 'T-' . uniqid(),
            'transaction_date' => $date,
        ]);
    }

    public function test_payment_is_recorded_and_balance_goes_down(): void
    {
        $customer = $this->makeCustomer();
        $this->khata($customer, 500, now()->toDateString());

        $this->post(route('khata.customer.store'), [
            'customer_id' => $customer->id,
            'amount'      => 200,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(300, $customer->fresh()->currentBalance());
    }

    public function test_payment_bigger_than_balance_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $this->khata($customer, 100, now()->toDateString());

        $this->post(route('khata.customer.store'), [
            'customer_id' => $customer->id,
            'amount'      => 500,
        ])->assertSessionHas('error');

        $this->assertEquals(100, $customer->fresh()->currentBalance());
    }

    public function test_old_receipt_shows_the_balance_of_that_day_not_todays_balance(): void
    {
        $customer = $this->makeCustomer();

        $this->khata($customer, 1000, now()->subDays(5)->toDateString());

        $payment = CustomerTransaction::create([
            'customer_id'      => $customer->id,
            'type'             => CustomerTransaction::TYPE_PAYMENT,
            'amount'           => 400,
            'invoice_no'       => 'CUS-OLD',
            'transaction_date' => now()->subDays(4)->toDateString(),
        ]);

        // A new purchase after that payment must not change the old receipt.
        $this->khata($customer, 777, now()->toDateString());

        $response = $this->get(route('khata.customer.receipt', ['transaction' => $payment->id]));

        $response->assertOk();
        $response->assertSee('600');       // 1000 - 400 on that day
        $response->assertDontSee('1,377'); // today's balance (1000 - 400 + 777)
    }
}
