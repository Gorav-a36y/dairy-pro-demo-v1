<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Small helpers shared by the feature tests.
 * They only fill columns that really exist, so they keep working
 * if a table gets a new optional column later.
 */
trait DairyTestHelpers
{
    protected function loginUser(): User
    {
        $this->withoutVite();

        $user = User::unguarded(fn () => User::create([
            'name'     => 'Test Owner',
            'email'    => 'owner' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]));

        $this->actingAs($user);

        return $user;
    }

    /** Insert only the columns that exist on the table. */
    protected function forceCreateFiltered(string $modelClass, array $attributes)
    {
        $table   = (new $modelClass)->getTable();
        $columns = Schema::getColumnListing($table);

        return $modelClass::unguarded(
            fn () => $modelClass::create(array_intersect_key($attributes, array_flip($columns)))
        );
    }

    protected function makePaymentMethods(): void
    {
        $rows = [
            ['code' => 'cash',                           'name' => 'Cash'],
            ['code' => PaymentMethod::KHATA_CODE,         'name' => 'Khata'],
            ['code' => PaymentMethod::PARTIAL_KHATA_CODE, 'name' => 'Partial Khata'],
        ];

        foreach ($rows as $i => $row) {
            if (PaymentMethod::where('code', $row['code'])->exists()) {
                continue;
            }

            $this->forceCreateFiltered(PaymentMethod::class, $row + [
                'label'      => $row['name'],
                'is_active'  => true,
                'sort_order' => $i + 1,
            ]);
        }
    }

    protected function makeProduct(array $overrides = []): Product
    {
        return $this->forceCreateFiltered(Product::class, $overrides + [
            'name'                 => 'Product ' . uniqid(),
            'unit'                 => 'Liter',
            'selling_price'        => 100,
            'stock_qty'            => 50,
            'output_qty_per_batch' => 10,
            'is_active'            => true,
        ]);
    }

    protected function makeRawMaterial(array $overrides = []): RawMaterial
    {
        return $this->forceCreateFiltered(RawMaterial::class, $overrides + [
            'name'          => 'Raw ' . uniqid(),
            'unit'          => 'Kilogram',
            'is_milk'       => false,
            'selling_price' => 50,
            'stock_qty'     => 50,
            'is_active'     => true,
        ]);
    }

    protected function makeCustomer(array $overrides = []): Customer
    {
        return $this->forceCreateFiltered(Customer::class, $overrides + [
            'name'              => 'Customer ' . uniqid(),
            'phone'             => '03' . random_int(100000000, 999999999),
            'is_daily_customer' => false,
            'balance'           => 0,
        ]);
    }

    protected function makeSupplier(array $overrides = []): Supplier
    {
        return $this->forceCreateFiltered(Supplier::class, $overrides + [
            'name'    => 'Supplier ' . uniqid(),
            'phone'   => '03' . random_int(100000000, 999999999),
            'balance' => 0,
        ]);
    }
}
