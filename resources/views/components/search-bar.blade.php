@props([
    'name' => 'search',
    'value' => null,
    'placeholder' => 'Search...',
    'icon' => 'search',
])

@php
    $currentValue = $value ?? request($name);
@endphp

<div class="relative w-full flex-1 min-w-0">
    <i data-lucide="{{ $icon }}" class="h-5 w-5 text-outline absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
    <input
        type="text"
        name="{{ $name }}"
        value="{{ $currentValue }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->class([
            'w-full rounded-control border border-outline-variant bg-white pl-12 pr-4 py-3',
            'text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700',
        ]) }}
    >
</div>