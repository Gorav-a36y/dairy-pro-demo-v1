<?php

namespace Database\Seeders;

use App\Models\BatchProduction;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\MilkCollection;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\Waste;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DummyDateRangeSeeder extends Seeder
{
    // Change this if you want a longer/shorter test window.
    protected int $days = 90;

    protected Carbon $start;
    protected Carbon $end;

    public function run(): void
    {
        $this->start = now()->subDays($this->days)->startOfDay();
        $this->end   = now()->endOfDay();

        $suppliers    = $this->seedSuppliers();
        $customers    = $this->seedCustomers();
        $rawMaterials = $this->seedRawMaterials();
        $products     = $this->seedProducts();

        $counts = [
            'milk_collections'      => 0,
            'sales'                 => 0,
            'wastes'                => 0,
            'customer_transactions' => 0,
            'supplier_transactions' => 0,
            'batch_productions'     => 0,
        ];

        $cursor = $this->start->copy();

        while ($cursor->lte($this->end)) {

            // --- Milk Collection (most days) ---
            if (rand(1, 10) <= 8) {
                foreach (range(1, rand(1, 2)) as $i) {
                    $this->seedMilkCollection($cursor, $suppliers, $rawMaterials);
                    $counts['milk_collections']++;
                }
            }

            // --- Sales (most days) ---
            if (rand(1, 10) <= 8) {
                foreach (range(1, rand(1, 2)) as $i) {
                    $this->seedSale($cursor, $customers, $products, $rawMaterials);
                    $counts['sales']++;
                }
            }

            // --- Waste (occasional) ---
            if (rand(1, 10) <= 3) {
                $this->seedWaste($cursor, $products, $rawMaterials);
                $counts['wastes']++;
            }

            // --- Customer transactions (occasional payment on top of khata sales) ---
            if (rand(1, 10) <= 3) {
                $this->seedCustomerPayment($cursor, $customers);
                $counts['customer_transactions']++;
            }

            // --- Supplier payment (occasional, on top of purchase txns from milk collection) ---
            if (rand(1, 10) <= 3) {
                $this->seedSupplierPayment($cursor, $suppliers);
                $counts['supplier_transactions']++;
            }

            // --- Batch production (every ~4 days) ---
            if (rand(1, 10) <= 2) {
                $this->seedBatchProduction($cursor, $products, $rawMaterials);
                $counts['batch_productions']++;
            }

            $cursor->addDay();
        }

        $this->printSummary($counts);
    }

    protected function seedSuppliers()
    {
        $names = ['Ahmed Dairy Farm', 'Green Valley Suppliers', 'Al-Noor Milk Co.', 'Sindh Farms', 'City Traders'];
        return collect($names)->map(fn ($name, $i) => Supplier::firstOrCreate(
            ['name' => $name],
            ['phone' => '030'.rand(10000000, 99999999), 'address' => 'Test Address '.($i + 1)]
        ));
    }

    protected function seedCustomers()
    {
        $names = ['Bilal Store', 'Fatima General Store', 'Karim Hotel', 'Sana Bakery', 'Zubair Mart'];
        return collect($names)->map(fn ($name, $i) => Customer::firstOrCreate(
            ['name' => $name],
            [
                'phone' => '031'.rand(10000000, 99999999),
                'email' => null,
                'address' => 'Test Customer Address '.($i + 1),
            ]
        ));
    }

    protected function seedRawMaterials()
    {
        $items = [
            ['name' => 'Raw Milk', 'unit' => 'Liter', 'is_milk' => true],
            ['name' => 'Sugar', 'unit' => 'Kilogram', 'is_milk' => false],
            ['name' => 'Packaging Box', 'unit' => 'Piece', 'is_milk' => false],
            ['name' => 'Flavoring', 'unit' => 'Kilogram', 'is_milk' => false],
        ];

        return collect($items)->map(fn ($item) => RawMaterial::firstOrCreate(
            ['name' => $item['name']],
            [
                'unit' => $item['unit'],
                'is_milk' => $item['is_milk'],
                'selling_price' => 0,
                'stock_qty' => 500,
                'is_active' => true,
            ]
        ));
    }

    protected function seedProducts()
    {
        $items = [
            ['name' => 'Yogurt 500g', 'unit' => 'Packet', 'selling_price' => 150],
            ['name' => 'Ghee 1L', 'unit' => 'Bottle', 'selling_price' => 900],
            ['name' => 'Butter 250g', 'unit' => 'Piece', 'selling_price' => 400],
        ];

        return collect($items)->map(fn ($item) => Product::firstOrCreate(
            ['name' => $item['name']],
            [
                'unit' => $item['unit'],
                'selling_price' => $item['selling_price'],
                'stock_qty' => 100,
                'output_qty_per_batch' => 10,
                'is_active' => true,
            ]
        ));
    }

    protected function randomTimeOn(Carbon $day): Carbon
    {
        return $day->copy()->setTime(rand(6, 20), rand(0, 59));
    }

    protected function seedMilkCollection(Carbon $day, $suppliers, $rawMaterials): void
    {
        $supplier = $suppliers->random();
        $rawMaterial = $rawMaterials->firstWhere('is_milk', true) ?? $rawMaterials->random();

        $qty = rand(20, 100);
        $price = rand(180, 220);
        $totalAmount = $qty * $price;

        // Mostly cash (fully paid), occasionally left partly/fully unpaid —
        // so seeded data actually exercises Khata like real usage would.
        $roll = rand(1, 10);
        $paidAmount = $roll <= 7
            ? $totalAmount               // fully paid — no khata debt
            : ($roll <= 9
                ? round($totalAmount * (rand(30, 70) / 100), 2) // partial khata
                : 0);                    // fully on khata

        MilkCollection::create([
            'supplier_id' => $supplier->id,
            'raw_material_id' => $rawMaterial->id,
            'quantity' => $qty,
            'unit' => $rawMaterial->unit,
            'purchase_price' => $price,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'payment_method' => $paidAmount >= $totalAmount ? 'cash' : ($paidAmount > 0 ? 'partial_khata' : 'khata'),
            'fat' => 4.5,
            'snf' => 8.5,
            'total_solids' => 13.0,
            'shift' => ['morning', 'evening'][rand(0, 1)],
            'collected_at' => $this->randomTimeOn($day),
            'notes' => 'Seeded test data',
        ]);

        // Same rule as MilkCollectionController: only the unpaid remainder
        // becomes Khata debt. A fully-paid collection never touches Khata.
        $remaining = round($totalAmount - $paidAmount, 2);

        if ($remaining > 0) {
            SupplierTransaction::create([
                'supplier_id' => $supplier->id,
                'type' => SupplierTransaction::TYPE_PURCHASE,
                'amount' => $remaining,
                'reference' => 'Seeded Milk Collection',
                'notes' => 'Seeded test data',
                'transaction_date' => $day->toDateString(),
            ]);
        }
    }

    protected function seedSale(Carbon $day, $customers, $products, $rawMaterials): void
    {
        $customer = rand(1, 10) <= 7 ? $customers->random() : null;
        $product = $products->random();
        $qty = rand(1, 10);
        $price = (float) $product->selling_price;
        $total = round($qty * $price, 2);

        $isKhata = $customer && rand(1, 10) <= 3;

        $sale = Sale::create([
            'invoice_no' => 'INV-'.$day->format('Ymd').'-'.rand(1000, 9999),
            'customer_id' => $customer?->id,
            'user_id' => null,
            'sale_date' => $day->toDateString(),
            'subtotal' => $total,
            'discount' => 0,
            'total_amount' => $total,
            'paid_amount' => $isKhata ? 0 : $total,
            'amount_tendered' => $isKhata ? null : $total,
            'payment_method' => $isKhata ? 'khata' : 'cash',
            'payment_status' => $isKhata ? 'unpaid' : 'paid',
            'source' => 'pos',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'item_type' => 'product',
            'item_id' => $product->id,
            'item_name' => $product->name,
            'unit' => $product->unit,
            'quantity' => $qty,
            'unit_price' => $price,
            'discount' => 0,
            'subtotal' => $total,
        ]);

        if ($isKhata) {
            CustomerTransaction::create([
                'customer_id' => $customer->id,
                'type' => CustomerTransaction::TYPE_KHATA,
                'amount' => $total,
                'invoice_no' => $sale->invoice_no,
                'notes' => 'Seeded test data',
                'transaction_date' => $day->toDateString(),
            ]);
        }
    }

    protected function seedWaste(Carbon $day, $products, $rawMaterials): void
    {
        $useRaw = rand(0, 1) === 1;
        $model = $useRaw ? $rawMaterials->random() : $products->random();

        Waste::create([
            'item_type' => $useRaw ? Waste::TYPE_RAW_MATERIAL : Waste::TYPE_PRODUCT,
            'item_id' => $model->id,
            'item_name' => $model->name,
            'unit' => $model->unit,
            'quantity' => rand(1, 5),
            'note' => 'Seeded test waste',
            'wasted_at' => $day->toDateString(),
        ]);
    }

    protected function seedCustomerPayment(Carbon $day, $customers): void
    {
        $customer = $customers->random();
        $balance = $customer->currentBalance();

        // Never seed a payment bigger than what the customer actually owes.
        if ($balance <= 0) {
            return;
        }

        $amount = min($balance, rand(500, 3000));

        CustomerTransaction::create([
            'customer_id' => $customer->id,
            'type' => CustomerTransaction::TYPE_PAYMENT,
            'amount' => $amount,
            'invoice_no' => null,
            'notes' => 'Seeded test payment',
            'transaction_date' => $day->toDateString(),
        ]);
    }

    protected function seedSupplierPayment(Carbon $day, $suppliers): void
    {
        $supplier = $suppliers->random();
        $balance = $supplier->currentBalance();

        // Never seed a payment bigger than what we actually owe the supplier.
        if ($balance <= 0) {
            return;
        }

        $amount = min($balance, rand(1000, 5000));

        SupplierTransaction::create([
            'supplier_id' => $supplier->id,
            'type' => SupplierTransaction::TYPE_PAYMENT,
            'amount' => $amount,
            'reference' => null,
            'notes' => 'Seeded test payment',
            'transaction_date' => $day->toDateString(),
        ]);
    }

    protected function seedBatchProduction(Carbon $day, $products, $rawMaterials): void
    {
        $product = $products->random();
        $multiplier = rand(1, 3);
        $outputQty = round($product->output_qty_per_batch * $multiplier, 2);
        $batchCost = round(rand(500, 2000) * $multiplier, 2);
        $costPerUnit = $outputQty > 0 ? round($batchCost / $outputQty, 2) : 0;

        $batch = new BatchProduction([
            'product_id' => $product->id,
            'user_id' => null,
            'multiplier' => $multiplier,
            'output_qty' => $outputQty,
            'batch_cost' => $batchCost,
            'cost_per_unit' => $costPerUnit,
            'status' => 'completed',
            'notes' => 'Seeded test batch',
        ]);
        $batch->timestamps = false;
        $batch->created_at = $this->randomTimeOn($day);
        $batch->updated_at = $batch->created_at;
        $batch->save();

        $movement = new StockMovement([
            'item_type' => 'product',
            'item_id' => $product->id,
            'direction' => 'in',
            'quantity' => $outputQty,
            'reason' => 'Seeded batch production output',
        ]);
        $movement->timestamps = false;
        $movement->created_at = $batch->created_at;
        $movement->updated_at = $batch->created_at;
        $movement->save();
    }

    protected function printSummary(array $counts): void
    {
        $this->command?->info('--- Dummy Data Seeded ---');
        $this->command?->info('Date range: '.$this->start->format('Y-m-d').' to '.$this->end->format('Y-m-d'));
        foreach ($counts as $table => $count) {
            $this->command?->info(str_pad($table, 25).': '.$count.' rows');
        }
        $this->command?->info('--------------------------');
        $this->command?->info('Use these two dates as your From/To filters on each CSV page to verify row counts.');
    }
}