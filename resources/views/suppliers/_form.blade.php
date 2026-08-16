@php $isEdit = isset($supplier); @endphp

<form method="POST" action="{{ $isEdit ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="max-w-2xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-5">
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Supplier Name</label>
            <input type="text" name="name" value="{{ old('name', $supplier->name ?? '') }}" required
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
        </div>
        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Phone</label>
                <input type="tel" name="phone" value="{{ old('phone', $supplier->phone ?? '') }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Opening Balance ({{ $settings->currency ?? 'Rs.' }})</label>
                <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', $supplier->opening_balance ?? 0) }}" {{ $isEdit ? 'disabled' : '' }}
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700 {{ $isEdit ? 'bg-surface-container-low text-outline cursor-not-allowed' : '' }}">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Address</label>
            <textarea name="address" rows="2" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">{{ old('address', $supplier->address ?? '') }}</textarea>
        </div>
        <div class="flex items-center gap-2.5">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true)) id="is_active"
                   class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700">
            <label for="is_active" class="text-sm text-ink-variant">Active</label>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Supplier' : 'Save Supplier' }}
        </button>
        <a href="{{ route('suppliers.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
