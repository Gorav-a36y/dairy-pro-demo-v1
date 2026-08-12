<?php

namespace App\View\Components;

use Illuminate\View\Component;

class StatusBadge extends Component
{
    public string $status;
    public string $classes;
    public string $label;

    public function __construct(string $status)
    {
        $this->status = $status;

        $map = [
            'paid' => ['bg-forest-50 text-forest-800 border-forest-300/50', 'Paid'],
            'partial' => ['bg-amber-50 text-amber-700 border-amber-200', 'Partial'],
            'unpaid' => ['bg-clay-container text-clay border-clay/20', 'Unpaid / Khata'],
            'completed' => ['bg-forest-50 text-forest-800 border-forest-300/50', 'Completed'],
            'active' => ['bg-forest-50 text-forest-800 border-forest-300/50', 'Active'],
            'inactive' => ['bg-surface-container text-outline border-outline-variant', 'Inactive'],
        ];

        [$this->classes, $this->label] = $map[$status] ?? ['bg-surface-container text-ink-variant border-outline-variant', ucfirst($status)];
    }

    public function render()
    {
        return <<<'BLADE'
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $classes }}">
                {{ $label }}
            </span>
        BLADE;
    }
}
