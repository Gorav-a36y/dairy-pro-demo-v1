@php
    $isEdit = isset($worker);
    $editRouteTemplate = route('workers.edit', ['worker' => '___ID___']);
@endphp

<form action="{{ $isEdit ? route('workers.update', $worker) : route('workers.store') }}" method="POST"
      x-data="{
          name: {{ json_encode(old('name', $worker->name ?? '')) }},
          allWorkers: {{ json_encode($allWorkers ?? []) }},
          nameOpen: false,

          get filteredWorkers() {
              if (this.name.trim().length < 1) return [];
              const q = this.name.toLowerCase();
              return this.allWorkers.filter(w => w.name.toLowerCase().includes(q));
          },

          get exactMatch() {
              return this.allWorkers.find(w => w.name.toLowerCase() === this.name.toLowerCase().trim());
          },

          selectExisting(w) {
              this.name = w.name;
              this.nameOpen = false;
          }
      }">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="mb-5 relative" @click.outside="nameOpen = false">
        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-outline mb-2">Full Name</label>
        <input type="text" id="name" name="name" x-model="name" autocomplete="off"
               placeholder="e.g. John Smith"
               @focus="nameOpen = true"
               @input="nameOpen = true"
               :class="exactMatch ? 'border-clay focus:ring-clay/20 focus:border-clay' : 'border-outline-variant focus:border-forest-700 focus:ring-forest-700/10'"
               class="w-full h-12 px-4 rounded-control border bg-white text-ink text-sm placeholder:text-outline focus:outline-none focus:ring-2 transition"
               required>
        @error('name')<p class="mt-2 text-xs text-clay font-medium">{{ $message }}</p>@enderror

        <div x-show="nameOpen && filteredWorkers.length > 0" x-cloak x-transition
             class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
            <div class="px-4 py-2 text-[11px] font-semibold text-outline uppercase tracking-wide border-b border-outline-variant/60">
                Already in database
            </div>
            <template x-for="w in filteredWorkers" :key="w.id">
                <button type="button" @click="selectExisting(w)"
                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition"
                        :class="w.name.toLowerCase() === name.toLowerCase().trim() ? 'bg-clay-container/40 text-clay' : ''">
                    <span class="font-medium" :class="w.name.toLowerCase() === name.toLowerCase().trim() ? 'text-clay' : 'text-ink'" x-text="w.name"></span>
                </button>
            </template>
        </div>

        <div x-show="exactMatch" x-cloak x-transition
             class="mt-2.5 p-3 rounded-control bg-clay-container/50 border border-clay/30 flex items-start gap-2">
            <i data-lucide="alert-circle" class="h-4 w-4 text-clay shrink-0 mt-0.5"></i>
            <div>
                <p class="text-sm font-semibold text-clay">
                    "<span x-text="exactMatch?.name"></span>" already exists in your database.
                </p>
                <p class="text-xs text-clay/80 mt-0.5">
                    You cannot create a duplicate.
                    <a :href="'{{ $editRouteTemplate }}'.replace('___ID___', exactMatch.id)"
                       class="underline hover:text-clay/70 font-semibold">Edit it instead</a>.
                </p>
            </div>
        </div>
    </div>

    <div class="mb-5">
        <label for="phone_number" class="block text-xs font-semibold uppercase tracking-wider text-outline mb-2">Phone Number</label>
        <input type="tel" id="phone_number" name="phone_number" value="{{ old('phone_number', $worker->phone_number ?? '') }}"
               placeholder="e.g. +1 234 567 8900"
               class="w-full h-12 px-4 rounded-control border border-outline-variant bg-white text-ink text-sm placeholder:text-outline focus:outline-none focus:border-forest-700 focus:ring-2 focus:ring-forest-700/10 transition"
               required>
        @error('phone_number')<p class="mt-2 text-xs text-clay font-medium">{{ $message }}</p>@enderror
    </div>

    <div class="mb-5">
        <label for="monthly_salary" class="block text-xs font-semibold uppercase tracking-wider text-outline mb-2">Monthly Salary</label>
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-ink select-none">{{ $settings->currency ?? 'Rs.' }}</span>
            <input type="number" id="monthly_salary" name="monthly_salary" value="{{ old('monthly_salary', $worker->monthly_salary ?? '') }}"
                   placeholder="0.00" step="0.01" min="0"
                   class="flex-1 h-12 px-4 rounded-control border border-outline-variant bg-white text-ink text-sm placeholder:text-outline tabular focus:outline-none focus:border-forest-700 focus:ring-2 focus:ring-forest-700/10 transition"
                   required>
        </div>
        @error('monthly_salary')<p class="mt-2 text-xs text-clay font-medium">{{ $message }}</p>@enderror
    </div>

    <div class="mb-5">
        <label for="joined_on" class="block text-xs font-semibold uppercase tracking-wider text-outline mb-2">Joined On</label>
        <input type="date" id="joined_on" name="joined_on" value="{{ old('joined_on', isset($worker) ? $worker->joined_on?->format('Y-m-d') : now()->format('Y-m-d')) }}"
               class="w-full h-12 px-4 rounded-control border border-outline-variant bg-white text-ink text-sm focus:outline-none focus:border-forest-700 focus:ring-2 focus:ring-forest-700/10 transition"
               required>
        <p class="mt-1 text-xs text-ink-variant">Used to work out their pay for the month they started (only that first month is prorated by days).</p>
        @error('joined_on')<p class="mt-2 text-xs text-clay font-medium">{{ $message }}</p>@enderror
        @if(isset($worker) && $worker->left_on)
            <p class="mt-2 text-xs text-clay font-medium">Marked inactive on {{ $worker->left_on->format('M j, Y') }} — that month's salary was prorated automatically. Reactivate to clear this.</p>
        @endif
    </div>

    <div class="mb-8">
        <label class="flex items-center gap-3 cursor-pointer">
            {{-- Hidden fallback: unchecked checkboxes send nothing, so without this
                 is_active silently reverted to true on every save --}}
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $worker->is_active ?? true))
                   class="h-5 w-5 rounded border-outline-variant text-forest-700 focus:ring-forest-700/20 cursor-pointer">
            <span class="text-sm font-medium text-ink">Active</span>
        </label>
        <p class="mt-1 text-xs text-ink-variant ml-8">Uncheck to mark as inactive.</p>
        @error('is_active')<p class="mt-1 text-xs text-clay font-medium ml-8">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" :disabled="exactMatch && !{{ json_encode($isEdit) }}"
                class="inline-flex items-center gap-2 rounded-control bg-forest-900 text-white px-6 py-2.5 text-sm font-semibold hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed transition shadow-sm">
            {{ $isEdit ? 'Update Worker' : 'Add Worker' }}
        </button>
        <a href="{{ route('workers.index') }}" class="inline-flex items-center rounded-control px-5 py-2.5 text-sm font-semibold text-ink-variant hover:bg-surface-container-low transition">
            Cancel
        </a>
    </div>
</form>