@php
    $isEdit = isset($customer);
    $dailyItems = $dailyItems ?? [];                 // guard against missing include data
    $allCustomers = $allCustomers ?? [];
    $customerDailyItems = $customerDailyItems ?? [];

    // Decide if the "Daily Customer" box starts ticked.
    //  - After a failed save: use what the user had on screen. An unticked box
    //    is not sent at all, so "missing" means "unticked".
    //  - Otherwise: use the saved value. A new customer is never daily by default.
    // Having rows in the list must NOT tick the box (the edit page always shows
    // one empty row, and that used to make every customer look like a daily customer).
    $hasOldInput = ! empty(old());
    $isDaily = $hasOldInput
        ? (bool) old('is_daily_customer', false)
        : ($isEdit ? (bool) $customer->is_daily_customer : false);

    // Collect ALL daily_items.* errors in one place (server-side, index-agnostic)
    $dailyItemErrors = collect()
        ->merge($errors->get('daily_items'))
        ->merge($errors->get('daily_items.*.item_key'))
        ->merge($errors->get('daily_items.*.quantity'))
        ->unique()
        ->values();
@endphp

<form method="POST"
      action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}"
      x-data="{
          isDaily: {{ $isDaily ? 'true' : 'false' }},
          name: {{ json_encode(old('name', $customer->name ?? '')) }},
          allCustomers: {{ json_encode($allCustomers) }},
          nameOpen: false,

          // Each row gets a stable unique id so Alpine's x-for
          // doesn't glitch when rows are removed from the middle.
          nextUid: 1,
          items: [],

          init() {
              // Seed saved/old rows (edit page or validation failure).
              const seeded = @js(array_values($customerDailyItems));

              this.items = seeded.map(row => ({
                  uid: this.nextUid++,
                  item_key: row.item_key ?? '',
                  quantity: row.quantity ?? '',
              }));

              // Daily customer with no rows yet -> show one immediately.
              if (this.isDaily && this.items.length === 0) {
                  this.addItem();
              }
          },

          addItem() {
              this.items.push({ uid: this.nextUid++, item_key: '', quantity: '' });
              this.$nextTick(() => window.lucide && lucide.createIcons());
          },

          removeItem(uid) {
              this.items = this.items.filter(item => item.uid !== uid);
              // Never leave a daily customer with zero visible rows.
              if (this.items.length === 0) this.addItem();
          },

          onToggleDaily() {
              if (this.isDaily && this.items.length === 0) {
                  this.addItem();
              }
          },

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
      }"
      x-init="init()">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-5">
        @unless($isEdit)
            <div class="p-3 rounded-control bg-surface-container-low border border-outline-variant flex items-start gap-2">
                <i data-lucide="info" class="h-4 w-4 text-outline shrink-0 mt-0.5"></i>
                <p class="text-xs text-ink-variant">
                    The customer name <span class="font-semibold">cannot be changed</span> after you save. Please check it carefully.
                </p>
            </div>
        @endunless

        <div @click.outside="nameOpen = false" class="relative">
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Customer / Business Name</label>

            @if($isEdit)
                <input type="text" value="{{ $customer->name }}" disabled
                       class="w-full rounded-control border border-outline-variant bg-surface-container-low text-outline cursor-not-allowed px-4 py-3 text-sm">
                <p class="text-[11px] text-outline mt-1">The name cannot be changed after the customer is created.</p>
            @else
                <input type="text" name="name" x-model="name" autocomplete="off" required
                       @focus="nameOpen = true"
                       @input="nameOpen = true"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            @endif
            @error('name')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror

            @unless($isEdit)
                <div x-show="nameOpen && filteredCustomersByName.length > 0" x-cloak x-transition
                     class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                    <div class="px-4 py-2 text-[11px] font-semibold text-outline uppercase tracking-wide border-b border-outline-variant/60">
                        Already in database
                    </div>
                    <template x-for="c in filteredCustomersByName" :key="c.id">
                        <button type="button" @click="selectExistingName(c)"
                                :class="c.name.toLowerCase() === name.toLowerCase().trim() ? 'bg-clay-container/40 text-clay' : ''"
                                class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition">
                            <span class="font-medium" :class="c.name.toLowerCase() === name.toLowerCase().trim() ? 'text-clay' : 'text-ink'" x-text="c.name"></span>
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
                            You cannot save a customer with a name that already exists. Please use a different name.
                        </p>
                    </div>
                </div>
            @endunless
        </div>

        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" required
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            <p class="text-[11px] text-outline mt-1">Must be different from every other customer's phone. Spaces, dashes and +92 are ignored, so 0300-1234567 and 03001234567 are the same number.</p>
            @error('phone')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror
        </div>

        <div class="pt-5 border-t border-outline-variant">
            {{-- Checkbox left, green "Add Another Item" button right --}}
            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2.5">
                    <input type="checkbox" name="is_daily_customer" value="1" x-model="isDaily"
                           @change="onToggleDaily()"
                           class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700">
                    <span class="text-sm font-semibold text-ink">Daily Customer</span>
                </label>

                <button type="button" x-show="isDaily" x-cloak @click="addItem()"
                        class="inline-flex items-center gap-1.5 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-xs font-semibold px-4 py-2.5 shadow-bento-sm transition">
                    <i data-lucide="plus" class="h-4 w-4"></i> Add Another Item
                </button>
            </div>
            <p class="text-[11px] text-outline mt-1">Takes one or more items every day and pays at month end. Shows up on the Daily Round page with these defaults pre-filled.</p>

            <div x-show="isDaily" x-cloak x-transition class="space-y-4 mt-4">
                <template x-for="item in items" :key="item.uid">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide"
                                   x-text="item.uid === items[0].uid ? 'Usual Item' : 'Another Item'"></label>
                            <select :name="'daily_items[' + (items.indexOf(item)) + '][item_key]'"
                                    x-model="item.item_key"
                                    class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                                <option value="">Select item...</option>
                                @foreach($dailyItems as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['label'] }} ({{ $option['sublabel'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-1">
                                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide"
                                       x-text="item.uid === items[0].uid ? 'Usual Quantity' : 'Quantity'"></label>
                                <input type="number" step="0.01" min="0.01"
                                       :name="'daily_items[' + (items.indexOf(item)) + '][quantity]'"
                                       x-model="item.quantity"
                                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                            </div>
                            <button type="button" x-show="items.length > 1" @click="removeItem(item.uid)"
                                    class="mt-[30px] h-[46px] w-[46px] shrink-0 rounded-control border border-outline-variant text-clay hover:bg-clay/10 flex items-center justify-center transition"
                                    title="Remove this item">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- All daily-item validation errors render here (server-side) --}}
                @if($dailyItemErrors->isNotEmpty())
                    <div class="p-3 rounded-control bg-clay-container/50 border border-clay/30">
                        @foreach($dailyItemErrors as $message)
                            <p class="text-xs text-clay font-medium">{{ $message }}</p>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit"
                @unless($isEdit)
                :disabled="exactNameMatch"
                :class="exactNameMatch ? 'opacity-50 cursor-not-allowed' : ''"
                @endunless
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Customer' : 'Save Customer' }}
        </button>
        <a href="{{ route('customers.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
