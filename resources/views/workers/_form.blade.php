@php
    $isEdit = isset($worker);
    $editRouteTemplate = route('workers.edit', ['worker' => '___ID___']);
    $today = $settings->now()->format('Y-m-d');
@endphp

<form action="{{ $isEdit ? route('workers.update', $worker) : route('workers.store') }}" method="POST"
      novalidate
      x-data="{
          name: {{ json_encode(old('name', $worker->name ?? '')) }},
          phoneNumber: {{ json_encode(old('phone_number', $worker->phone_number ?? '')) }},
          salary: {{ json_encode((string) old('monthly_salary', $worker->monthly_salary ?? '')) }},
          joinedOn: {{ json_encode(old('joined_on', isset($worker) ? $worker->joined_on?->format('Y-m-d') : $today)) }},
          today: {{ json_encode($today) }},
          allWorkers: {{ json_encode($allWorkers ?? []) }},
          nameOpen: false,
          submitted: false,

          // Same rules as App\Support\PhoneNumber::normalize() on the server
          normalizePhone(value) {
              value = (value || '').trim();
              if (value === '' || /[^\d\s+\-().]/.test(value)) return value;
              const digits = value.replace(/\D+/g, '');
              if (digits === '') return value;
              if (digits.startsWith('0092')) {
                  const rest = digits.slice(4);
                  return rest.startsWith('0') ? rest : '0' + rest;
              }
              if (digits.startsWith('92') && digits.length >= 11) {
                  const rest = digits.slice(2);
                  return rest.startsWith('0') ? rest : '0' + rest;
              }
              if (digits.length === 10 && digits.startsWith('3')) return '0' + digits;
              return digits;
          },

          get normalizedPhone() {
              return this.normalizePhone(this.phoneNumber);
          },

          get filteredWorkers() {
              if (this.name.trim().length < 1) return [];
              const q = this.name.toLowerCase();
              return this.allWorkers.filter(w => w.name.toLowerCase().includes(q));
          },

          get exactMatch() {
              const n = this.name.toLowerCase().trim();
              return n.length > 0 ? this.allWorkers.find(w => w.name.toLowerCase().trim() === n) : undefined;
          },

          get phoneValid() {
              return /^03\d{9}$/.test(this.normalizedPhone);
          },

          get phoneMatch() {
              if (!this.phoneValid) return undefined;
              return this.allWorkers.find(w => this.normalizePhone(w.phone_number) === this.normalizedPhone);
          },

          get salaryValid() {
              const s = parseFloat(this.salary);
              return !isNaN(s) && s > 0;
          },

          get dateValid() {
              return /^\d{4}-\d{2}-\d{2}$/.test(this.joinedOn) && this.joinedOn <= this.today;
          },

          get canSubmit() {
              return this.name.trim().length > 0
                  && this.phoneNumber.trim().length > 0 && this.phoneValid && !this.phoneMatch
                  && this.salaryValid
                  && this.dateValid
                  && !this.exactMatch;
          },

          selectExisting(w) {
              this.name = w.name;
              this.nameOpen = false;
          },

          // Every way of saving (button, Enter, Ctrl+S, Ctrl+Enter) goes through here
          trySubmit() {
              this.submitted = true;
              if (!this.canSubmit) return;
              // send the clean phone format to the server
              this.phoneNumber = this.normalizedPhone;
              this.$nextTick(() => this.$el.submit());
          }
      }"
      @submit.prevent="trySubmit()"
      @keydown.window="
          if ((($event.ctrlKey || $event.metaKey) && ($event.key === 's' || $event.key === 'S' || $event.key === 'Enter'))) {
              $event.preventDefault();
              trySubmit();
          }
      ">
    @csrf
    @if($isEdit) @method('PUT') @endif

    {{-- Full Name --}}
    <div class="mb-5 relative" @click.outside="nameOpen = false">
        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-outline mb-2">Full Name <span class="text-clay">*</span></label>
        <input type="text" id="name" name="name" x-model="name" autocomplete="off"
               placeholder="e.g. John Smith" maxlength="255"
               @focus="nameOpen = true"
               @input="nameOpen = true"
               @keydown.escape="nameOpen = false"
               :class="exactMatch ? 'border-clay focus:ring-clay/20 focus:border-clay' : 'border-outline-variant focus:border-forest-700 focus:ring-forest-700/10'"
               class="w-full h-12 px-4 rounded-control border bg-white text-ink text-sm placeholder:text-outline focus:outline-none focus:ring-2 transition"
               required>
        <p x-show="submitted && name.trim().length === 0" x-cloak class="mt-2 text-xs text-clay font-medium">Worker name is required.</p>
        @error('name')<p class="mt-2 text-xs text-clay font-medium">{{ $message }}</p>@enderror

        <div x-show="nameOpen && filteredWorkers.length > 0" x-cloak x-transition
             class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
            <div class="px-4 py-2 text-[11px] font-semibold text-outline uppercase tracking-wide border-b border-outline-variant/60">
                Already in database
            </div>
            <template x-for="w in filteredWorkers" :key="w.id">
                <button type="button" @click="selectExisting(w)"
                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3 transition"
                        :class="w.name.toLowerCase().trim() === name.toLowerCase().trim() ? 'bg-clay-container/40 text-clay' : ''">
                    <span class="font-medium" :class="w.name.toLowerCase().trim() === name.toLowerCase().trim() ? 'text-clay' : 'text-ink'" x-text="w.name"></span>
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
                    <a x-show="exactMatch" :href="exactMatch ? '{{ $editRouteTemplate }}'.replace('___ID___', exactMatch.id) : '#'"
                       class="underline hover:text-clay/70 font-semibold">Edit it instead</a>.
                </p>
            </div>
        </div>
    </div>

    {{-- Phone Number --}}
    <div class="mb-5">
        <label for="phone_number" class="block text-xs font-semibold uppercase tracking-wider text-outline mb-2">Phone Number <span class="text-clay">*</span></label>
        <input type="tel" id="phone_number" name="phone_number" x-model="phoneNumber"
               placeholder="e.g. 0300 1234567 or +92 300 1234567" maxlength="25"
               class="w-full h-12 px-4 rounded-control border border-outline-variant bg-white text-ink text-sm placeholder:text-outline focus:outline-none focus:border-forest-700 focus:ring-2 focus:ring-forest-700/10 transition"
               :class="(phoneNumber.trim().length > 0 && !phoneValid) || phoneMatch ? 'border-clay' : ''"
               required>
        <p x-show="submitted && phoneNumber.trim().length === 0" x-cloak class="mt-2 text-xs text-clay font-medium">Phone number is required.</p>
        <p x-show="phoneNumber.trim().length > 0 && !phoneValid" x-cloak class="mt-2 text-xs text-clay font-medium">
            Enter a valid Pakistani mobile number, e.g. 0300 1234567 or +92 300 1234567.
        </p>
        <p x-show="phoneValid && !phoneMatch && normalizedPhone !== phoneNumber.trim()" x-cloak class="mt-1.5 text-xs text-ink-variant">
            Will be saved as <span class="font-semibold" x-text="normalizedPhone"></span>
        </p>
        @error('phone_number')<p class="mt-2 text-xs text-clay font-medium">{{ $message }}</p>@enderror
        <div x-show="phoneMatch" x-cloak x-transition
             class="mt-2.5 p-3 rounded-control bg-clay-container/50 border border-clay/30 flex items-start gap-2">
            <i data-lucide="alert-circle" class="h-4 w-4 text-clay shrink-0 mt-0.5"></i>
            <p class="text-sm font-semibold text-clay">
                This phone number is already registered to <span x-text="phoneMatch?.name"></span>.
            </p>
        </div>
    </div>

    {{-- Monthly Salary --}}
    <div class="mb-5">
        <label for="monthly_salary" class="block text-xs font-semibold uppercase tracking-wider text-outline mb-2">Monthly Salary <span class="text-clay">*</span></label>
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-ink select-none">{{ $settings->currency ?? 'Rs.' }}</span>
            <input type="number" id="monthly_salary" name="monthly_salary" x-model="salary"
                   placeholder="0.00" step="0.01" min="0.01" inputmode="decimal"
                   class="flex-1 h-12 px-4 rounded-control border border-outline-variant bg-white text-ink text-sm placeholder:text-outline tabular focus:outline-none focus:border-forest-700 focus:ring-2 focus:ring-forest-700/10 transition"
                   :class="salary !== '' && !salaryValid ? 'border-clay' : ''"
                   required>
        </div>
        <p x-show="submitted && salary === ''" x-cloak class="mt-2 text-xs text-clay font-medium">Monthly salary is required.</p>
        <p x-show="salary !== '' && !salaryValid" x-cloak class="mt-2 text-xs text-clay font-medium">Monthly salary must be greater than zero.</p>
        @error('monthly_salary')<p class="mt-2 text-xs text-clay font-medium">{{ $message }}</p>@enderror
    </div>

    {{-- Joined On --}}
    <div class="mb-5">
        <label for="joined_on" class="block text-xs font-semibold uppercase tracking-wider text-outline mb-2">Joined On <span class="text-clay">*</span></label>
        <input type="date" id="joined_on" name="joined_on" x-model="joinedOn" max="{{ $today }}"
               class="w-full h-12 px-4 rounded-control border border-outline-variant bg-white text-ink text-sm focus:outline-none focus:border-forest-700 focus:ring-2 focus:ring-forest-700/10 transition"
               :class="joinedOn !== '' && !dateValid ? 'border-clay' : ''"
               required>
        <p class="mt-1 text-xs text-ink-variant">Used to work out their pay for the month they started (only that first month is prorated by days).</p>
        <p x-show="submitted && joinedOn === ''" x-cloak class="mt-2 text-xs text-clay font-medium">Joining date is required.</p>
        <p x-show="joinedOn !== '' && joinedOn > today" x-cloak class="mt-2 text-xs text-clay font-medium">Joining date cannot be in the future.</p>
        @error('joined_on')<p class="mt-2 text-xs text-clay font-medium">{{ $message }}</p>@enderror
        @if(isset($worker) && $worker->left_on)
            <p class="mt-2 text-xs text-clay font-medium">Marked inactive on {{ $worker->left_on->format('M j, Y') }} — that month's salary was prorated automatically. Reactivate to clear this.</p>
        @endif
    </div>

    {{-- Active --}}
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
        <button type="submit" :disabled="!canSubmit"
                class="inline-flex items-center gap-2 rounded-control bg-forest-900 text-white px-6 py-2.5 text-sm font-semibold hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed transition shadow-sm">
            {{ $isEdit ? 'Update Worker' : 'Add Worker' }}
        </button>
        <a href="{{ route('workers.index') }}" class="inline-flex items-center rounded-control px-5 py-2.5 text-sm font-semibold text-ink-variant hover:bg-surface-container-low transition">
            Cancel
        </a>
        <span class="ml-auto hidden sm:inline text-xs text-outline">Ctrl + S to save</span>
    </div>
</form>
