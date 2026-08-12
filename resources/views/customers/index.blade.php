@extends('layouts.app')
@section('title', 'Customers')
@section('page-title', 'Customers')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" class="relative w-full sm:max-w-xs">
            <i data-lucide="search" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customers..."
                   class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
        </form>
        <a href="{{ route('customers.create') }}"
           class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 shadow-bento-sm transition">
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
                            <p class="font-semibold text-ink">{{ $customer->name }}</p>
                            <p class="text-xs text-outline">{{ $customer->phone ?? 'No phone on file' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('khata.index', ['tab' => 'customer', 'id' => $customer->id]) }}" class="p-1.5 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition" title="View Khata"><i data-lucide="book-text" class="h-3.5 w-3.5"></i></a>
                        <a href="{{ route('customers.edit', $customer) }}" class="p-1.5 rounded-control text-outline hover:text-forest-700 hover:bg-forest-50 transition"><i data-lucide="pencil" class="h-3.5 w-3.5"></i></a>
                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer?')">
                            @csrf @method('DELETE')
                            <button class="p-1.5 rounded-control text-outline hover:text-clay hover:bg-clay-container transition"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                        </form>
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
