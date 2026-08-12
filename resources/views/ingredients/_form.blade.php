@php $isEdit = isset($ingredient); @endphp

<form method="POST" action="{{ $isEdit ? route('ingredients.update', $ingredient) : route('ingredients.store') }}" class="max-w-2xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-5">
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Raw Material Name</label>
            <input type="text" name="name" value="{{ old('name', $ingredient->name ?? '') }}" required
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
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
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Cost per Unit ({{ $settings->currency ?? 'Rs.' }})</label>
                <input type="number" step="0.01" name="cost_per_unit" value="{{ old('cost_per_unit', $ingredient->cost_per_unit ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Current Stock Qty</label>
                <input type="number" step="0.01" name="stock_qty" value="{{ old('stock_qty', $ingredient->stock_qty ?? '') }}" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Reorder Level</label>
                <input type="number" step="0.01" name="reorder_level" value="{{ old('reorder_level', $ingredient->reorder_level ?? 0) }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">Stock at or below this triggers a "Low Stock" warning.</p>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Raw Material' : 'Save Raw Material' }}
        </button>
        <a href="{{ route('ingredients.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
