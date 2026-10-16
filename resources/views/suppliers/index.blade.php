@extends('layouts.app')
@section('title', 'Suppliers / Farmers')
@section('page-title', 'Suppliers / Farmers')

@section('content')
<div class="space-y-6">
    {{-- ==================== SEARCH + ADD ==================== --}}
    <div class="flex flex-col sm:flex-row sm:items-stretch sm:justify-between gap-4">
        <form method="GET"
            class="bg-white rounded-bento border border-outline-variant shadow-bento p-4 flex items-center gap-4 w-full sm:flex-1 min-w-0">
            <x-search-bar name="search" placeholder="Search suppliers..."/>
        </form>

        <a href="{{ route('suppliers.create') }}"
        class="shrink-0 inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="user-plus" class="h-4 w-4"></i> Add Supplier
        </a>
    </div>

    <div id="live-results" class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Supplier Name</th>
                        <th class="px-6 py-3 font-semibold">Phone</th>
                        <th class="px-6 py-3 font-semibold">Current Balance</th>
                        <th class="px-6 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($suppliers as $supplier)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center shrink-0 font-serif font-semibold text-forest-700">
                                        {{ strtoupper(substr($supplier->name, 0, 1)) }}
                                    </span>
                                    <p class="font-semibold text-ink">{{ $supplier->name }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-ink-variant">{{ $supplier->phone ?? '—' }}</td>
                            <td class="px-6 py-4 tabular font-semibold {{ $supplier->balance > 0 ? 'text-clay' : 'text-ink' }}">
                                {{ $settings->currency }} {{ number_format($supplier->balance, 0) }}
                                @if($supplier->balance > 0) <span class="text-[11px] font-normal text-outline">owed</span> @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('khata.index', ['tab' => 'supplier', 'id' => $supplier->id]) }}" class="p-2 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition" title="View Khata"><i data-lucide="book-text" class="h-4 w-4"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-outline">
                            {{ request()->filled('search') ? 'No suppliers match your search.' : 'No suppliers yet. Add your first farmer or supplier.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
