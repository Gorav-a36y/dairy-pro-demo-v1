@extends('layouts.app')
@section('title', 'Reports')
@section('page-title', 'Business Analytics')

@section('content')
<div class="space-y-6">

    {{-- Flash messages --}}
    @if(session('error'))
        <div class="rounded-control bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium flex items-center gap-2">
            <i data-lucide="alert-circle" class="h-4 w-4"></i> {{ session('error') }}
        </div>
    @endif
    @if(session('success'))
        <div class="rounded-control bg-forest-50 border border-forest-200 text-forest-800 px-4 py-3 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="h-4 w-4"></i> {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-bento border border-outline-variant shadow-bento p-5 flex flex-col sm:flex-row items-end gap-4 flex-wrap">
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
        <div class="flex gap-2 sm:ml-auto">
            <a href="{{ route('reports.index', ['from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->endOfMonth()->format('Y-m-d')]) }}" class="inline-flex items-center gap-1.5 rounded-control border border-outline-variant text-ink-variant text-xs font-semibold px-3 py-2 hover:bg-surface-container-low transition">This Month</a>
            <a href="{{ route('reports.index', ['from' => now()->subMonth()->startOfMonth()->format('Y-m-d'), 'to' => now()->subMonth()->endOfMonth()->format('Y-m-d')]) }}" class="inline-flex items-center gap-1.5 rounded-control border border-outline-variant text-ink-variant text-xs font-semibold px-3 py-2 hover:bg-surface-container-low transition">Last Month</a>
            <a href="{{ route('reports.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="inline-flex items-center gap-2 rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-5 py-2.5 hover:bg-surface-container-low transition">
                <i data-lucide="download" class="h-4 w-4"></i> CSV
            </a>
        </div>
    </form>

    {{-- Row 1: Money Overview --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="wallet" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $settings->currency }} {{ number_format($totalRevenue, 0) }}</p>
            <p class="text-xs text-outline mt-1">Total Revenue</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="banknote" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-forest-700 tabular">{{ $settings->currency }} {{ number_format($cashSales, 0) }}</p>
            <p class="text-xs text-outline mt-1">Cash Sales</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-clay-container flex items-center justify-center mb-3"><i data-lucide="credit-card" class="h-5 w-5 text-clay"></i></span>
            <p class="text-2xl font-serif font-semibold text-clay tabular">{{ $settings->currency }} {{ number_format($creditSales, 0) }}</p>
            <p class="text-xs text-outline mt-1">Credit (Khata) Sales</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="trending-up" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold {{ $grossProfit >= 0 ? 'text-forest-700' : 'text-clay' }} tabular">{{ $settings->currency }} {{ number_format($grossProfit, 0) }}</p>
            <p class="text-xs text-outline mt-1">Gross Profit (Revenue − Milk Cost)</p>
        </div>
    </div>

    {{-- Row 2: Dairy Operations & Khata Health --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-blue-50 flex items-center justify-center mb-3"><i data-lucide="droplets" class="h-5 w-5 text-blue-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ number_format($totalMilkLiters, 1) }} L</p>
            <p class="text-xs text-outline mt-1">Milk Collected</p>
            <p class="text-[11px] text-outline mt-1">Fat {{ number_format($avgFat, 1) }}% · SNF {{ number_format($avgSnf, 1) }}%</p>
            @if($milkWasted > 0)
                <p class="text-[11px] text-clay mt-1">Wasted {{ number_format($milkWasted, 1) }} L · Net {{ number_format($netMilk, 1) }} L</p>
            @endif
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-clay-container flex items-center justify-center mb-3"><i data-lucide="users" class="h-5 w-5 text-clay"></i></span>
            <p class="text-2xl font-serif font-semibold text-clay tabular">{{ $settings->currency }} {{ number_format($totalCustomerOutstanding, 0) }}</p>
            <p class="text-xs text-outline mt-1">Customer Khata Outstanding</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-amber-50 flex items-center justify-center mb-3"><i data-lucide="tractor" class="h-5 w-5 text-amber-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-amber-700 tabular">{{ $settings->currency }} {{ number_format($totalSupplierOwed, 0) }}</p>
            <p class="text-xs text-outline mt-1">Owed to Suppliers</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="receipt" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ number_format($totalInvoices) }}</p>
            <p class="text-xs text-outline mt-1">Total Invoices</p>
            <p class="text-[11px] text-outline mt-1">Avg {{ $settings->currency }} {{ number_format($avgInvoiceValue, 0) }}</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="hand-coins" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $settings->currency }} {{ number_format($totalWorkersSalary, 0) }}</p>
            <p class="text-xs text-outline mt-1">Total Workers' Salary</p>
            <p class="text-[11px] text-outline mt-1">Active staff, per month</p>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Revenue vs Milk Cost Trend --}}
        <div class="xl:col-span-2 bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-4">Revenue vs Milk Purchase Cost</h3>
            <div class="h-72"><canvas id="trendChart"></canvas></div>
        </div>

        {{-- Payment Method Breakdown --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-4">Payment Methods</h3>
            <div class="h-56 flex items-center justify-center"><canvas id="paymentChart"></canvas></div>
            <div class="mt-4 space-y-2">
                @forelse($paymentMethods as $method => $amount)
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2 text-ink-variant capitalize">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ ['cash'=>'#1b3022','khata'=>'#c96442','partial_khata'=>'#d4a017'][$method] ?? '#737973' }}"></span>
                            {{ $method }}
                        </span>
                        <span class="font-semibold text-ink tabular">{{ $settings->currency }} {{ number_format($amount, 0) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-outline text-center">No data</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Tables Row --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Product Performance --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-4">Product Performance</h3>
            <div class="space-y-4 max-h-80 overflow-y-auto pr-1">
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

        {{-- Top Milk Suppliers --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-4">Top Milk Suppliers</h3>
            <div class="space-y-4 max-h-80 overflow-y-auto pr-1">
                @forelse($topSuppliers as $s)
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink truncate">{{ $s->name }}</p>
                            <p class="text-xs text-outline">{{ number_format($s->total_qty, 1) }} L · Fat {{ number_format($s->avg_fat ?? 0, 1) }}%</p>
                        </div>
                        <span class="text-sm font-semibold text-ink shrink-0 tabular">{{ $settings->currency }} {{ number_format($s->total_cost, 0) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-outline">No milk collections in this range.</p>
                @endforelse
            </div>
        </div>

        {{-- Overdue Khata --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-serif text-lg font-semibold text-ink">Overdue Khata</h3>
                <a href="{{ route('khata.index', ['tab' => 'customer']) }}" class="text-xs font-semibold text-forest-700 hover:underline">View All</a>
            </div>
            <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                @forelse($overdueCustomers as $c)
                    <div class="flex items-center justify-between p-3 rounded-control bg-surface-container-low/50">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink truncate">{{ $c->name }}</p>
                            <p class="text-xs text-outline">{{ $c->phone ?? 'No phone' }}</p>
                        </div>
                        <span class="text-sm font-bold text-clay shrink-0 tabular">{{ $settings->currency }} {{ number_format($c->balance, 0) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-outline text-center py-4">All customer accounts are clear.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
    /* ─── DESKTOP GRID LAYOUT ─── */
    @media (min-width: 1024px) {
        .grid.grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-4 {
            grid-template-columns: repeat(4, 1fr) !important;
        }
        .grid.grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-5 {
            grid-template-columns: repeat(5, 1fr) !important;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    /* ── Revenue vs Milk Cost Line Chart ── */
    const trendCtx = document.getElementById('trendChart');
    const labels = @json($dailyTrend->pluck('day'));
    const revenueData = @json($dailyTrend->pluck('total'));
    const milkCostData = labels.map(day => {
        const m = @json($dailyMilk);
        return m[day]?.cost ?? 0;
    });

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenueData,
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
                },
                {
                    label: 'Milk Cost',
                    data: milkCostData,
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1.5,
                    borderWidth: 2,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { usePointStyle: true } } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#efeeea' }, ticks: { color: '#737973', font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { color: '#737973', font: { size: 11 } } },
            }
        }
    });

    /* ── Payment Method Doughnut ── */
    const paymentCtx = document.getElementById('paymentChart');
    const pmLabels = @json($paymentMethods->keys());
    const pmValues = @json($paymentMethods->values());
    const pmColors = pmLabels.map(m => ({ cash: '#1b3022', khata: '#c96442', partial_khata: '#d4a017' }[m] ?? '#737973'));

    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: pmLabels,
            datasets: [{ data: pmValues, backgroundColor: pmColors, borderWidth: 0, hoverOffset: 4 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const v = ctx.parsed;
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total ? ((v / total) * 100).toFixed(1) : 0;
                            return ` {{ $settings->currency }} ${Number(v).toLocaleString()} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
</script>
@endpush