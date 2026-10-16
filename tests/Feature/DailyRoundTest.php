<?php

namespace Tests\Feature;

use App\Models\CustomerTransaction;
use App\Models\DailyRound;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DairyTestHelpers;
use Tests\TestCase;

class DailyRoundTest extends TestCase
{
    use RefreshDatabase, DairyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginUser();
        $this->makePaymentMethods();
    }

    /** A daily customer with two daily items. */
    private function dailyCustomerWith($itemA, $itemB, $qtyA = 2, $qtyB = 1)
    {
        $customer = $this->makeCustomer(['is_daily_customer' => true]);
        $customer->syncDailyItems([
            ['item_type' => 'product', 'item_id' => $itemA->id, 'quantity' => $qtyA],
            ['item_type' => 'product', 'item_id' => $itemB->id, 'quantity' => $qtyB],
        ]);

        return $customer->fresh();
    }

    private function deliver($customer, array $quantities)
    {
        return $this->post(route('daily-round.store'), [
            'deliveries' => [$customer->id => $quantities],
        ]);
    }

    private function round($customer): ?DailyRound
    {
        return DailyRound::where('customer_id', $customer->id)
            ->whereDate('round_date', now()->toDateString())
            ->first();
    }

    public function test_all_items_delivered_makes_one_sale_and_one_khata_row(): void
    {
        $a = $this->makeProduct(['name' => 'Milk', 'selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['name' => 'Yogurt', 'selling_price' => 50, 'stock_qty' => 20]);
        $customer = $this->dailyCustomerWith($a, $b);

        $this->deliver($customer, ['product:' . $a->id => 2, 'product:' . $b->id => 1]);

        $sale = Sale::firstOrFail();
        $this->assertSame(Sale::SOURCE_DAILY_ROUND, $sale->source);
        $this->assertEquals(250, $sale->total_amount);
        $this->assertSame(2, SaleItem::count());
        $this->assertSame(1, CustomerTransaction::where('sale_id', $sale->id)->count());
        $this->assertSame(DailyRound::STATUS_DELIVERED, $this->round($customer)->status);
        $this->assertEquals(18, $a->fresh()->stock_qty);
    }

    public function test_out_of_stock_item_is_blocked_but_the_other_item_is_delivered(): void
    {
        $a = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['selling_price' => 50, 'stock_qty' => 0]);
        $customer = $this->dailyCustomerWith($a, $b);

        $this->deliver($customer, ['product:' . $a->id => 2, 'product:' . $b->id => 1]);

        $this->assertSame(1, SaleItem::count());
        $this->assertEquals(200, Sale::firstOrFail()->total_amount);
        $this->assertSame(DailyRound::STATUS_PENDING, $this->round($customer)->status); // not fully delivered
        $this->assertEquals(0, $b->fresh()->stock_qty);
    }

    public function test_low_stock_item_is_blocked_and_not_partly_delivered(): void
    {
        $a = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['selling_price' => 50, 'stock_qty' => 3]);
        $customer = $this->dailyCustomerWith($a, $b, 2, 5);

        $this->deliver($customer, ['product:' . $a->id => 2, 'product:' . $b->id => 5]);

        $this->assertSame(1, SaleItem::count());
        $this->assertEquals(3, $b->fresh()->stock_qty);
    }

    public function test_operator_can_lower_the_quantity_to_what_is_in_stock(): void
    {
        $a = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['selling_price' => 50, 'stock_qty' => 3]);
        $customer = $this->dailyCustomerWith($a, $b, 2, 5);

        $this->deliver($customer, ['product:' . $a->id => 2, 'product:' . $b->id => 3]);

        $this->assertSame(2, SaleItem::count());
        $this->assertEquals(0, $b->fresh()->stock_qty);
        $this->assertSame(DailyRound::STATUS_DELIVERED, $this->round($customer)->status);
    }

    public function test_inactive_item_is_blocked(): void
    {
        $a = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['selling_price' => 50, 'stock_qty' => 20, 'is_active' => false]);
        $customer = $this->dailyCustomerWith($a, $b);

        $this->deliver($customer, ['product:' . $a->id => 2, 'product:' . $b->id => 1]);

        $this->assertSame(1, SaleItem::count());
        $this->assertEquals(20, $b->fresh()->stock_qty);
    }

    public function test_blocked_item_can_be_delivered_later_after_restock(): void
    {
        $a = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['selling_price' => 50, 'stock_qty' => 0]);
        $customer = $this->dailyCustomerWith($a, $b);

        $this->deliver($customer, ['product:' . $a->id => 2, 'product:' . $b->id => 1]);

        $b->forceFill(['stock_qty' => 10])->save();

        // Item A is not delivered twice. Item B now goes through.
        $this->deliver($customer->fresh(), ['product:' . $a->id => 2, 'product:' . $b->id => 1]);

        $this->assertSame(2, SaleItem::count()); // A once, B once
        $this->assertEquals(18, $a->fresh()->stock_qty);
        $this->assertEquals(9, $b->fresh()->stock_qty);
        $this->assertSame(DailyRound::STATUS_DELIVERED, $this->round($customer)->status);
    }

    public function test_second_save_does_not_deliver_twice(): void
    {
        $a = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['selling_price' => 50, 'stock_qty' => 20]);
        $customer = $this->dailyCustomerWith($a, $b);
        $q = ['product:' . $a->id => 2, 'product:' . $b->id => 1];

        $this->deliver($customer, $q);
        $this->deliver($customer->fresh(), $q);

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, CustomerTransaction::count());
        $this->assertEquals(18, $a->fresh()->stock_qty);
    }

    public function test_skip_is_saved_with_a_reason(): void
    {
        $a = $this->makeProduct();
        $b = $this->makeProduct();
        $customer = $this->dailyCustomerWith($a, $b);

        $this->post(route('daily-round.store'), [
            'skipped'     => [$customer->id => '1'],
            'skip_reason' => [$customer->id => 'Not at home'],
        ]);

        $round = $this->round($customer);
        $this->assertSame(DailyRound::STATUS_SKIPPED, $round->status);
        $this->assertSame('Not at home', $round->skip_reason);
    }

    public function test_skip_reason_is_optional(): void
    {
        $customer = $this->dailyCustomerWith($this->makeProduct(), $this->makeProduct());

        $this->post(route('daily-round.store'), ['skipped' => [$customer->id => '1']]);

        $this->assertSame(DailyRound::STATUS_SKIPPED, $this->round($customer)->status);
        $this->assertNull($this->round($customer)->skip_reason);
    }

    public function test_skipped_customer_can_be_logged_later_the_same_day(): void
    {
        $a = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['selling_price' => 50, 'stock_qty' => 20]);
        $customer = $this->dailyCustomerWith($a, $b);

        $this->post(route('daily-round.store'), [
            'skipped'     => [$customer->id => '1'],
            'skip_reason' => [$customer->id => 'Not at home'],
        ]);

        $this->deliver($customer->fresh(), ['product:' . $a->id => 2, 'product:' . $b->id => 1]);

        $round = $this->round($customer);
        $this->assertSame(DailyRound::STATUS_DELIVERED, $round->status);
        $this->assertNull($round->skip_reason);
        $this->assertSame(1, Sale::count());
    }

    public function test_skipped_customer_stays_skipped_if_nothing_can_be_delivered(): void
    {
        $a = $this->makeProduct(['stock_qty' => 0]);
        $b = $this->makeProduct(['stock_qty' => 0]);
        $customer = $this->dailyCustomerWith($a, $b);

        $this->post(route('daily-round.store'), ['skipped' => [$customer->id => '1']]);
        $this->deliver($customer->fresh(), ['product:' . $a->id => 2, 'product:' . $b->id => 1]);

        $this->assertSame(DailyRound::STATUS_SKIPPED, $this->round($customer)->status);
        $this->assertSame(0, Sale::count());
    }

    public function test_page_shows_each_item_with_its_blocked_reason_and_log_button(): void
    {
        $a = $this->makeProduct(['name' => 'Fresh Milk', 'stock_qty' => 20]);
        $b = $this->makeProduct(['name' => 'Sweet Lassi', 'stock_qty' => 0]);
        $customer = $this->dailyCustomerWith($a, $b);

        $this->get(route('daily-round.index'))
            ->assertOk()
            ->assertSee('Fresh Milk')
            ->assertSee('Sweet Lassi')
            ->assertSee('Out of stock');

        $this->post(route('daily-round.store'), ['skipped' => [$customer->id => '1']]);

        $this->get(route('daily-round.index'))
            ->assertOk()
            ->assertSee('Log delivery');
    }

    public function test_history_balance_comes_from_the_khata_ledger(): void
    {
        $a = $this->makeProduct(['selling_price' => 100, 'stock_qty' => 20]);
        $b = $this->makeProduct(['selling_price' => 50, 'stock_qty' => 20]);
        $customer = $this->dailyCustomerWith($a, $b);

        $this->deliver($customer, ['product:' . $a->id => 2, 'product:' . $b->id => 1]); // 250 on khata

        $this->post(route('khata.customer.store'), [
            'customer_id' => $customer->id,
            'amount'      => 100,
        ])->assertSessionHasNoErrors();

        $this->get(route('daily-round.history', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertViewHas('summary', function ($summary) {
                return (float) $summary['total_amount'] === 250.0
                    && (float) $summary['total_paid'] === 100.0
                    && (float) $summary['balance'] === 150.0;
            });
    }
}