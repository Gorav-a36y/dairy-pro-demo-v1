<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function signIn(): void
    {
        $this->actingAs(User::unguarded(fn () => User::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ])));
    }

    protected function method(string $code): PaymentMethod
    {
        return PaymentMethod::where('code', $code)->firstOrFail();
    }

    protected function add(string $name)
    {
        return $this->from(route('settings.edit'))
            ->post(route('payment-methods.store'), ['name' => $name]);
    }

    // ---------------------------------------------------------------- page

    public function test_settings_page_lists_the_payment_methods(): void
    {
        $this->signIn();

        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Cash')
            ->assertSee('Khata')
            ->assertSee('Partial Khata');
    }

    // ---------------------------------------------------------------- add

    public function test_a_new_payment_method_is_saved_active_with_a_code(): void
    {
        $this->signIn();

        $this->add('Bank Transfer')->assertSessionHas('success');

        $this->assertDatabaseHas('payment_methods', [
            'name'       => 'Bank Transfer',
            'code'       => 'bank_transfer',
            'is_system'  => false,
            'is_active'  => true,
            'sort_order' => 2,
        ]);
    }

    public function test_name_is_required(): void
    {
        $this->signIn();

        $this->add('')->assertSessionHasErrors('name');
        $this->assertSame(3, PaymentMethod::count());
    }

    public function test_name_longer_than_50_characters_is_rejected(): void
    {
        $this->signIn();

        $this->add(str_repeat('a', 51))->assertSessionHasErrors('name');
        $this->assertSame(3, PaymentMethod::count());
    }

    public function test_name_that_slugs_to_an_existing_code_is_rejected(): void
    {
        $this->signIn();

        // "Partial-Khata" is a different name, but its code is "partial_khata".
        $this->add('Partial-Khata')->assertSessionHas('error');

        $this->assertSame(3, PaymentMethod::count());
        $this->assertSame('Partial Khata', $this->method('partial_khata')->name);
    }

    public function test_name_without_letters_or_numbers_is_rejected(): void
    {
        $this->signIn();

        $this->add('$$$')->assertSessionHas('error');

        $this->assertSame(3, PaymentMethod::count());
        $this->assertDatabaseMissing('payment_methods', ['code' => '']);
    }

    public function test_long_name_gets_a_code_that_fits_the_column(): void
    {
        $this->signIn();

        $this->add(str_repeat('a', 50))->assertSessionHas('success');

        $this->assertDatabaseHas('payment_methods', ['code' => str_repeat('a', 30)]);
    }

    public function test_two_long_names_with_the_same_cut_off_code_are_rejected(): void
    {
        $this->signIn();

        $this->add(str_repeat('a', 50))->assertSessionHas('success');
        $this->add(str_repeat('a', 49))->assertSessionHas('error');

        $this->assertSame(4, PaymentMethod::count());
    }

    // ---------------------------------------------------------------- toggle

    public function test_custom_method_can_be_deactivated_and_reactivated(): void
    {
        $this->signIn();
        $this->add('Bank Transfer');
        $bank = $this->method('bank_transfer');

        $this->patch(route('payment-methods.toggle', $bank))->assertSessionHas('success');
        $this->assertFalse($bank->fresh()->is_active);

        $this->patch(route('payment-methods.toggle', $bank))->assertSessionHas('success');
        $this->assertTrue($bank->fresh()->is_active);
    }

    public function test_cash_cannot_be_deactivated(): void
    {
        $this->signIn();

        $this->patch(route('payment-methods.toggle', $this->method('cash')))
            ->assertSessionHas('error');

        $this->assertTrue($this->method('cash')->is_active);
    }

    public function test_khata_cannot_be_deactivated(): void
    {
        $this->signIn();

        $this->patch(route('payment-methods.toggle', $this->method('khata')))
            ->assertSessionHas('error');

        $this->assertTrue($this->method('khata')->is_active);
    }

    public function test_partial_khata_cannot_be_deactivated(): void
    {
        $this->signIn();

        $this->patch(route('payment-methods.toggle', $this->method('partial_khata')))
            ->assertSessionHas('error');

        $this->assertTrue($this->method('partial_khata')->is_active);
    }

    // ---------------------------------------------------------------- model guard

    public function test_system_method_cannot_be_deleted_through_the_model(): void
    {
        $khata = $this->method('khata');

        try {
            $khata->delete();
            $this->fail('Deleting a system payment method should be blocked.');
        } catch (LogicException $e) {
            $this->assertDatabaseHas('payment_methods', ['code' => 'khata']);
        }
    }

    public function test_cash_cannot_be_deleted_through_the_model(): void
    {
        $cash = $this->method('cash');

        try {
            $cash->delete();
            $this->fail('Deleting Cash should be blocked.');
        } catch (LogicException $e) {
            $this->assertDatabaseHas('payment_methods', ['code' => 'cash']);
        }
    }

    public function test_cash_cannot_be_deactivated_through_the_model(): void
    {
        $cash = $this->method('cash');

        try {
            $cash->update(['is_active' => false]);
            $this->fail('Deactivating Cash should be blocked.');
        } catch (LogicException $e) {
            $this->assertTrue($this->method('cash')->is_active);
        }
    }

    public function test_system_method_cannot_be_deactivated_through_the_model(): void
    {
        $partial = $this->method('partial_khata');

        try {
            $partial->update(['is_active' => false]);
            $this->fail('Deactivating a system payment method should be blocked.');
        } catch (LogicException $e) {
            $this->assertTrue($this->method('partial_khata')->is_active);
        }
    }

    public function test_system_method_code_cannot_be_changed_through_the_model(): void
    {
        $khata = $this->method('khata');

        try {
            $khata->update(['code' => 'something_else']);
            $this->fail('Changing a system payment method code should be blocked.');
        } catch (LogicException $e) {
            $this->assertDatabaseHas('payment_methods', ['code' => 'khata']);
            $this->assertDatabaseMissing('payment_methods', ['code' => 'something_else']);
        }
    }

    public function test_system_method_name_can_still_be_saved_without_changing_rules(): void
    {
        // Saving other fields of a system method (still active, same code) is allowed.
        $khata = $this->method('khata');
        $khata->update(['sort_order' => 98]);

        $this->assertSame(98, $khata->fresh()->sort_order);
        $this->assertTrue($khata->fresh()->is_active);
    }

    // ---------------------------------------------------------------- access

    public function test_there_is_no_delete_route_for_payment_methods(): void
    {
        $this->signIn();
        $cash = $this->method('cash');

        $paths = [
            "/settings/payment-methods/{$cash->id}",
            "/settings/payment-methods/{$cash->id}/toggle",
            '/settings/payment-methods',
        ];

        foreach ($paths as $path) {
            $this->assertContains($this->delete($path)->status(), [404, 405]);
        }

        $this->assertDatabaseHas('payment_methods', ['code' => 'cash']);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->post(route('payment-methods.store'), ['name' => 'Bank Transfer'])
            ->assertRedirect(route('login'));

        $this->patch(route('payment-methods.toggle', $this->method('cash')))
            ->assertRedirect(route('login'));

        $this->assertSame(3, PaymentMethod::count());
        $this->assertTrue($this->method('cash')->is_active);
    }
}