@php
$isEdit = isset($customer);
$isDaily = old('is_daily_customer', $isEdit ? $customer->is_daily_customer : false);
$currentDailyKey = $isEdit && $customer->daily_item_type && $customer->daily_item_id
    ? $customer->daily_item_type . ':' . $customer->daily_item_id
    : '';
@endphp

<form method="POST"
      action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}"
      x-data="{
          isDaily: {{ $isDaily ? 'true' : 'false' }},
          name: {{ json_encode(old('name', $customer->name ?? '')) }},
          allCustomers: {{ json_encode($allCustomers ?? []) }},
          nameOpen: false,

          get filteredCustomersByName() {
              if (this.name.trim().length < 1) return [];
              const q = this.name.toLowerCase();
              return this.allCustomers.filter(c => c.name.toLowerCase().includes(q));
          },

          get exactNameMatch() {
              return this.allCustomers.find(c => c.name.toLowerCase() === this.name.toLowerCase().trim());
          },

          selectExistingName(c) {
              this.name = c.name;
              this.nameOpen = false;
          }
      }">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-5">
        <div @click.outside="nameOpen = false" class="relative">
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Customer / Business Name</label>
            <input type="text" name="name" x-model="name" autocomplete="off" required
                   @focus="nameOpen = true"
                   @input="nameOpen = true"
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">

            <div x-show="nameOpen && filteredCustomersByName.length > 0" x-cloak x-transition
                 class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                <div class="px-4 py-2 text-[11px] font-semibold text-outline uppercase tracking-wide border-b border-outline-variant/60">
                    Already in database
                </div>
                <template x-for="c in filteredCustomersByName" :key="c.id">
                    <button type="button" @click="selectExistingName(c)"
                            class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition">
                        <span class="font-medium text-ink" x-text="c.name"></span>
                    </button>
                </template>
            </div>

            <div x-show="exactNameMatch" x-cloak x-transition
                 class="mt-2.5 p-3 rounded-control bg-clay-container/50 border border-clay/30 flex items-start gap-2">
                <i data-lucide="alert-circle" class="h-4 w-4 text-clay shrink-0 mt-0.5"></i>
                <div>
                    <p class="text-sm font-semibold text-clay">
                        A customer named "<span x-text="exactNameMatch?.name"></span>" already exists.
                    </p>
                    <p class="text-xs text-clay/80 mt-0.5">
                        This may be the same person — double check before saving. (Different people can share a name, so this is just a heads-up, not a hard block.)
                    </p>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                @error('phone')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Email</label>
                <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                @error('email')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror
            </div>
        </div>
        <p class="text-[11px] text-outline -mt-2">Phone and email must each be different from every other customer — this keeps the same person from being added twice.</p>
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Address</label>
            <textarea name="address" rows="2" required class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">{{ old('address', $customer->address ?? '') }}</textarea>
            @error('address')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror
        </div>

        <div class="pt-5 border-t border-outline-variant">
            <label class="flex items-center gap-2.5">
                <input type="checkbox" name="is_daily_customer" value="1" x-model="isDaily"
                       class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700">
                <span class="text-sm font-semibold text-ink">Daily Customer</span>
            </label>
            <p class="text-[11px] text-outline mt-1 ml-6">Takes milk (or another item) every day and pays at month end. Shows up on the Daily Round page with these defaults pre-filled.</p>

            <div x-show="isDaily" x-cloak x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-4">
                <div>
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Usual Item</label>
                    <select name="daily_item_key" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                        <option value="">Select item...</option>
                        @foreach($dailyItems as $item)
                            <option value="{{ $item['id'] }}" @selected(old('daily_item_key', $currentDailyKey) === $item['id'])>{{ $item['label'] }} ({{ $item['sublabel'] }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Usual Quantity</label>
                    <input type="number" step="0.01" min="0.01" name="daily_quantity" value="{{ old('daily_quantity', $customer->daily_quantity ?? '') }}"
                           class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit"
                :disabled="exactNameMatch"
                :class="exactNameMatch ? 'opacity-50 cursor-not-allowed' : ''"
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Customer' : 'Save Customer' }}
        </button>
        <a href="{{ route('customers.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>