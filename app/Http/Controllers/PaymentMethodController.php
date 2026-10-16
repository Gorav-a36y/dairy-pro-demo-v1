<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    /** payment_methods.code is a string(30) column. */
    private const CODE_MAX_LENGTH = 30;

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('payment_methods', 'name'),
            ],
        ]);

        // Slug the name, cut it to the column size, and drop a trailing "_".
        $code = str($data['name'])
            ->slug('_')
            ->limit(self::CODE_MAX_LENGTH, '')
            ->rtrim('_')
            ->toString();

        if ($code === '') {
            return back()->withInput()->with('error', 'Please use a name with letters or numbers.');
        }

        if (PaymentMethod::where('code', $code)->exists()) {
            return back()->withInput()->with('error', 'A payment method with that name already exists.');
        }

        PaymentMethod::create([
            'name' => $data['name'],
            'code' => $code,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => (int) PaymentMethod::max('sort_order') + 1,
        ]);

        return back()->with('success', "\"{$data['name']}\" added as a payment method.");
    }

    public function toggleActive(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->is_system) {
            return back()->with('error', "{$paymentMethod->name} must always stay available and can't be deactivated.");
        }

        $paymentMethod->update(['is_active' => !$paymentMethod->is_active]);

        return back()->with('success', $paymentMethod->is_active
            ? "{$paymentMethod->name} activated."
            : "{$paymentMethod->name} deactivated."
        );
    }
}