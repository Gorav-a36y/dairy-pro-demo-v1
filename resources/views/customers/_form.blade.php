@php
    $isEdit = isset($customer);
    $isDaily = old('is_daily_customer', $customer->is_daily_customer ?? false);
    $currentDailyKey = $customer->daily_item_type && $customer->daily_item_id
        ? $customer->daily_item_type . ':' . $customer->daily_item_id
        : '';
@endphp

<form method="POST" action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}"
      x-data="{ isDaily: {{ $isDaily ? 'true' : 'false' }} }" class="max-w-2xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-5">
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Customer / Business Name</label>
            <input type="text" name="name" value="{{ old('name', $customer->name ?? '') }}" required
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
        </div>
        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Email</label>
                <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Address</label>
            <textarea name="address" rows="2" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">{{ old('address', $customer->address ?? '') }}</textarea>
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
        <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Customer' : 'Save Customer' }}
        </button>
        <a href="{{ route('customers.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>