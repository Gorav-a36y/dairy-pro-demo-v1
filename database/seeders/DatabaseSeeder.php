<?php

namespace Database\Seeders;

use App\Models\BatchProduction;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Ingredient;
use App\Models\MilkCollection;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(['id' => 1], [
            'dairy_name' => 'DairyPro',
            'phone' => '0300-1234567',
            'address' => 'Main Bazaar Road, Hyderabad, Sindh',
            'currency' => 'Rs.',
            'invoice_region' => 'Pakistan (PKT)',
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@gorav.click'],
            ['name' => 'Gorav Admin', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        // Raw materials used for batch production recipes
        $milkIngredient = Ingredient::firstOrCreate(['name' => 'Raw Milk'], ['unit' => 'Liter', 'stock_qty' => 500, 'cost_per_unit' => 120, 'reorder_level' => 100]);
        $culture = Ingredient::firstOrCreate(['name' => 'Yogurt Culture'], ['unit' => 'Gram', 'stock_qty' => 200, 'cost_per_unit' => 15, 'reorder_level' => 50]);
        $sugar = Ingredient::firstOrCreate(['name' => 'Sugar'], ['unit' => 'Kilogram', 'stock_qty' => 100, 'cost_per_unit' => 180, 'reorder_level' => 20]);
        $salt = Ingredient::firstOrCreate(['name' => 'Salt'], ['unit' => 'Kilogram', 'stock_qty' => 50, 'cost_per_unit' => 60, 'reorder_level' => 10]);
        $cream = Ingredient::firstOrCreate(['name' => 'Cream'], ['unit' => 'Liter', 'stock_qty' => 80, 'cost_per_unit' => 350, 'reorder_level' => 15]);

        // Products sold to customers
        $milkProduct = Product::firstOrCreate(
            ['name' => 'Fresh Toned Milk'],
            ['unit' => 'Liter', 'purchase_price' => 130, 'selling_price' => 180, 'stock_qty' => 40, 'output_qty_per_batch' => 100, 'is_active' => true]
        );
        $milkProduct->ingredients()->sync([$milkIngredient->id => ['quantity_required' => 100]]);

        $yogurt = Product::firstOrCreate(
            ['name' => 'Plain Yogurt'],
            ['unit' => 'Kilogram', 'purchase_price' => 190, 'selling_price' => 260, 'stock_qty' => 25, 'output_qty_per_batch' => 50, 'is_active' => true]
        );
        $yogurt->ingredients()->sync([
            $milkIngredient->id => ['quantity_required' => 45],
            $culture->id => ['quantity_required' => 25],
            $sugar->id => ['quantity_required' => 2],
        ]);

        $butter = Product::firstOrCreate(
            ['name' => 'Farm Butter'],
            ['unit' => 'Kilogram', 'purchase_price' => 700, 'selling_price' => 950, 'stock_qty' => 12, 'output_qty_per_batch' => 20, 'is_active' => true]
        );
        $butter->ingredients()->sync([
            $cream->id => ['quantity_required' => 25],
            $salt->id => ['quantity_required' => 1],
        ]);

        // Suppliers / farmers
        $suppliers = collect(['Ahmed Dairy Farm', 'Bashir Livestock', 'Noor Farms'])
            ->map(fn ($name) => Supplier::firstOrCreate(['name' => $name], [
                'phone' => '03' . rand(100000000, 999999999),
                'opening_balance' => 0,
                'is_active' => true,
            ]));

        // A few sample milk collections
        foreach ($suppliers as $supplier) {
            $qty = rand(20, 60);
            $price = 130;
            $total = $qty * $price;
            $paid = $total * 0.6;

            $collection = MilkCollection::firstOrCreate(
                ['supplier_id' => $supplier->id, 'product_id' => $milkProduct->id, 'collected_at' => Carbon::today()->setTime(7, 0)],
                ['quantity' => $qty, 'unit' => 'Liter', 'purchase_price' => $price, 'total_amount' => $total, 'paid_amount' => $paid, 'payment_method' => 'cash']
            );

            SupplierTransaction::firstOrCreate(
                ['supplier_id' => $supplier->id, 'reference' => 'Milk Collection #' . $collection->id, 'type' => 'purchase'],
                ['amount' => $total, 'transaction_date' => Carbon::today(), 'notes' => 'Seeded demo purchase']
            );
            SupplierTransaction::firstOrCreate(
                ['supplier_id' => $supplier->id, 'reference' => 'Milk Collection #' . $collection->id, 'type' => 'payment'],
                ['amount' => $paid, 'transaction_date' => Carbon::today(), 'notes' => 'Seeded demo payment']
            );
        }

        // Customers
        $customers = collect(['Ali General Store', 'Karachi Fresh Mart', 'Sunrise Bakery', 'Green Valley Hotel'])
            ->map(fn ($name) => Customer::firstOrCreate(['name' => $name], ['phone' => '03' . rand(100000000, 999999999)]));

        // Seed last 7 days of sales for the revenue trend chart
        $products = [$milkProduct, $yogurt, $butter];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $ordersToday = rand(1, 3);

            for ($o = 0; $o < $ordersToday; $o++) {
                $customer = $customers->random();
                $isPaid = rand(0, 4) > 0; // mostly paid, occasionally khata

                $sale = Sale::create([
                    'invoice_no' => 'INV-' . strtoupper(Str::random(8)),
                    'customer_id' => $customer->id,
                    'user_id' => $admin->id,
                    'sale_date' => $date,
                    'subtotal' => 0,
                    'discount' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'payment_method' => 'cash',
                    'payment_status' => $isPaid ? 'paid' : 'unpaid',
                ]);

                $total = 0;
                foreach ([1, 2] as $line) {
                    $product = $products[array_rand($products)];
                    $qty = rand(2, 8);
                    $subtotal = $qty * (float) $product->selling_price;
                    $total += $subtotal;

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $product->selling_price,
                        'subtotal' => $subtotal,
                    ]);
                }

                $sale->update([
                    'subtotal' => $total,
                    'total_amount' => $total,
                    'paid_amount' => $isPaid ? $total : 0,
                ]);

                if (! $isPaid) {
                    CustomerTransaction::create([
                        'customer_id' => $customer->id,
                        'type' => 'khata',
                        'amount' => $total,
                        'invoice_no' => $sale->invoice_no,
                        'notes' => 'Seeded demo khata sale',
                        'transaction_date' => $date,
                    ]);
                }
            }
        }

        BatchProduction::firstOrCreate(
            ['product_id' => $milkProduct->id, 'multiplier' => 1],
            [
                'user_id' => $admin->id,
                'output_qty' => 100,
                'batch_cost' => 12000,
                'cost_per_unit' => 120,
                'status' => 'completed',
                'notes' => 'Seeded demo batch',
            ]
        );
    }
}
