@extends('layouts.app')
@section('title', 'Sales History')
@section('page-title', 'Sales History')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full sm:max-w-2xl">
            <div class="relative flex-1">
                <i data-lucide="search" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search invoice no..."
                       class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            <button type="submit" class="inline-flex items-center gap-2 rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition">
                <i data-lucide="filter" class="h-4 w-4"></i> Filter
            </button>
        </form>
        <a href="{{ route('sales.pos') }}"
           class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 shadow-bento-sm transition shrink-0">
            <i data-lucide="plus" class="h-4 w-4"></i> New Sale
        </a>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Invoice</th>
                        <th class="px-6 py-3 font-semibold">Customer</th>
                        <th class="px-6 py-3 font-semibold">Date</th>
                        <th class="px-6 py-3 font-semibold">Method</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold text-right">Amount</th>
                        <th class="px-6 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($sales as $sale)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-4 font-medium text-ink">
                                <a href="{{ route('sales.show', $sale) }}" class="hover:text-forest-700">{{ $sale->invoice_no }}</a>
                            </td>
                            <td class="px-6 py-4 text-ink-variant">{{ $sale->customer->name ?? 'Walk-in Customer' }}</td>
                            <td class="px-6 py-4 text-outline">{{ $sale->sale_date->format('M j, Y') }}</td>
                            <td class="px-6 py-4 text-ink-variant capitalize">{{ $sale->payment_method }}</td>
                            <td class="px-6 py-4"><x-status-badge :status="$sale->payment_status" /></td>
                            <td class="px-6 py-4 text-right tabular font-semibold text-ink">{{ $settings->currency }} {{ number_format($sale->total_amount, 0) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('sales.show', $sale) }}" class="p-2 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition"><i data-lucide="eye" class="h-4 w-4"></i></a>
                                    <form method="POST" action="{{ route('sales.destroy', $sale) }}" onsubmit="return confirm('Delete this sale?')">
                                        @csrf @method('DELETE')
                                        <button class="p-2 rounded-control text-outline hover:text-clay hover:bg-clay-container transition"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-outline">No sales recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sales->hasPages())
            <div class="px-6 py-4 border-t border-outline-variant">{{ $sales->links() }}</div>
        @endif
    </div>
</div>
@endsection
