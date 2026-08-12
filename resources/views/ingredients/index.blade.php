@extends('layouts.app')
@section('title', 'Raw Materials')
@section('page-title', 'Raw Materials')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" class="relative w-full sm:max-w-xs">
            <i data-lucide="search" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search raw materials..."
                   class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
        </form>
        <a href="{{ route('ingredients.create') }}"
           class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 shadow-bento-sm transition">
            <i data-lucide="plus" class="h-4 w-4"></i> Add Raw Material
        </a>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Raw Material</th>
                        <th class="px-6 py-3 font-semibold">Stock</th>
                        <th class="px-6 py-3 font-semibold">Cost / Unit</th>
                        <th class="px-6 py-3 font-semibold">Reorder Level</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($ingredients as $ingredient)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center shrink-0">
                                        <i data-lucide="wheat" class="h-4.5 w-4.5 text-forest-700" style="width:18px;height:18px"></i>
                                    </span>
                                    <p class="font-semibold text-ink">{{ $ingredient->name }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 tabular font-medium {{ $ingredient->isLowStock() ? 'text-clay' : 'text-ink-variant' }}">
                                {{ number_format($ingredient->stock_qty, 1) }} {{ $ingredient->unit }}
                            </td>
                            <td class="px-6 py-4 tabular text-ink-variant">{{ $settings->currency }} {{ number_format($ingredient->cost_per_unit, 2) }}</td>
                            <td class="px-6 py-4 tabular text-outline">{{ number_format($ingredient->reorder_level, 1) }} {{ $ingredient->unit }}</td>
                            <td class="px-6 py-4">
                                @if($ingredient->isLowStock())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-clay-container text-clay border border-clay/20">
                                        <i data-lucide="alert-triangle" class="h-3 w-3"></i> Low Stock
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-forest-50 text-forest-800 border border-forest-300/40">
                                        <i data-lucide="check" class="h-3 w-3"></i> In Stock
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('ingredients.edit', $ingredient) }}" class="p-2 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition"><i data-lucide="pencil" class="h-4 w-4"></i></a>
                                    <form method="POST" action="{{ route('ingredients.destroy', $ingredient) }}" onsubmit="return confirm('Delete this raw material?')">
                                        @csrf @method('DELETE')
                                        <button class="p-2 rounded-control text-outline hover:text-clay hover:bg-clay-container transition"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-outline">No raw materials yet. Add one to start building product recipes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($ingredients->hasPages())
            <div class="px-6 py-4 border-t border-outline-variant">{{ $ingredients->links() }}</div>
        @endif
    </div>
</div>
@endsection
