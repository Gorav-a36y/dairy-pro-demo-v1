@extends('layouts.app')
@section('title', 'Customers')
@section('page-title', 'Customers')

@section('content')
<div class="space-y-6">
    {{-- ==================== SEARCH + ADD ==================== --}}
    <div class="flex flex-col sm:flex-row sm:items-stretch sm:justify-between gap-4">
        <form method="GET"
            class="bg-white rounded-bento border border-outline-variant shadow-bento p-4 flex items-center gap-4 w-full sm:flex-1 min-w-0">
            <x-search-bar name="search" placeholder="Search customers..."
                        x-data @input.debounce.400ms="$event.target.form.requestSubmit()" />
        </form>

        <a href="{{ route('customers.create') }}"
        class="shrink-0 inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="user-plus" class="h-4 w-4"></i> Add Customer
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($customers as $customer)
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <span class="h-11 w-11 rounded-control bg-forest-50 flex items-center justify-center font-serif font-semibold text-forest-700">
                            {{ strtoupper(substr($customer->name, 0, 1)) }}
                        </span>
                        <div>
                            <p class="font-semibold text-ink flex items-center gap-2">
                                {{ $customer->name }}
                                @if($customer->is_daily_customer)
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-forest-50 text-forest-700 border border-forest-300/40">Daily</span>
                                @endif
                            </p>
                            <p class="text-xs text-outline">{{ $customer->phone ?? 'No phone on file' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('khata.index', ['tab' => 'customer', 'id' => $customer->id]) }}" class="p-2 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition" title="View Khata"><i data-lucide="book-text" class="h-4 w-4"></i></a>
                        <a href="{{ route('customers.edit', $customer) }}" class="p-2 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition"><i data-lucide="pencil" class="h-4 w-4"></i></a>
                    </div>
                </div>
                @if($customer->address)
                    <p class="text-xs text-outline mt-3 flex items-start gap-1.5"><i data-lucide="map-pin" class="h-3.5 w-3.5 mt-0.5 shrink-0"></i>{{ $customer->address }}</p>
                @endif
                <div class="mt-4 pt-4 border-t border-outline-variant flex items-center justify-between text-xs">
                    <span class="text-outline">Khata Balance</span>
                    @php $balance = $customer->currentBalance(); @endphp
                    <span class="font-semibold tabular {{ $balance > 0 ? 'text-clay' : 'text-forest-700' }}">{{ $settings->currency }} {{ number_format($balance, 0) }}</span>
                </div>
            </div>
        @empty
            <p class="text-sm text-outline col-span-full text-center py-10">No customers yet. Add your first customer.</p>
        @endforelse
    </div>

    @if($customers->hasPages())
        <div>{{ $customers->links() }}</div>
    @endif
</div>
@endsection