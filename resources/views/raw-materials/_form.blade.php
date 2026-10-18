@php
    $isEdit = isset($rawMaterial);
    $currentId = $isEdit ? $rawMaterial->getKey() : null;
    $editRouteTemplate = route('raw-materials.edit', ['raw_material' => '___ID___']);
    $selectedUnit = old('unit', $rawMaterial->unit ?? 'Liter');
    $isMilk = (bool) old('is_milk', $rawMaterial->is_milk ?? false);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('raw-materials.update', ['raw_material' => $currentId]) : route('raw-materials.store') }}"
      x-data="{
          name: {{ json_encode(old('name', $rawMaterial->name ?? '')) }},
          unit: {{ json_encode($selectedUnit) }},
          isMilk: {{ $isMilk ? 'true' : 'false' }},
          allRawMaterials: {{ json_encode($allRawMaterials ?? []) }},
          open: false,
          submitting: false,

          get canBeMilk() {
              return ['Liter', 'Kilogram'].includes(this.unit);
          },

          unitChanged() {
              if (!this.canBeMilk) this.isMilk = false;
          },

          get filteredRawMaterials() {
              if (this.name.trim().length < 1) return [];
              const q = this.name.toLowerCase();
              return this.allRawMaterials.filter(rm => rm.name.toLowerCase().includes(q));
          },

          get exactMatch() {
              return this.allRawMaterials.find(rm => rm.name.toLowerCase() === this.name.toLowerCase().trim());
          },

          selectExisting(rm) {
              this.name = rm.name;
              this.open = false;
          }
      }"
      x-init="unitChanged()"
      @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
      @pageshow.window="submitting = false"
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
                       class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 transition">
            </div>
            @error('name')
                <p class="text-[11px] text-clay font-semibold mt-1">{{ $message }}</p>
            @enderror

            {{-- Dropdown: existing raw materials --}}
            <div x-show="open && filteredRawMaterials.length > 0" x-cloak x-transition
                 class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                <div class="px-4 py-2 text-[11px] font-semibold text-outline uppercase tracking-wide border-b border-outline-variant/60">
                    Already in database
                </div>
                <template x-for="rm in filteredRawMaterials" :key="rm.id">
                    <button type="button" @click="selectExisting(rm)"
                            class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition"
                            :class="rm.name.toLowerCase() === name.toLowerCase().trim() ? 'bg-clay-container/40 text-clay' : ''">
                        <span class="font-medium" :class="rm.name.toLowerCase() === name.toLowerCase().trim() ? 'text-clay' : 'text-ink'" x-text="rm.name"></span>
                        <span class="text-xs text-outline" x-text="(rm.stock_qty ?? 0) + ' ' + rm.unit"></span>
                    </button>
                </template>
            </div>

            {{-- Exact duplicate warning --}}
            <div x-show="exactMatch" x-cloak x-transition
                 class="mt-2.5 p-3 rounded-control bg-clay-container/50 border border-clay/30 flex items-start gap-2">
                <i data-lucide="alert-circle" class="h-4 w-4 text-clay shrink-0 mt-0.5"></i>
                <div>
                    <p class="text-sm font-semibold text-clay">
                        "<span x-text="exactMatch?.name"></span>" already exists in your database.
                    </p>
                    <p class="text-xs text-clay/80 mt-0.5">
                        You cannot use a duplicate name.
                        <a :href="'{{ $editRouteTemplate }}'.replace('___ID___', exactMatch?.id ?? '')"
                           class="underline hover:text-clay/70 font-semibold">Edit it instead</a>.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
            {{-- Unit (locked on edit) --}}
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Unit</label>
                <select name="unit" x-model="unit" @change="unitChanged()" {{ $isEdit ? 'disabled' : '' }}
                        class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700 {{ $isEdit ? 'bg-surface-container-low text-outline cursor-not-allowed' : '' }}">
                    @foreach(\App\Models\Product::UNITS as $u)
                        <option value="{{ $u }}" @selected(old('unit', $rawMaterial->unit ?? 'Liter') == $u)>{{ $u }}</option>
                    @endforeach
                </select>
                @if($isEdit)
                    <p class="text-[11px] text-outline mt-1">Unit cannot be changed after the raw material is created.</p>
                @endif
                @error('unit')
                    <p class="text-[11px] text-clay font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Selling Price ({{ $settings->currency ?? 'Rs.' }})</label>
                <input type="number" step="0.01" min="0.01" name="selling_price" value="{{ old('selling_price', $rawMaterial->selling_price ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">Price if sold directly to a customer (e.g. loose milk).</p>
                @error('selling_price')
                    <p class="text-[11px] text-clay font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="col-span-2">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">
                    Current Stock Qty @unless($isEdit)<span class="normal-case font-normal text-outline">(optional, defaults to 0)</span>@endunless
                </label>
                <input type="number" step="0.01" min="0" name="stock_qty" value="{{ old('stock_qty', $rawMaterial->stock_qty ?? 0) }}" {{ $isEdit ? 'disabled' : '' }}
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700 {{ $isEdit ? 'bg-surface-container-low text-outline cursor-not-allowed' : '' }}">
                @if($isEdit)
                    <p class="text-[11px] text-outline mt-1">This also updates automatically whenever you record a Milk Collection for this raw material.</p>
                @endif
                @error('stock_qty')
                    <p class="text-[11px] text-clay font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Is Milk (locked on edit) --}}
            <div class="col-span-2">
                <div class="flex items-center gap-3 p-3.5 rounded-control border-2 border-forest-700 bg-forest-700/5"
                     :class="!canBeMilk ? 'border-outline-variant bg-surface-container-low opacity-70' : ''">
                    {{-- On edit the hidden input is removed, otherwise it would still send 0 --}}
                    @unless($isEdit)
                        <input type="hidden" name="is_milk" value="0">
                    @endunless
                    <input type="checkbox" name="is_milk" value="1" x-model="isMilk"
                           @checked($isMilk) id="is_milk"
                           :disabled="!canBeMilk || {{ $isEdit ? 'true' : 'false' }}"
                           class="h-5 w-5 rounded border-outline-variant text-forest-700 focus:ring-forest-700"
                           :class="!canBeMilk || {{ $isEdit ? 'true' : 'false' }} ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'">
                    <label for="is_milk" class="text-base font-bold text-ink"
                           :class="!canBeMilk || {{ $isEdit ? 'true' : 'false' }} ? 'cursor-not-allowed' : 'cursor-pointer'">
                        This raw material is Milk
                    </label>
                </div>
                <p class="text-[11px] text-outline mt-1">
                    <span x-show="canBeMilk">Available for Liter and Kilogram units.</span>
                    <span x-show="!canBeMilk">Milk can only be selected for Liter or Kilogram units.</span>
                </p>
                @if($isEdit)
                    <p class="text-[11px] text-outline mt-1">This setting cannot be changed after the raw material is created.</p>
                @endif
                @error('is_milk')
                    <p class="text-[11px] text-clay font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="col-span-2 flex items-center gap-2.5">
                {{-- Hidden fallback: unchecked checkboxes send nothing, so without this
                     is_active silently reverted to true on every save --}}
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $rawMaterial->is_active ?? true)) id="is_active"
                       class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700">
                <label for="is_active" class="text-sm text-ink-variant">Active</label>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" :disabled="submitting || !!exactMatch"
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Raw Material' : 'Save Raw Material' }}
        </button>
        <a href="{{ route('raw-materials.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
