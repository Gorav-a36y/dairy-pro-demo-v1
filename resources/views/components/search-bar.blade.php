@props([
    'name' => 'search',
    'value' => null,
    'placeholder' => 'Search...',
    'icon' => 'search',
    'live' => true,               // live search while typing
    'target' => '#live-results',  // the element on the page that holds the table + pagination
])

@php
    $currentValue = $value ?? request($name);
@endphp

<div class="relative w-full flex-1 min-w-0"
     @if($live)
     x-data="{
        controller: null,
        async run(value) {
            const box = document.querySelector('{{ $target }}');

            // Page has no live-results box yet: fall back to a normal form submit.
            if (!box) { this.$el.closest('form')?.requestSubmit(); return; }

            // Keep other filters in the URL (like dates), change only the search text and go back to page 1.
            const url = new URL(window.location.href);
            const term = value.trim();
            if (term) url.searchParams.set('{{ $name }}', term);
            else url.searchParams.delete('{{ $name }}');
            url.searchParams.delete('page');

            // If the user keeps typing, cancel the older request.
            if (this.controller) this.controller.abort();
            this.controller = new AbortController();

            box.style.opacity = '0.5';
            try {
                const res = await fetch(url, {
                    signal: this.controller.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const fresh = doc.querySelector('{{ $target }}');
                if (fresh) {
                    box.innerHTML = fresh.innerHTML;
                    history.replaceState(null, '', url);
                    if (window.lucide) lucide.createIcons();
                }
            } catch (e) {
                if (e.name !== 'AbortError') console.error(e);
            } finally {
                box.style.opacity = '';
            }
        }
     }"
     @endif>
    <i data-lucide="{{ $icon }}" class="h-5 w-5 text-outline absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
    <input
        type="text"
        name="{{ $name }}"
        value="{{ $currentValue }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        @if($live) @input.debounce.300ms="run($event.target.value)" @endif
        {{ $attributes->class([
            'w-full rounded-control border border-outline-variant bg-white pl-12 pr-4 py-3',
            'text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700',
        ]) }}
    >
</div>