<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — {{ $settings->dairy_name ?? 'DairyPro' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
                        serif: ['Playfair Display', 'ui-serif', 'Georgia'],
                    },
                    colors: {
                        surface: '#fbf9f5',
                        'surface-dim': '#dbdad6',
                        'surface-container-lowest': '#ffffff',
                        'surface-container-low': '#f5f3ef',
                        'surface-container': '#efeeea',
                        'surface-container-high': '#e9e8e4',
                        ink: '#1b1c1a',
                        'ink-variant': '#4a4a4a',
                        outline: '#737973',
                        'outline-variant': '#e5e4e0',
                        forest: {
                            50: '#eef4ef', 100: '#d0e9d4', 300: '#b4cdb8', 500: '#4d6453',
                            700: '#1b3022', 800: '#0f2317', 900: '#061b0e',
                        },
                        clay: '#ba1a1a',
                        'clay-container': '#ffdad6',
                    },
                    boxShadow: {
                        bento: '0px 4px 20px rgba(27, 48, 34, 0.06)',
                        'bento-sm': '0px 2px 10px rgba(27, 48, 34, 0.05)',
                    },
                    borderRadius: {
                        bento: '1.5rem',
                        control: '1rem',
                    },
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; background: #fbf9f5; }
        h1, h2, h3, h4, .font-serif { font-family: 'Playfair Display', serif; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: #dbdad6; border-radius: 999px; }
        [x-cloak] { display: none !important; }
        .tabular { font-variant-numeric: tabular-nums; }
    </style>
    <script>
        document.addEventListener('alpine:init', () => {
            // Reusable searchable dropdown. Usage:
            // <div x-data="searchableSelect(itemsJson, initialId)">...</div>
            // items: [{ id, label, sublabel? }]
            Alpine.data('searchableSelect', (items = [], initialId = '') => ({
                items: items,
                query: '',
                open: false,
                selectedId: initialId,
                get selectedItem() {
                    return this.items.find(i => String(i.id) === String(this.selectedId)) || null;
                },
                get selectedLabel() {
                    return this.selectedItem ? this.selectedItem.label : '';
                },
                get filtered() {
                    if (!this.query) return this.items;
                    const q = this.query.toLowerCase();
                    return this.items.filter(i =>
                        i.label.toLowerCase().includes(q) ||
                        (i.sublabel && String(i.sublabel).toLowerCase().includes(q))
                    );
                },
                select(item) {
                    this.selectedId = item.id;
                    this.query = '';
                    this.open = false;
                    this.$dispatch('select-item', item);
                },
                clear() {
                    this.selectedId = '';
                    this.query = '';
                }
            }));
        });
    </script>
    @stack('head')
</head>
<body class="bg-surface text-ink antialiased">
<div class="min-h-screen flex">

    {{-- Sidebar --}}
    <aside class="hidden lg:flex lg:flex-col w-64 shrink-0 bg-forest-900 text-forest-100 min-h-screen sticky top-0">
        <div class="h-16 flex items-center gap-2.5 px-6 border-b border-white/10">
            <img src="{{ asset('images/dairy_pro_logo.png') }}" alt="DairyPro" class="h-8 w-8 rounded-lg object-cover"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <span class="h-8 w-8 rounded-lg bg-gradient-to-br from-forest-300 to-forest-500 items-center justify-center hidden">
                <i data-lucide="milk" class="h-4 w-4 text-white"></i>
            </span>
            <span class="font-serif font-semibold text-white text-lg tracking-tight">{{ $settings->dairy_name ?? 'DairyPro' }}</span>
        </div>

        <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-forest-300/70 mb-2">Overview</p>
            <x-nav-link route="dashboard" icon="layout-dashboard" label="Dashboard" />

            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-forest-300/70 mt-6 mb-2">Sales</p>
            <x-nav-link route="sales.pos" icon="shopping-cart" label="New Sale (POS)" />
            <x-nav-link route="sales.history" icon="receipt" label="Sales History" />
            <x-nav-link route="customers.index" icon="users" label="Customers" />

            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-forest-300/70 mt-6 mb-2">Procurement</p>
            <x-nav-link route="milk-collections.index" icon="truck" label="Milk Collection" />
            <x-nav-link route="suppliers.index" icon="tractor" label="Suppliers / Farmers" />

            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-forest-300/70 mt-6 mb-2">Production</p>
            <x-nav-link route="batches.index" icon="flask-conical" label="Batch Production" />
            <x-nav-link route="products.index" icon="package" label="Products" />
            <x-nav-link route="ingredients.index" icon="wheat" label="Raw Materials" />

            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-forest-300/70 mt-6 mb-2">Accounts</p>
            <x-nav-link route="khata.index" icon="book-text" label="Khata (Ledger)" />
            <x-nav-link route="reports.index" icon="bar-chart-3" label="Reports" />

            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-forest-300/70 mt-6 mb-2">Assistant</p>
            <x-nav-link route="ai.index" icon="sparkles" label="AI Assistant" />
            <x-nav-link route="settings.edit" icon="settings" label="Settings" />
        </nav>

        <div class="p-4 border-t border-white/10">
            <a href="{{ config('app.brand_url') }}" target="_blank" rel="noopener"
               class="flex items-center gap-2 rounded-control bg-white/5 hover:bg-white/10 transition px-3 py-2.5 group">
                <i data-lucide="sparkle" class="h-4 w-4 text-forest-300"></i>
                <span class="text-xs text-forest-200">Powered by
                    <span class="font-semibold text-white">Gorav</span><span class="font-semibold text-forest-300">AI</span>
                </span>
                <i data-lucide="arrow-up-right" class="h-3.5 w-3.5 text-forest-400 ml-auto group-hover:text-forest-200"></i>
            </a>
        </div>
    </aside>

    {{-- Mobile sidebar --}}
    <input type="checkbox" id="mobile-nav-toggle" class="hidden peer">
    <div class="fixed inset-0 z-40 bg-forest-900/60 hidden peer-checked:block lg:hidden" onclick="document.getElementById('mobile-nav-toggle').checked=false"></div>
    <aside class="fixed z-50 inset-y-0 left-0 w-72 bg-forest-900 text-forest-100 -translate-x-full peer-checked:translate-x-0 transition-transform lg:hidden overflow-y-auto">
        <div class="h-16 flex items-center justify-between px-5 border-b border-white/10 sticky top-0 bg-forest-900">
            <span class="font-serif font-semibold text-white text-lg">{{ $settings->dairy_name ?? 'DairyPro' }}</span>
            <label for="mobile-nav-toggle" class="text-forest-300"><i data-lucide="x" class="h-5 w-5"></i></label>
        </div>
        <nav class="p-3 space-y-1">
            <x-nav-link route="dashboard" icon="layout-dashboard" label="Dashboard" />
            <x-nav-link route="sales.pos" icon="shopping-cart" label="New Sale (POS)" />
            <x-nav-link route="sales.history" icon="receipt" label="Sales History" />
            <x-nav-link route="customers.index" icon="users" label="Customers" />
            <x-nav-link route="milk-collections.index" icon="truck" label="Milk Collection" />
            <x-nav-link route="suppliers.index" icon="tractor" label="Suppliers / Farmers" />
            <x-nav-link route="batches.index" icon="flask-conical" label="Batch Production" />
            <x-nav-link route="products.index" icon="package" label="Products" />
            <x-nav-link route="ingredients.index" icon="wheat" label="Raw Materials" />
            <x-nav-link route="khata.index" icon="book-text" label="Khata (Ledger)" />
            <x-nav-link route="reports.index" icon="bar-chart-3" label="Reports" />
            <x-nav-link route="ai.index" icon="sparkles" label="AI Assistant" />
            <x-nav-link route="settings.edit" icon="settings" label="Settings" />
        </nav>
    </aside>

    {{-- Main --}}
    <div class="flex-1 min-w-0 flex flex-col">
        <header class="h-16 sticky top-0 z-30 bg-surface/90 backdrop-blur border-b border-outline-variant flex items-center justify-between px-4 lg:px-8">
            <div class="flex items-center gap-3">
                <label for="mobile-nav-toggle" class="lg:hidden text-outline"><i data-lucide="menu" class="h-5 w-5"></i></label>
                <h1 class="font-serif text-lg font-semibold text-ink">@yield('page-title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex items-center gap-2 text-xs text-outline bg-white border border-outline-variant rounded-full px-3 py-1.5">
                    <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                    {{ now()->format('D, M j Y') }}
                </div>
                @auth
                <div class="flex items-center gap-2 pl-4 border-l border-outline-variant">
                    <div class="h-8 w-8 rounded-full bg-forest-700 text-white flex items-center justify-center text-xs font-semibold font-serif">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <span class="hidden md:block text-sm font-medium text-ink-variant">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-outline hover:text-clay transition" title="Log out">
                            <i data-lucide="log-out" class="h-4.5 w-4.5" style="width:18px;height:18px"></i>
                        </button>
                    </form>
                </div>
                @endauth
            </div>
        </header>

        <main class="flex-1 p-4 lg:p-8 bg-surface">
            @if (session('success'))
                <div class="mb-6 flex items-center gap-3 rounded-bento border border-forest-300/40 bg-forest-50 px-4 py-3 text-sm text-forest-800">
                    <i data-lucide="check-circle-2" class="h-5 w-5 text-forest-700 shrink-0"></i>
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 flex items-center gap-3 rounded-bento border border-clay/30 bg-clay-container px-4 py-3 text-sm text-clay">
                    <i data-lucide="alert-triangle" class="h-5 w-5 text-clay shrink-0"></i>
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-bento border border-clay/30 bg-clay-container px-4 py-3 text-sm text-clay">
                    <div class="flex items-center gap-2 font-semibold mb-1"><i data-lucide="alert-triangle" class="h-4 w-4"></i> Please fix the following:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="px-4 lg:px-8 py-6 text-center text-xs text-outline border-t border-outline-variant">
            {{ $settings->dairy_name ?? 'DairyPro' }} &copy; {{ now()->year }} — Built by
            <a href="{{ config('app.brand_url') }}" target="_blank" class="font-semibold text-ink-variant hover:text-forest-700">
                <span class="text-ink">Gorav</span><span class="text-forest-700">AI</span>
            </a>
        </footer>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded', () => lucide.createIcons());</script>
@stack('scripts')
</body>
</html>
