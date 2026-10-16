@php
    $isEdit = isset($product);

    $rawMaterialOptions = $rawMaterials->map(fn($rm) => [
        'id' => $rm->id,
        'label' => $rm->name,
        'sublabel' => $rm->unit,
        'unit' => $rm->unit,
        // Raw Material Cost (auto) = Raw Material SELLING price x Quantity Per Batch —
        // must use selling_price, not currentCostPerUnit() (the purchase price).
        'cost' => (float) $rm->selling_price,
    ]);

    // After a failed validation, bring back exactly the rows the user typed;
    // otherwise use the saved recipe (edit) or start empty (create).
    $oldRecipe = old('raw_materials');
    if (is_array($oldRecipe)) {
        $existingRows = collect($oldRecipe)->map(fn($r) => [
            'raw_material_id' => (string) ($r['raw_material_id'] ?? ''),
            'quantity_required' => $r['quantity_required'] ?? '',
        ])->values();
    } elseif ($isEdit) {
        $existingRows = $product->rawMaterials->map(fn($rm) => [
            'raw_material_id' => (string) $rm->id,
            'quantity_required' => (float) $rm->pivot->quantity_required,
        ])->values();
    } else {
        $existingRows = collect();
    }

    $editRouteTemplate = route('products.edit', ['product' => '___ID___']);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}"
      x-data="{
        rawMaterialOptions: {{ $rawMaterialOptions->toJson() }},
        rows: {{ $existingRows->isEmpty() ? '[{ raw_material_id: \'\', quantity_required: \'\' }]' : $existingRows->toJson() }},
        outputQty: {{ json_encode(old('output_qty_per_batch', $isEdit ? (float) $product->output_qty_per_batch : '')) }},
        rawMaterialInfo(id) { return this.rawMaterialOptions.find(rm => rm.id == id) },
        lineCost(row) { const rm = this.rawMaterialInfo(row.raw_material_id); return rm ? (parseFloat(row.quantity_required)||0) * rm.cost : 0 },

        name: {{ json_encode(old('name', $product->name ?? '')) }},
        allProducts: {{ json_encode($allProducts ?? []) }},
        nameOpen: false,
        get filteredProductsByName() {
            if (this.name.trim().length < 1) return [];
            const q = this.name.toLowerCase();
            return this.allProducts.filter(p => p.name.toLowerCase().includes(q));
        },
        get exactNameMatch() {
            return this.allProducts.find(p => p.name.toLowerCase() === this.name.toLowerCase().trim());
        },
        selectExistingName(p) {
            this.name = p.name;
            this.nameOpen = false;
        },
      }"
      class="space-y-6 max-w-3xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    {{-- 1. Product Details --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <h3 class="font-serif text-xl font-semibold text-ink mb-1">Product Details</h3>
        <p class="text-sm text-outline mb-6">The item you'll sell to customers (e.g. Dahi, Yogurt, Butter).</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2 relative" @click.outside="nameOpen = false">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Product Name</label>
                <input type="text" name="name" x-model="name" autocomplete="off" required placeholder="e.g. Dahi (Yogurt)"
                       @focus="nameOpen = true"
                       @input="nameOpen = true"
                       :class="exactNameMatch ? 'border-clay focus:ring-clay focus:border-clay' : 'border-outline-variant focus:ring-forest-700 focus:border-forest-700'"
                       class="w-full rounded-control border px-4 py-3 text-sm focus:outline-none focus:ring-2 transition">
                @error('name')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror

                <div x-show="nameOpen && filteredProductsByName.length > 0" x-cloak x-transition
                     class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                    <div class="px-4 py-2 text-[11px] font-semibold text-outline uppercase tracking-wide border-b border-outline-variant/60">
                        Already in database
                    </div>
                    <template x-for="p in filteredProductsByName" :key="p.id">
                        <button type="button" @click="selectExistingName(p)"
                                class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition"
                                :class="p.name.toLowerCase() === name.toLowerCase().trim() ? 'bg-clay-container/40 text-clay' : ''">
                            <span class="font-medium" :class="p.name.toLowerCase() === name.toLowerCase().trim() ? 'text-clay' : 'text-ink'" x-text="p.name"></span>
                        </button>
                    </template>
                </div>

                <div x-show="exactNameMatch" x-cloak x-transition
                     class="mt-2.5 p-3 rounded-control bg-clay-container/50 border border-clay/30 flex items-start gap-2">
                    <i data-lucide="alert-circle" class="h-4 w-4 text-clay shrink-0 mt-0.5"></i>
                    <div>
                        <p class="text-sm font-semibold text-clay">
                            "<span x-text="exactNameMatch?.name"></span>" already exists in your database.
                        </p>
                        <p class="text-xs text-clay/80 mt-0.5">
                            You cannot create a duplicate.
                            <a :href="'{{ $editRouteTemplate }}'.replace('___ID___', exactNameMatch.id)"
                               class="underline hover:text-clay/70 font-semibold">Edit it instead</a>.
                        </p>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Unit</label>
                @if($isEdit)
                    {{-- Unit is locked after creation. No name attribute, so it is never submitted;
                         the controller also ignores any unit sent to update(). --}}
                    <input type="text" value="{{ $product->unit }}" disabled
                           class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm bg-surface-container-low text-outline cursor-not-allowed">
                    <p class="text-[11px] text-outline mt-1">Unit cannot be changed after the product is created.</p>
                @else
                    <select name="unit" required class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                        @foreach(\App\Models\Product::UNITS as $u)
                            <option value="{{ $u }}" @selected(old('unit', 'Kilogram') == $u)>{{ $u }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Selling Price ({{ $settings->currency ?? 'Rs.' }})</label>
                <input type="number" step="0.01" min="0.01" name="selling_price" value="{{ old('selling_price', $product->selling_price ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                @error('selling_price')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror
            </div>

            @if($isEdit)
                <div>
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Current Stock Qty</label>
                    <input type="number" step="0.01" value="{{ $product->stock_qty }}" disabled
                           class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm bg-surface-container-low text-outline cursor-not-allowed">
                    <p class="text-[11px] text-outline mt-1">To add more stock, use the Batch Production page instead of editing this directly.</p>
                </div>
            @endif
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Recipe Output / Yield</label>
                <input type="number" step="0.01" min="0.01" name="output_qty_per_batch" x-model="outputQty" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">e.g. this recipe makes 8 Liters of Dahi per batch. Used by Batch Production to scale future runs.</p>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2.5 pt-2">
                {{-- Hidden fallback: unchecked checkboxes send nothing, so without this
                     is_active silently reverted to true on every save --}}
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $isEdit ? $product->is_active : true)) id="is_active"
                       class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700">
                <label for="is_active" class="text-sm text-ink-variant">Active (visible for sale)</label>
            </div>
        </div>
    </div>

    {{-- 2. Recipe --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <h3 class="font-serif text-xl font-semibold text-ink mb-1">Recipe</h3>
        <p class="text-sm text-outline mb-5">
            @if($isEdit)
                Editing this only changes the recipe definition — it does not produce new stock. Run a new batch from the Batch Production page.
            @else
                Which raw materials — and how much of each — this product is made from. Saving this only defines the recipe; it does not produce stock yet.
            @endif
        </p>

        <div class="space-y-3">
            <template x-for="(row, index) in rows" :key="index">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                    <div class="sm:col-span-5">
                        <select :name="`raw_materials[${index}][raw_material_id]`" x-model="row.raw_material_id" required
                                class="w-full rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            <option value="">Select raw material...</option>
                            @foreach($rawMaterials as $rawMaterial)
                                <option value="{{ $rawMaterial->id }}">
                                    {{ $rawMaterial->name }}{{ !$rawMaterial->is_active ? ' (inactive)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <input type="number" step="0.01" min="0.01" required
                               :name="`raw_materials[${index}][quantity_required]`" x-model="row.quantity_required"
                               placeholder="Qty per batch" class="w-full rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                    </div>
                    <div class="sm:col-span-2">
                        <input type="text" disabled :value="rawMaterialInfo(row.raw_material_id)?.unit || ''" placeholder="Unit"
                               class="w-full rounded-control border border-outline-variant bg-surface-container-low px-4 py-2.5 text-sm text-outline cursor-not-allowed opacity-70">
                    </div>
                    <div class="sm:col-span-2">
                        <input type="text" disabled :value="'{{ $settings->currency ?? 'Rs.' }} ' + lineCost(row).toFixed(2)" placeholder="Cost"
                               class="w-full rounded-control border border-outline-variant bg-surface-container-low px-4 py-2.5 text-sm text-outline tabular cursor-not-allowed opacity-70">
                    </div>
                    <div class="sm:col-span-1 text-right">
                        {{-- Remove button is hidden when only one row is left,
                             so the recipe always has at least one raw material --}}
                        <button type="button" x-show="rows.length > 1" @click="rows.splice(index, 1)"
                                class="p-2.5 rounded-control text-outline hover:text-clay hover:bg-clay-container transition">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 mt-2 text-[11px] font-semibold text-outline uppercase tracking-wide">
            <div class="sm:col-span-5">Raw Material</div>
            <div class="sm:col-span-2">Quantity per batch</div>
            <div class="sm:col-span-2">Unit (auto)</div>
            <div class="sm:col-span-2">Raw material cost (auto)</div>
        </div>

        <button type="button" @click="rows.push({raw_material_id: '', quantity_required: ''}); $nextTick(() => lucide.createIcons())"
                class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-forest-700 hover:text-forest-800">
            <i data-lucide="plus-circle" class="h-4 w-4"></i> Add Raw Material Row
        </button>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" :disabled="exactNameMatch && !{{ json_encode($isEdit) }}"
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i>
            {{ $isEdit ? 'Update Product' : 'Save Product' }}
        </button>
        <a href="{{ route('products.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
