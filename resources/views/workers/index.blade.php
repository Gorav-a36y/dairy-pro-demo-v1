{{-- resources/views/workers/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Workers')
@section('page-title', 'Workers')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
    <div>
        <p class="text-sm text-ink-variant mt-1">Manage your dairy farm staff and their compensation.</p>
    </div>
    <a href="{{ route('workers.create') }}"
       class="inline-flex items-center gap-2 rounded-control bg-forest-900 text-white px-5 py-2.5 text-sm font-semibold hover:bg-forest-800 transition shadow-sm">
        <i data-lucide="plus" class="h-4 w-4"></i>
        Add Worker
    </a>
</div>

<div class="bg-white rounded-bento border border-outline-variant shadow-sm overflow-hidden">
    <div class="px-6 lg:px-8 py-6 flex items-center justify-between">
        <h2 class="font-serif text-xl font-semibold text-ink">Staff Directory</h2>
        <span class="text-xs font-semibold uppercase tracking-wider text-outline">
            {{ $workers->total() }} {{ Str::plural('worker', $workers->total()) }}
        </span>
    </div>

    @if($workers->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b" style="border-color: #e9e8e4;">
                        <th class="px-6 lg:px-8 pb-3 pt-1 text-[11px] font-semibold uppercase tracking-wider text-outline">Name</th>
                        <th class="px-6 pb-3 pt-1 text-[11px] font-semibold uppercase tracking-wider text-outline">Phone Number</th>
                        <th class="px-6 pb-3 pt-1 text-[11px] font-semibold uppercase tracking-wider text-outline text-right">Monthly Salary</th>
                        <th class="px-6 pb-3 pt-1 text-[11px] font-semibold uppercase tracking-wider text-outline text-center">Status</th>
                        <th class="px-6 lg:px-8 pb-3 pt-1 text-[11px] font-semibold uppercase tracking-wider text-outline text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workers as $worker)
                        <tr class="group hover:bg-surface-container-low/40 transition">
                            <td class="px-6 lg:px-8 py-4 text-sm font-medium text-ink">
                                {{ $worker->name }}
                            </td>
                            <td class="px-6 py-4 text-sm tabular text-ink-variant">
                                {{ $worker->phone_number }}
                            </td>
                            <td class="px-6 py-4 text-sm tabular text-ink text-right font-medium">
                                {{ $settings->currency ?? 'Rs.' }} {{ number_format($worker->monthly_salary, 2) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <x-toggle-switch :action="route('workers.toggle', $worker)" :active="$worker->is_active" />
                            </td>
                            <td class="px-6 lg:px-8 py-4 text-right">
                                <a href="{{ route('workers.edit', $worker) }}"
                                   class="inline-flex items-center justify-center h-8 w-8 rounded-control bg-surface-container-low text-ink-variant hover:bg-surface-container hover:text-ink transition"
                                   title="Edit">
                                    <i data-lucide="pencil" class="h-4 w-4"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 lg:px-8 py-4 border-t" style="border-color: #e9e8e4;">
            {{ $workers->links() }}
        </div>
    @else
        <div class="text-center py-16 px-6">
            <div class="h-14 w-14 mx-auto mb-4 rounded-bento bg-surface-container-low flex items-center justify-center text-outline">
                <i data-lucide="users" class="h-6 w-6"></i>
            </div>
            <h3 class="font-serif text-lg font-semibold text-ink mb-1">No workers yet</h3>
            <p class="text-sm text-ink-variant mb-6">Add your first staff member to get started.</p>
            <a href="{{ route('workers.create') }}"
               class="inline-flex items-center gap-2 rounded-control bg-forest-900 text-white px-5 py-2.5 text-sm font-semibold hover:bg-forest-800 transition">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Add Worker
            </a>
        </div>
    @endif
</div>

@endsection