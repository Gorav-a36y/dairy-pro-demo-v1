<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // route('worker') resolves to the bound Worker model instance (not a plain id)
        // once Laravel's implicit route-model-binding has run. Concatenating the model
        // straight into the unique() rule string tried to cast it to string and threw
        // "Object of class App\Models\Worker could not be converted to string" on edit.
        $worker = $this->route('worker');
        $workerId = $worker instanceof \App\Models\Worker ? $worker->id : $worker;

        return [
            'name'           => ['required', 'string', 'max:255', Rule::unique('workers', 'name')->ignore($workerId)],
            'phone_number'   => ['required', 'string', 'max:20', Rule::unique('workers', 'phone_number')->ignore($workerId)],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'joined_on'      => ['required', 'date'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'Worker name is required.',
            'phone_number.required'   => 'Phone number is required.',
            'phone_number.unique'     => 'This phone number is already registered.',
            'monthly_salary.required' => 'Monthly salary is required.',
            'monthly_salary.numeric'  => 'Salary must be a valid number.',
        ];
    }
}