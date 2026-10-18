@props(['action', 'active', 'activeLabel' => 'Active', 'inactiveLabel' => 'Inactive'])

<form method="POST" action="{{ $action }}" class="inline-flex items-center gap-2">
    @csrf
    @method('PATCH')
    {{-- The state the admin WANTS after this click (1 = active, 0 = inactive).
         Sending the wanted state (not "flip") means a double-click cannot undo itself. --}}
    <input type="hidden" name="is_active" value="{{ $active ? 0 : 1 }}">
    <button type="submit"
            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border transition-colors focus:outline-none focus:ring-2 focus:ring-forest-700/40 {{ $active ? 'bg-forest-700 border-forest-700' : 'bg-outline/50 border-outline' }}"
            title="{{ $active ? 'Click to deactivate' : 'Click to activate' }}">
        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform {{ $active ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
    </button>
    <span class="text-xs font-semibold {{ $active ? 'text-forest-700' : 'text-outline' }}">
        {{ $active ? $activeLabel : $inactiveLabel }}
    </span>
</form>
