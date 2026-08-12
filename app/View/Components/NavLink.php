<?php

namespace App\View\Components;

use Illuminate\View\Component;

class NavLink extends Component
{
    public string $route;
    public string $icon;
    public string $label;
    public bool $active;

    public function __construct(string $route, string $icon, string $label)
    {
        $this->route = $route;
        $this->icon = $icon;
        $this->label = $label;
        $this->active = request()->routeIs($route) || request()->routeIs(explode('.', $route)[0] . '.*');
    }

    public function render()
    {
        return <<<'BLADE'
            <a href="{{ route($route) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-control text-sm font-medium transition {{ $active ? 'bg-forest-700 text-white shadow-bento-sm' : 'text-forest-200/80 hover:bg-white/5 hover:text-white' }}">
                <i data-lucide="{{ $icon }}" class="h-4 w-4 shrink-0" style="width:17px;height:17px"></i>
                <span>{{ $label }}</span>
            </a>
        BLADE;
    }
}
