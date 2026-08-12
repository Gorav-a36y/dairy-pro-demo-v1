@php $isEdit = isset($customer); @endphp

<form method="POST" action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}" class="max-w-2xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-5">
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Customer / Business Name</label>
            <input type="text" name="name" value="{{ old('name', $customer->name ?? '') }}" required
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
        </div>
        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Email</label>
                <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Address</label>
            <textarea name="address" rows="2" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">{{ old('address', $customer->address ?? '') }}</textarea>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Update Customer' : 'Save Customer' }}
        </button>
        <a href="{{ route('customers.index') }}" class="text-sm font-medium text-outline hover:text-ink px-3">Cancel</a>
    </div>
</form>
