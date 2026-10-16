<?php
// app/Http/Controllers/WorkerController.php

namespace App\Http\Controllers;

use App\Http\Requests\WorkerRequest;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WorkerController extends Controller
{
    public function index(): View
    {
        $workers = Worker::latest()->paginate(10);

        return view('workers.index', compact('workers'));
    }

    public function create(): View
    {
        // phone_number is needed by the form for the live duplicate check
        $allWorkers = Worker::select('id', 'name', 'phone_number')->orderBy('name')->get();

        return view('workers.create', compact('allWorkers'));
    }

    public function store(WorkerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        Worker::create($data);

        return redirect()
            ->route('workers.index')
            ->with('success', 'Worker added successfully.');
    }

    public function edit(Worker $worker): View
    {
        $allWorkers = Worker::select('id', 'name', 'phone_number')
            ->where('id', '!=', $worker->id)
            ->orderBy('name')
            ->get();

        return view('workers.edit', compact('worker', 'allWorkers'));
    }

    public function update(WorkerRequest $request, Worker $worker): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $worker->update($data);

        return redirect()
            ->route('workers.index')
            ->with('success', 'Worker updated successfully.');
    }

    public function toggleActive(Worker $worker): RedirectResponse
    {
        $worker->update([
            'is_active' => ! $worker->is_active,
        ]);

        return back()->with('success', 'Worker status updated.');
    }
}
