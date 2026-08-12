@props([
    'name',                 // form field name for the hidden input
    'options' => [],        // array of ['id' => ..., 'label' => ..., 'sublabel' => optional]
    'placeholder' => 'Search...',
    'selectedId' => '',
    'label' => null,
    'required' => false,
    'onSelect' => null,     // optional Alpine expression run on selection, e.g. "quantity = 1"
])
<div x-data="searchableSelect(@js($options), @js((string) $selectedId))" @click.outside="open = false" class="relative">
    @if($label)
        <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">{{ $label }}</label>
    @endif
    <input type="hidden" name="{{ $name }}" :value="selectedId" @if($required) required @endif>
    <div class="relative">
        <i data-lucide="search" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
        <input type="text"
               autocomplete="off"
               placeholder="{{ $placeholder }}"
               x-model="query"
               :value="open ? query : selectedLabel"
               @focus="open = true; query = ''"
               @input="open = true"
               class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-9 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
        <button type="button" x-show="selectedId" x-cloak @click="clear()"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-clay">
            <i data-lucide="x" class="h-3.5 w-3.5"></i>
        </button>
    </div>
    <div x-show="open" x-cloak x-transition
         class="absolute z-30 mt-1.5 w-full max-h-60 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
        <template x-for="item in filtered" :key="item.id">
            <button type="button"
                    @click="select(item); {{ $onSelect }}"
                    class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition">
                <span class="text-ink font-medium" x-text="item.label"></span>
                <span class="text-xs text-outline shrink-0" x-text="item.sublabel"></span>
            </button>
        </template>
        <div x-show="filtered.length === 0" class="px-4 py-3 text-sm text-outline">No results found.</div>
    </div>
</div>
