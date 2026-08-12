@php
    $isEdit = isset($product);
    $existingRows = $isEdit
        ? $product->ingredients->map(fn($i) => ['ingredient_id' => $i->id, 'quantity_required' => $i->pivot->quantity_required])->values()
        : collect();
@endphp

<form method="POST" action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}"
      x-data="{ rows: {{ $existingRows->isEmpty() ? '[{ingredient_id: \'\', quantity_required: \'\'}]' : $existingRows->toJson() }} }"
      class="space-y-6">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <h3 class="font-serif text-xl font-semibold text-ink mb-6">Product Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Product Name</label>
                <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Unit</label>
                <select name="unit" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                    @foreach(\App\Models\Product::UNITS as $u)
                        <option value="{{ $u }}" @selected(old('unit', $product->unit ?? 'Liter') == $u)>{{ $u }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Current Stock Qty</label>
                <input type="number" step="0.01" name="stock_qty" value="{{ old('stock_qty', $product->stock_qty ?? 0) }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Purchase Price ({{ $settings->currency ?? 'Rs.' }})</label>
                <input type="number" step="0.01" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">What you pay to acquire/produce one unit.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Selling Price ({{ $settings->currency ?? 'Rs.' }})</label>
                <input type="number" step="0.01" name="selling_price" value="{{ old('selling_price', $product->selling_price ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div class="flex items-center gap-2.5 pt-6">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) id="is_active"
                       class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700">
                <label for="is_active" class="text-sm text-ink-variant">Active (visible for sale)</label>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <h3 class="font-serif text-xl font-semibold text-ink mb-1">Recipe — Required Raw Materials</h3>
        <p class="text-sm text-outline mb-5">Used for Batch Production. Optional — leave empty if this product isn't manufactured from raw materials.</p>

        <div class="space-y-3">
            <template x-for="(row, index) in rows" :key="index">
                <div class="flex items-center gap-3">
                    <select :name="`ingredients[${index}][ingredient_id]`" x-model="row.ingredient_id"
                            class="flex-1 rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                        <option value="">Select raw material...</option>
                        @foreach($ingredients as $ingredient)
                            <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit }})</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" :name="`ingredients[${index}][quantity_required]`" x-model="row.quantity_required"
                           placeholder="Qty needed" class="w-40 rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                    <button type="button" @click="rows.splice(index, 1)" class="p-2.5 rounded-control text-outline hover:text-clay hover:bg-clay-container transition shrink-0">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
            </template>
        </div>

        <button type="button" @click="rows.push({ingredient_id: '', quantity_required: ''}); $nextTick(() => lucide.createIcons())"
                class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-forest-700 hover:text-forest-800">
            <i data-lucide="plus-circle" class="h-4 w-4"></i> Add Raw Material Row
        </button>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Product' : 'Save Product' }}
        </button>
        <a href="{{ route('products.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
