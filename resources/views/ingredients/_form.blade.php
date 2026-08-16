@php
    $isEdit = isset($ingredient);
    $currentId = $isEdit ? $ingredient->id : null;
    $editRouteTemplate = route('ingredients.edit', ['ingredient' => '___ID___']);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('ingredients.update', $ingredient) : route('ingredients.store') }}"
      x-data="{
          name: {{ json_encode(old('name', $ingredient->name ?? '')) }},
          allIngredients: {{ json_encode($allIngredients ?? []) }},
          open: false,

          get filteredIngredients() {
              if (this.name.trim().length < 1) return [];
              const q = this.name.toLowerCase();
              return this.allIngredients.filter(i => i.name.toLowerCase().includes(q));
          },

          get exactMatch() {
              return this.allIngredients.find(i => i.name.toLowerCase() === this.name.toLowerCase().trim());
          },

          selectExisting(ing) {
              this.name = ing.name;
              this.open = false;
          }
      }"
      class="max-w-2xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-5">
        {{-- Name with live duplicate search --}}
        <div @click.outside="open = false" class="relative">
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Raw Material Name</label>
            <div class="relative">
                <i data-lucide="search" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input type="text" name="name" x-model="name" autocomplete="off" required
                       placeholder="e.g. Raw Milk"
                       @focus="open = true"
                       @input="open = true"
                       :class="exactMatch ? 'border-clay focus:ring-clay focus:border-clay' : 'border-outline-variant focus:ring-forest-700 focus:border-forest-700'"
                       class="w-full rounded-control bg-white pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 transition">
            </div>

            {{-- Dropdown: existing raw materials --}}
            <div x-show="open && filteredIngredients.length > 0" x-cloak x-transition
                 class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                <div class="px-4 py-2 text-[11px] font-semibold text-outline uppercase tracking-wide border-b border-outline-variant/60">
                    Already in database
                </div>
                <template x-for="ing in filteredIngredients" :key="ing.id">
                    <button type="button" @click="selectExisting(ing)"
                            class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition"
                            :class="ing.name.toLowerCase() === name.toLowerCase().trim() ? 'bg-clay-container/40 text-clay' : ''">
                        <span class="font-medium" :class="ing.name.toLowerCase() === name.toLowerCase().trim() ? 'text-clay' : 'text-ink'" x-text="ing.name"></span>
                        <span class="text-xs text-outline" x-text="(ing.stock_qty ?? 0) + ' ' + ing.unit"></span>
                    </button>
                </template>
            </div>

            {{-- Exact duplicate warning --}}
            <div x-show="exactMatch" x-cloak x-transition
                 class="mt-2.5 p-3 rounded-control bg-clay-container/50 border border-clay/30 flex items-start gap-2">
                <i data-lucide="alert-circle" class="h-4 w-4 text-clay shrink-0 mt-0.5"></i>
                <div>
                    <p class="text-sm font-semibold text-clay">
                        “<span x-text="exactMatch?.name"></span>” already exists in your database.
                    </p>
                    <p class="text-xs text-clay/80 mt-0.5">
                        You cannot create a duplicate.
                        <a :href="'{{ $editRouteTemplate }}'.replace('___ID___', exactMatch.id)"
                           class="underline hover:text-clay/70 font-semibold">Edit it instead</a>.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Unit</label>
                <select name="unit" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                    @foreach(\App\Models\Product::UNITS as $u)
                        <option value="{{ $u }}" @selected(old('unit', $ingredient->unit ?? 'Liter') == $u)>{{ $u }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Selling Price ({{ $settings->currency ?? 'Rs.' }})</label>
                <input type="number" step="0.01" name="selling_price" value="{{ old('selling_price', $ingredient->selling_price ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">Price if sold directly to a customer (e.g. loose milk).</p>
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Current Stock Qty</label>
                <input type="number" step="0.01" name="stock_qty" value="{{ old('stock_qty', $ingredient->stock_qty ?? 0) }}" {{ $isEdit ? 'disabled' : 'required' }}
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700 {{ $isEdit ? 'bg-surface-container-low text-outline cursor-not-allowed' : '' }}">
                @if($isEdit)
                    <p class="text-[11px] text-outline mt-1">This also updates automatically whenever you record a Milk Collection for this raw material.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" :disabled="exactMatch && !{{ json_encode($isEdit) }}"
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Raw Material' : 'Save Raw Material' }}
        </button>
        <a href="{{ route('ingredients.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>