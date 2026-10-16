@extends('layouts.app')
@section('title', 'Raw Materials')
@section('page-title', 'Raw Materials')

@section('content')
<div id="live-results" class="space-y-6">
    {{-- ==================== SEARCH + ADD ==================== --}}
    <div class="flex flex-col sm:flex-row sm:items-stretch sm:justify-between gap-4">
        <form method="GET"
            class="bg-white rounded-bento border border-outline-variant shadow-bento p-4 flex items-center gap-4 w-full sm:flex-1 min-w-0">
            <x-search-bar name="search" placeholder="Search raw materials..." />
        </form>

        <a href="{{ route('raw-materials.create') }}"
        class="shrink-0 inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="plus" class="h-4 w-4"></i> Add Raw Material
        </a>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Raw Material</th>
                        <th class="px-6 py-3 font-semibold">Unit</th>
                        <th class="px-6 py-3 font-semibold">Selling Price</th>
                        <th class="px-6 py-3 font-semibold">Current Stock</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($rawMaterials as $rawMaterial)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center shrink-0">
                                        <i data-lucide="wheat" class="h-4.5 w-4.5 text-forest-700" style="width:18px;height:18px"></i>
                                    </span>
                                    <p class="font-semibold text-ink">{{ $rawMaterial->name }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-ink-variant">{{ $rawMaterial->unit }}</td>
                            <td class="px-6 py-4 tabular text-ink font-semibold">{{ $settings->currency }} {{ number_format($rawMaterial->selling_price, 2) }}</td>
                            <td class="px-6 py-4 tabular text-ink-variant">{{ number_format($rawMaterial->stock_qty, 2) }} {{ $rawMaterial->unit }}</td>
                            <td class="px-6 py-4">
                                @if($rawMaterial->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-forest-50 text-forest-700 text-xs font-semibold px-2.5 py-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-forest-700"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-low text-outline text-xs font-semibold px-2.5 py-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-outline"></span> Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-3">
                                    <x-toggle-switch :action="route('raw-materials.toggle', ['raw_material' => $rawMaterial->id])" :active="$rawMaterial->is_active" />
                                    <a href="{{ route('raw-materials.edit', ['raw_material' => $rawMaterial->id]) }}" class="p-2 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition">
                                        <i data-lucide="pencil" class="h-4 w-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-outline">No raw materials yet. Add one (e.g. Raw Milk) to get started.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rawMaterials->hasPages())
            <div class="px-6 py-4 border-t border-outline-variant">{{ $rawMaterials->links() }}</div>
        @endif
    </div>
</div>
@endsection