@extends('layouts.app')
@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" class="relative w-full sm:max-w-xs">
            <i data-lucide="search" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..."
                   class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
        </form>
        <a href="{{ route('products.create') }}"
           class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 shadow-bento-sm transition">
            <i data-lucide="flask-conical" class="h-4 w-4"></i> Make a Product
        </a>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Product</th>
                        <th class="px-6 py-3 font-semibold">Unit</th>
                        <th class="px-6 py-3 font-semibold">Selling Price</th>
                        <th class="px-6 py-3 font-semibold">Recipe Yield</th>
                        <th class="px-6 py-3 font-semibold">Stock</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($products as $product)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center shrink-0">
                                        <i data-lucide="package" class="h-4.5 w-4.5 text-forest-700" style="width:18px;height:18px"></i>
                                    </span>
                                    <p class="font-semibold text-ink">{{ $product->name }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-ink-variant">{{ $product->unit }}</td>
                            <td class="px-6 py-4 tabular font-semibold text-ink">{{ $settings->currency }} {{ number_format($product->selling_price, 0) }}</td>
                            <td class="px-6 py-4 text-ink-variant">{{ number_format($product->output_qty_per_batch, 1) }} {{ $product->unit }} / batch</td>
                            <td class="px-6 py-4 tabular text-ink-variant">{{ number_format($product->stock_qty, 1) }} {{ $product->unit }}</td>
                            <td class="px-6 py-4"><x-status-badge :status="$product->is_active ? 'active' : 'inactive'" /></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('products.edit', $product) }}" class="p-2 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition"><i data-lucide="pencil" class="h-4 w-4"></i></a>
                                    <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Delete this product?')">
                                        @csrf @method('DELETE')
                                        <button class="p-2 rounded-control text-outline hover:text-clay hover:bg-clay-container transition"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-outline">No products yet. Make your first product from raw materials.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())
            <div class="px-6 py-4 border-t border-outline-variant">{{ $products->links() }}</div>
        @endif
    </div>
</div>
@endsection
