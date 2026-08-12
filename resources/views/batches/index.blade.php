@extends('layouts.app')
@section('title', 'Batch Production')
@section('page-title', 'Batch Production')

@section('content')
<div class="space-y-8">
    <div>
        <h2 class="font-serif text-xl font-semibold text-ink mb-1">Run a Production Batch</h2>
        <p class="text-sm text-outline">Pick a product recipe, set a batch multiplier, and confirm — stock is deducted and produced automatically.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @forelse($products as $product)
            @php
                $recipe = $product->ingredients->map(fn($i) => [
                    'id' => $i->id, 'name' => $i->name, 'unit' => $i->unit,
                    'qty' => (float) $i->pivot->quantity_required,
                    'cost' => (float) $i->cost_per_unit,
                    'available' => (float) $i->stock_qty,
                ]);
            @endphp
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden flex flex-col"
                 x-data="{
                    multiplier: 1,
                    recipe: {{ $recipe->toJson() }},
                    outputPerBatch: {{ $product->output_qty_per_batch }},
                    get batchCost() { return this.recipe.reduce((sum, r) => sum + (r.qty * this.multiplier * r.cost), 0) },
                    get outputQty() { return this.outputPerBatch * this.multiplier },
                    get costPerUnit() { return this.outputQty > 0 ? this.batchCost / this.outputQty : 0 },
                    get canRun() { return this.recipe.length > 0 && this.recipe.every(r => (r.qty * this.multiplier) <= r.available) }
                 }">
                <div class="p-6 pb-0">
                    <div class="flex items-start justify-between mb-4">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold tracking-wide bg-forest-50 text-forest-700 border border-forest-300/40 uppercase">
                            <i data-lucide="flask-conical" class="h-3 w-3"></i>
                            {{ number_format($product->output_qty_per_batch, 0) }} {{ $product->unit }} manufactured
                        </span>
                    </div>
                    <h3 class="font-serif text-xl font-semibold text-ink">{{ $product->name }}</h3>
                    <p class="text-xs text-outline mb-5">Sells at {{ $settings->currency }} {{ number_format($product->selling_price, 0) }}/{{ $product->unit }}</p>
                </div>

                <div class="px-6 grid grid-cols-2 gap-3 mb-5">
                    <div class="rounded-control bg-surface-container-low border border-outline-variant p-4">
                        <p class="text-[11px] font-semibold text-outline uppercase tracking-wide mb-1">Batch Cost</p>
                        <p class="font-serif text-xl font-semibold text-ink tabular">{{ $settings->currency }} <span x-text="batchCost.toFixed(0)"></span></p>
                    </div>
                    <div class="rounded-control bg-forest-50 border border-forest-300/30 p-4">
                        <p class="text-[11px] font-semibold text-forest-700 uppercase tracking-wide mb-1">Live Cost / Unit</p>
                        <p class="font-serif text-xl font-semibold text-forest-700 tabular">{{ $settings->currency }} <span x-text="costPerUnit.toFixed(2)"></span></p>
                    </div>
                </div>

                <div class="px-6 mb-5">
                    <p class="text-[11px] font-semibold text-outline uppercase tracking-wide mb-2">Required Raw Materials</p>
                    @if($recipe->isEmpty())
                        <p class="text-sm text-outline italic">No recipe configured yet — <a href="{{ route('products.edit', $product) }}" class="text-forest-700 font-medium">add raw materials</a>.</p>
                    @else
                        <ul class="space-y-2">
                            <template x-for="r in recipe" :key="r.id">
                                <li class="flex items-center justify-between text-sm rounded-control px-3 py-2"
                                    :class="(r.qty * multiplier) > r.available ? 'bg-clay-container' : 'bg-surface-container-low'">
                                    <span class="text-ink font-medium" x-text="r.name"></span>
                                    <span class="text-ink-variant">
                                        <span x-text="(r.qty * multiplier).toFixed(2)"></span> <span x-text="r.unit"></span>
                                        <span class="text-outline mx-1">/</span>
                                        <span :class="(r.qty * multiplier) > r.available ? 'text-clay font-semibold' : 'text-outline'" x-text="r.available.toFixed(1) + ' avail.'"></span>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    @endif
                </div>

                <form method="POST" action="{{ route('batches.store', $product) }}" class="px-6 pb-6 mt-auto pt-2 border-t border-outline-variant">
                    @csrf
                    <div class="flex items-center gap-3 pt-4">
                        <div class="flex-1">
                            <label class="block text-[11px] font-semibold text-outline uppercase tracking-wide mb-1.5">Batch Multiplier</label>
                            <input type="number" step="0.1" min="0.1" name="multiplier" x-model.number="multiplier"
                                   class="w-full rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                        </div>
                        <div class="flex-1 text-right">
                            <p class="text-[11px] font-semibold text-outline uppercase tracking-wide mb-1.5">Output</p>
                            <p class="font-semibold text-ink tabular"><span x-text="outputQty.toFixed(1)"></span> {{ $product->unit }}</p>
                        </div>
                    </div>
                    <button type="submit" :disabled="!canRun"
                            class="w-full mt-4 inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
                        <i data-lucide="play-circle" class="h-4 w-4"></i> Run Batch Production
                    </button>
                    <p x-show="!canRun" class="text-xs text-clay mt-2 text-center">Not enough raw material stock for this multiplier.</p>
                </form>
            </div>
        @empty
            <p class="text-sm text-outline col-span-full text-center py-10">No active products yet. <a href="{{ route('products.create') }}" class="text-forest-700 font-medium">Create one</a> to start production.</p>
        @endforelse
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="p-6 pb-4">
            <h3 class="font-serif text-lg font-semibold text-ink">Recent Batches</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-y border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Product</th>
                        <th class="px-6 py-3 font-semibold">Multiplier</th>
                        <th class="px-6 py-3 font-semibold">Output</th>
                        <th class="px-6 py-3 font-semibold">Batch Cost</th>
                        <th class="px-6 py-3 font-semibold">Cost / Unit</th>
                        <th class="px-6 py-3 font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($history as $batch)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-3 font-medium text-ink">{{ $batch->product->name ?? 'Deleted product' }}</td>
                            <td class="px-6 py-3 text-outline">{{ $batch->multiplier }}x</td>
                            <td class="px-6 py-3 text-ink-variant tabular">{{ number_format($batch->output_qty, 1) }}</td>
                            <td class="px-6 py-3 text-ink font-semibold tabular">{{ $settings->currency }} {{ number_format($batch->batch_cost, 0) }}</td>
                            <td class="px-6 py-3 text-ink-variant tabular">{{ $settings->currency }} {{ number_format($batch->cost_per_unit, 2) }}</td>
                            <td class="px-6 py-3 text-outline">{{ $batch->created_at->format('M j, g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-outline">No batches run yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
