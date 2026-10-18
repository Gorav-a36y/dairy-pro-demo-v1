@extends('layouts.app')
@section('title', 'Add Supplier')
@section('page-title', 'Add Supplier')

@section('content')
{{-- Create-only form. Suppliers cannot be edited after they are saved. --}}
<form method="POST" action="{{ route('suppliers.store') }}"
      x-data="{
          name: {{ json_encode(old('name', '')) }},
          allSuppliers: {{ json_encode($allSuppliers ?? []) }},
          open: false,
          submitting: false,

          get filteredSuppliers() {
              if (this.name.trim().length < 1) return [];
              const q = this.name.toLowerCase();
              return this.allSuppliers.filter(s => s.name.toLowerCase().includes(q));
          },

          get exactMatch() {
              return this.allSuppliers.find(s => s.name.toLowerCase() === this.name.toLowerCase().trim());
          },

          selectExisting(s) {
              this.name = s.name;
              this.open = false;
          }
      }"
      @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
      @pageshow.window="submitting = false"
      class="max-w-2xl">
    @csrf

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-5">
        {{-- Warning: no edit after save --}}
        <div class="p-3 rounded-control bg-surface-container-low border border-outline-variant flex items-start gap-2">
            <i data-lucide="info" class="h-4 w-4 text-outline shrink-0 mt-0.5"></i>
            <p class="text-xs text-ink-variant">
                Name and phone <span class="font-semibold">cannot be changed</span> after you save. Please check them carefully.
            </p>
        </div>

        {{-- Name with live duplicate search --}}
        <div @click.outside="open = false" class="relative">
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Supplier Name</label>
            <input type="text" name="name" x-model="name" autocomplete="off" required maxlength="255"
                   @focus="open = true"
                   @input="open = true"
                   :class="exactMatch ? 'border-clay focus:ring-clay focus:border-clay' : 'border-outline-variant focus:ring-forest-700 focus:border-forest-700'"
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 transition">
            @error('name')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror

            {{-- Dropdown: existing suppliers --}}
            <div x-show="open && filteredSuppliers.length > 0" x-cloak x-transition
                 class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                <div class="px-4 py-2 text-[11px] font-semibold text-outline uppercase tracking-wide border-b border-outline-variant/60">
                    Already in database
                </div>
                <template x-for="s in filteredSuppliers" :key="s.id">
                    <button type="button" @click="selectExisting(s)"
                            class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition"
                            :class="s.name.toLowerCase() === name.toLowerCase().trim() ? 'bg-clay-container/40 text-clay' : ''">
                        <span class="font-medium" :class="s.name.toLowerCase() === name.toLowerCase().trim() ? 'text-clay' : 'text-ink'" x-text="s.name"></span>
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
                    <p class="text-xs text-clay/80 mt-0.5">You cannot create a duplicate.</p>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Phone Number</label>
            <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="30"
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            <p class="text-[11px] text-outline mt-1">Must be different from every other supplier's phone — used to avoid the same person being added twice.</p>
            @error('phone')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" :disabled="submitting || !!exactMatch"
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> Save Supplier
        </button>
        <a href="{{ route('suppliers.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
@endsection
