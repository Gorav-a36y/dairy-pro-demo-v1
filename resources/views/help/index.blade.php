@extends('layouts.app')
@section('title', 'Help & Guide')
@section('page-title', 'How To Use DairyPro')

@section('content')
<div class="space-y-6 max-w-4xl">

    {{-- Intro --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <h2 class="font-serif text-xl font-semibold text-ink mb-2">Welcome to DairyPro</h2>
        <p class="text-sm text-ink-variant leading-relaxed">
            This page explains, in simple words, what every part of this software does and how to use it.
            No technical language — just plain talk, like someone explaining it to you in person.
        </p>
    </div>

    {{-- Raw Materials --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="box" class="h-5 w-5 text-forest-700"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">Raw Materials</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed mb-3">
            Raw Material means anything you buy from farmers or suppliers — like milk, eggs, sugar. This is the
            "input" for your dairy.
        </p>
        <ul class="text-sm text-ink-variant space-y-2 list-disc pl-5">
            <li>Every raw material has a <b>Name</b>, <b>Unit</b> (Liter, Kg, etc.), and <b>Selling Price</b> — this is the price if you sell it directly, like loose milk.</li>
            <li>There is a special tick box called <b>"This raw material is Milk"</b>. Tick this ONLY for actual milk. This tells the software which numbers to show on the Milk Collection card and Reports page — so if you tick it wrongly on something else (like eggs), your milk totals will show wrong numbers.</li>
            <li><b>Stock Quantity</b> is not something you type by hand after creation — it updates automatically whenever you record a Milk Collection.</li>
        </ul>
    </div>

    {{-- Products --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="package" class="h-5 w-5 text-forest-700"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">Products</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed mb-3">
            Product means something you <b>make</b> using raw materials — like dahi (yogurt) made from milk.
        </p>
        <ul class="text-sm text-ink-variant space-y-2 list-disc pl-5">
            <li>When you create a product, you also tell it the <b>Recipe</b> — which raw materials and how much of each is used to make one batch.</li>
            <li>The moment you save a new product, it also runs the <b>first production</b> — meaning it takes out the raw material stock and adds stock for the new product. So don't create the product before you're ready to actually make it.</li>
            <li>Next time you want to make more of the same product, go to <b>Batch Production</b> page — not back to edit the product.</li>
        </ul>
    </div>

    {{-- Milk Collection --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-blue-50 flex items-center justify-center"><i data-lucide="milk" class="h-5 w-5 text-blue-700"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">Milk Collection</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed mb-3">
            This page is for recording what you bought from a supplier — mainly milk, but any raw material can be recorded here.
        </p>
        <ul class="text-sm text-ink-variant space-y-2 list-disc pl-5">
            <li>Pick the <b>Supplier</b>, pick the <b>Raw Material</b>, enter <b>Quantity</b> and <b>Price</b> — total amount is calculated for you automatically.</li>
            <li><b>Fat %</b> and <b>SNF %</b> boxes only appear when the raw material is a liquid (like milk). For non-liquid things like eggs, these boxes won't show — because they don't make sense there.</li>
            <li>Whatever amount is NOT paid right now goes on to the supplier's <b>Khata</b> (credit account) automatically — you don't need to enter it anywhere else.</li>
            <li>Click the small eye icon 👁 next to any entry to see and print its receipt.</li>
        </ul>
    </div>

    {{-- Payment Methods: Khata / Partial Khata --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-clay-container flex items-center justify-center"><i data-lucide="book-open" class="h-5 w-5 text-clay"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">Khata &amp; Partial Khata (Very Important)</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed mb-3">
            Khata means "credit" — customer or supplier owes you money, or you owe them, and it gets written down instead of paid right away. There are 3 ways to pay:
        </p>
        <div class="space-y-3">
            <div class="p-4 rounded-control bg-surface-container-low/60">
                <p class="text-sm font-bold text-ink mb-1">1. Cash (or any other normal method)</p>
                <p class="text-sm text-ink-variant">Full amount is paid right now. Nothing goes on Khata. Simple and complete.</p>
            </div>
            <div class="p-4 rounded-control bg-surface-container-low/60">
                <p class="text-sm font-bold text-ink mb-1">2. Khata (Full Credit)</p>
                <p class="text-sm text-ink-variant">Nothing is paid right now. The <b>whole amount</b> goes on to the person's Khata account. You will collect the money later.</p>
            </div>
            <div class="p-4 rounded-control bg-surface-container-low/60">
                <p class="text-sm font-bold text-ink mb-1">3. Partial Khata (Some Cash, Some Credit)</p>
                <p class="text-sm text-ink-variant">Customer/supplier pays <b>some</b> amount now, and the <b>remaining</b> amount goes on their Khata. For example: total is Rs. 1,000, they pay Rs. 600 now, Rs. 400 goes on Khata.</p>
                <p class="text-sm text-ink-variant mt-1">Note: If you're paying the full amount, don't use Partial Khata — just use Cash instead. Partial Khata will not let you enter the full amount, because at that point it's not "partial" anymore.</p>
            </div>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed mt-3">
            You can see and settle all Khata (both customer side and supplier side) from the <b>Khata</b> page — pick the person, see how much they owe or you owe, and record a payment any time.
        </p>
    </div>

    {{-- POS / Sales --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="shopping-cart" class="h-5 w-5 text-forest-700"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">POS (Selling Page)</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed mb-3">
            This is where you sell things to customers — both Products (like dahi) and Raw Materials (like loose milk) can be sold from the same page.
        </p>
        <ul class="text-sm text-ink-variant space-y-2 list-disc pl-5">
            <li>Default customer is <b>Walk-in Customer</b> — use this for people who just come and buy, no account needed.</li>
            <li>If a regular customer is buying on Khata, search and pick their name — their existing due amount will show up too.</li>
            <li>The software will <b>not let you sell</b> more quantity than what is in stock — it will stop you and show a warning.</li>
            <li>After the sale, a receipt is ready to print. If it's a Khata sale, the receipt shows Previous Balance + This Purchase + New Balance clearly.</li>
        </ul>
    </div>

    {{-- Active / Inactive --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-amber-50 flex items-center justify-center"><i data-lucide="toggle-left" class="h-5 w-5 text-amber-700"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">What "Active / Inactive" Means</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed mb-2">
            Every Raw Material, Product, Supplier, Customer, and Worker has an <b>Active / Inactive</b> switch.
        </p>
        <ul class="text-sm text-ink-variant space-y-2 list-disc pl-5">
            <li>If you turn a Raw Material or Product <b>Inactive</b>, it will <b>not show up in the POS</b> when someone searches to sell it. It also won't show in Milk Collection's raw material list.</li>
            <li>This does NOT delete anything — all old records, sales, history stay exactly as they were. It only hides it from the "new entry" screens going forward.</li>
            <li>Use this when something is out of season, discontinued, or a supplier/customer is no longer active — instead of deleting them.</li>
            <li>To bring it back, just switch it to Active again — it will reappear everywhere immediately.</li>
        </ul>
    </div>

    {{-- Batch Production --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="factory" class="h-5 w-5 text-forest-700"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">Batch Production</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed">
            This page is for making more of a Product you already created. Pick the product, enter how many batches
            or how much quantity you want to make, and it will automatically reduce the raw material stock and
            increase the finished product stock — same recipe every time, just repeated.
        </p>
    </div>

    {{-- Reports --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="bar-chart-3" class="h-5 w-5 text-forest-700"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">Reports</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed mb-3">
            This page gives you the full picture of your business for any date range you choose.
        </p>
        <ul class="text-sm text-ink-variant space-y-2 list-disc pl-5">
            <li><b>Total Revenue, Cash Sales, Credit Sales, Gross Profit</b> — money coming in and what's left after milk cost.</li>
            <li><b>Milk Collected</b> — this only counts raw materials that are ticked as "is Milk" (see Raw Materials section above). Anything not ticked as milk is left out of this number on purpose.</li>
            <li><b>Customer Khata Outstanding</b> and <b>Owed to Suppliers</b> — how much money is pending both ways.</li>
            <li>You can download a CSV file (opens in Excel) of the sales data for the same date range using the <b>Download CSV</b> button.</li>
        </ul>
    </div>

    {{-- Settings --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center"><i data-lucide="settings" class="h-5 w-5 text-forest-700"></i></span>
            <h3 class="font-serif text-lg font-semibold text-ink">Settings</h3>
        </div>
        <p class="text-sm text-ink-variant leading-relaxed">
            Here you set your Dairy Name, Phone, Address, Currency symbol, and your Region/Timezone — these show up
            on every printed receipt. Changing the Region here also fixes the time shown across the whole app, so
            set this correctly once and you won't need to touch it again.
        </p>
    </div>

</div>
@endsection