@extends('layouts.app')
@section('title', 'Make a Product')
@section('page-title', 'Make a Product')

@section('content')
@php
    $ingredientOptions = $ingredients->map(fn($i) => ['id' => $i->id, 'label' => $i->name, 'sublabel' => $i->unit, 'unit' => $i->unit, 'cost' => (float) $i->cost_per_unit]);
@endphp

<form method="POST" action="{{ route('products.store') }}"
      x-data="{
        ingredientOptions: {{ $ingredientOptions->toJson() }},
        rows: [{ ingredient_id: '', quantity_required: '' }],
        outputQty: '',
        ingredientInfo(id) { return this.ingredientOptions.find(i => i.id == id) },
        lineCost(row) { const ing = this.ingredientInfo(row.ingredient_id); return ing ? (parseFloat(row.quantity_required)||0) * ing.cost : 0 },
        get productionCost() { return this.rows.reduce((s, r) => s + this.lineCost(r), 0) },
        get costPerUnit() { const out = parseFloat(this.outputQty) || 0; return out > 0 ? this.productionCost / out : 0 },
      }"
      class="space-y-6 max-w-3xl">
    @csrf

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <h3 class="font-serif text-xl font-semibold text-ink mb-1">Product Details</h3>
        <p class="text-sm text-outline mb-6">The item you'll sell to customers (e.g. Dahi, Yogurt, Butter).</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Product Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Dahi (Yogurt)"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Unit</label>
                <select name="unit" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                    @foreach(\App\Models\Product::UNITS as $u)
                        <option value="{{ $u }}" @selected(old('unit', 'Kilogram') == $u)>{{ $u }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Selling Price ({{ $settings->currency ?? 'Rs.' }})</label>
                <input type="number" step="0.01" name="selling_price" value="{{ old('selling_price') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <h3 class="font-serif text-xl font-semibold text-ink mb-1">Recipe</h3>
        <p class="text-sm text-outline mb-5">Which raw materials — and how much of each — this product is made from.</p>

        <div class="space-y-3">
            <template x-for="(row, index) in rows" :key="index">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                    <div class="sm:col-span-5">
                        <select :name="`ingredients[${index}][ingredient_id]`" x-model="row.ingredient_id"
                                class="w-full rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            <option value="">Select raw material...</option>
                            @foreach($ingredients as $ingredient)
                                <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <input type="number" step="0.01" :name="`ingredients[${index}][quantity_required]`" x-model="row.quantity_required"
                               placeholder="Qty per batch" class="w-full rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                    </div>
                    <div class="sm:col-span-2">
                        <input type="text" disabled :value="ingredientInfo(row.ingredient_id)?.unit || ''" placeholder="Unit"
                               class="w-full rounded-control border border-outline-variant bg-surface-container-low px-4 py-2.5 text-sm text-outline">
                    </div>
                    <div class="sm:col-span-2">
                        <input type="text" disabled :value="'{{ $settings->currency ?? 'Rs.' }} ' + lineCost(row).toFixed(2)" placeholder="Cost"
                               class="w-full rounded-control border border-outline-variant bg-surface-container-low px-4 py-2.5 text-sm text-outline tabular">
                    </div>
                    <div class="sm:col-span-1 text-right">
                        <button type="button" @click="rows.splice(index, 1)" class="p-2.5 rounded-control text-outline hover:text-clay hover:bg-clay-container transition">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 mt-2 text-[11px] font-semibold text-outline uppercase tracking-wide">
            <div class="sm:col-span-5">Ingredient</div>
            <div class="sm:col-span-2">Quantity per batch</div>
            <div class="sm:col-span-2">Unit (auto)</div>
            <div class="sm:col-span-2">Ingredient cost (auto)</div>
        </div>

        <button type="button" @click="rows.push({ingredient_id: '', quantity_required: ''}); $nextTick(() => lucide.createIcons())"
                class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-forest-700 hover:text-forest-800">
            <i data-lucide="plus-circle" class="h-4 w-4"></i> Add Raw Material Row
        </button>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <h3 class="font-serif text-xl font-semibold text-ink mb-1">Production</h3>
        <p class="text-sm text-outline mb-5">This runs immediately when you save — raw materials above are deducted and this product's stock is created.</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Recipe Output / Yield</label>
                <input type="number" step="0.01" min="0.01" name="output_qty_per_batch" x-model="outputQty" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">e.g. this recipe makes 8 Liters of Dahi.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Production Cost (auto)</label>
                <div class="w-full rounded-control border border-outline-variant bg-surface-container-low px-4 py-3 text-sm font-semibold text-ink tabular">
                    {{ $settings->currency ?? 'Rs.' }} <span x-text="productionCost.toFixed(2)"></span>
                </div>
                <p class="text-[11px] text-outline mt-1">Cost/unit: {{ $settings->currency ?? 'Rs.' }} <span x-text="costPerUnit.toFixed(2)"></span></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Production Date</label>
                <input type="text" disabled value="{{ now()->format('M j, Y g:i A') }}"
                       class="w-full rounded-control border border-outline-variant bg-surface-container-low px-4 py-3 text-sm text-outline">
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="flask-conical" class="h-4 w-4"></i> Make the Product
        </button>
        <a href="{{ route('products.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
@endsection
