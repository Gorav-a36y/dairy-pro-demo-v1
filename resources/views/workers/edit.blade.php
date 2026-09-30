@extends('layouts.app')
@section('title', 'Edit Worker')
@section('page-title', 'Edit Worker')
@section('content')
<div class="mb-6">
    <a href="{{ route('workers.index') }}" class="inline-flex items-center gap-1.5 text-sm text-outline hover:text-ink-variant transition">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Workers
    </a>
</div>
<div class="max-w-xl">
    <div class="bg-white rounded-bento border border-outline-variant shadow-sm p-6 lg:p-8">
        <h2 class="font-serif text-xl font-semibold text-ink mb-1">Worker Details</h2>
        <p class="text-sm text-ink-variant mb-8">Update this staff member's details.</p>
        @include('workers._form')
    </div>
</div>
@endsection