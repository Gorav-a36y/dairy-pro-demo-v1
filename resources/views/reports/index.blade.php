@extends('layouts.app')
@section('title', 'Reports')
@section('page-title', 'Reports')

@section('content')
<div class="space-y-6">
    <form method="GET" class="bg-white rounded-bento border border-outline-variant shadow-bento p-5 flex flex-col sm:flex-row items-end gap-4">
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">From</label>
            <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
        </div>
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">To</label>
            <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-2.5 transition">
            <i data-lucide="filter" class="h-4 w-4"></i> Apply
        </button>
        <a href="{{ route('reports.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}"
           class="inline-flex items-center gap-2 rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-5 py-2.5 hover:bg-surface-container-low transition sm:ml-auto">
            <i data-lucide="download" class="h-4 w-4"></i> Download CSV Report
        </a>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="wallet" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $settings->currency }} {{ number_format($totalRevenue, 0) }}</p>
            <p class="text-xs text-outline mt-1">Total Revenue</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="receipt" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ number_format($totalOrders) }}</p>
            <p class="text-xs text-outline mt-1">Total Orders</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="trending-up" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $settings->currency }} {{ number_format($avgOrderValue, 0) }}</p>
            <p class="text-xs text-outline mt-1">Average Order Value</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-4">Revenue Over Time</h3>
            <div class="h-72"><canvas id="reportTrendChart"></canvas></div>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-4">Product Performance</h3>
            <div class="space-y-4 max-h-72 overflow-y-auto pr-1">
                @forelse($productPerformance as $p)
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink truncate">{{ $p->name }}</p>
                            <p class="text-xs text-outline">{{ number_format($p->qty, 0) }} sold</p>
                        </div>
                        <span class="text-sm font-semibold text-ink shrink-0 tabular">{{ $settings->currency }} {{ number_format($p->revenue, 0) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-outline">No data for this range.</p>
                @endforelse
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
    const rctx = document.getElementById('reportTrendChart');
    const rLabels = @json($dailyTrend->pluck('day'));
    const rValues = @json($dailyTrend->pluck('total'));

    new Chart(rctx, {
        type: 'bar',
        data: { labels: rLabels, datasets: [{ label: 'Revenue', data: rValues, backgroundColor: '#1b3022', borderRadius: 6, maxBarThickness: 28 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#efeeea' }, ticks: { color: '#737973', font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { color: '#737973', font: { size: 11 } } },
            }
        }
    });
</script>
@endpush
