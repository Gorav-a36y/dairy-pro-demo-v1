@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <div>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-outline">Morning Overview</p>
        <h2 class="font-serif text-2xl font-semibold text-ink">{{ now()->format('F j, Y') }}</h2>
    </div>

    {{-- 4 stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="shopping-cart" class="h-5 w-5 text-forest-700"></i></span>
            </div>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ number_format($todaySalesCount) }}</p>
            <p class="text-xs text-outline mt-1">Today's Sales</p>
        </div>

        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="wallet" class="h-5 w-5 text-forest-700"></i></span>
            </div>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $settings->currency }} {{ number_format($todayRevenue, 0) }}</p>
            <p class="text-xs text-outline mt-1">Today's Revenue</p>
        </div>

        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="milk" class="h-5 w-5 text-forest-700"></i></span>
            </div>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ number_format($milkSoldToday, 0) }} L</p>
            <p class="text-xs text-outline mt-1">Milk Sold (Liters)</p>
        </div>

        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="h-10 w-10 rounded-control bg-clay-container flex items-center justify-center"><i data-lucide="book-text" class="h-5 w-5 text-clay"></i></span>
                @if($outstandingKhata > 0)
                    <span class="text-[11px] font-semibold text-clay bg-clay-container px-2 py-1 rounded-full">Due</span>
                @endif
            </div>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $settings->currency }} {{ number_format($outstandingKhata, 0) }}</p>
            <p class="text-xs text-outline mt-1">Outstanding Khata</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Trend chart --}}
        <div class="xl:col-span-2 bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <div class="flex items-center justify-between mb-1">
                <h3 class="font-serif text-lg font-semibold text-ink">7-Day Sales Trend</h3>
            </div>
            <p class="text-xs text-outline mb-4">Revenue per day, trailing 7 days</p>
            <div class="h-72">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>

        {{-- Right column: AI card + Quick Actions --}}
        <div class="space-y-6">
            <a href="{{ route('ai.index') }}" class="block bg-forest-900 rounded-bento p-6 text-white relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-forest-700/40 blur-2xl"></div>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-forest-300 mb-3">
                    <i data-lucide="sparkles" class="h-3.5 w-3.5"></i> AI Assistant
                </span>
                <h4 class="font-serif text-lg font-semibold mb-1.5">Ask about your dairy business</h4>
                <p class="text-sm text-forest-200/90 mb-4">Production costing, khata trends, and restocking — get a quick answer.</p>
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-white group-hover:gap-2.5 transition-all">
                    Ask AI Assistant <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </span>
            </a>

            <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
                <h4 class="font-serif font-semibold text-ink mb-4">Quick Actions</h4>
                <div class="space-y-1">
                    <a href="{{ route('sales.pos') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-control bg-forest-700 text-white hover:bg-forest-800 transition">
                        <i data-lucide="plus-circle" class="h-4 w-4"></i>
                        <span class="text-sm font-semibold">New Sale</span>
                    </a>
                    <a href="{{ route('milk-collections.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-control text-ink-variant hover:bg-surface-container-low transition">
                        <i data-lucide="truck" class="h-4 w-4"></i>
                        <span class="text-sm font-medium">Register Purchase</span>
                    </a>
                    <a href="{{ route('customers.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-control text-ink-variant hover:bg-surface-container-low transition">
                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                        <span class="text-sm font-medium">Add Customer</span>
                    </a>
                    <a href="{{ route('products.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-control text-ink-variant hover:bg-surface-container-low transition">
                        <i data-lucide="package-plus" class="h-4 w-4"></i>
                        <span class="text-sm font-medium">New Product</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@endpush

@push('scripts')
<script>
    const ctx = document.getElementById('salesTrendChart');
    const labels = @json($trend->pluck('label'));
    const values = @json($trend->pluck('total'));

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: values,
                borderColor: '#1b3022',
                backgroundColor: (ctxObj) => {
                    const g = ctxObj.chart.ctx.createLinearGradient(0, 0, 0, 280);
                    g.addColorStop(0, 'rgba(27,48,34,0.15)');
                    g.addColorStop(1, 'rgba(27,48,34,0.0)');
                    return g;
                },
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#1b3022',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                borderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#efeeea' }, ticks: { color: '#737973', font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { color: '#737973', font: { size: 11 } } },
            }
        }
    });
</script>
@endpush
