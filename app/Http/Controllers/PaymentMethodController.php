<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            // Sort after Cash and other custom methods, before Khata entries.
            'sort_order' => (int) PaymentMethod::whereNotIn('code', [
                PaymentMethod::KHATA_CODE,
                PaymentMethod::PARTIAL_KHATA_CODE,
            ])->max('sort_order') + 1,
        ]);

        return back()->with('success', "\"{$data['name']}\" added as a payment method.");
    }

    public function toggleActive(Request $request, PaymentMethod $paymentMethod)
    {
        $request->validate([
            'is_active' => 'sometimes|boolean',
        ]);

        if ($paymentMethod->isSystemMethod()) {
            return back()->with('error', "{$paymentMethod->name} must always stay available and can't be deactivated.");
        }

        return DB::transaction(function () use ($request, $paymentMethod) {
            // Read the newest saved state, not the state loaded a moment ago.
            $method = PaymentMethod::whereKey($paymentMethod->getKey())->firstOrFail();

            if ($method->isSystemMethod()) {
                return back()->with('error', "{$method->name} must always stay available and can't be deactivated.");
            }

            // The switch sends the state the admin WANTS (1 or 0). That way a
            // double-click sends the same wish twice instead of flipping the
            // method back. If a request arrives without it, fall back to a flip.
            $wanted = $request->has('is_active')
                ? $request->boolean('is_active')
                : ! $method->is_active;

            if ($wanted === $method->is_active) {
                return back()->with('success', $method->is_active
                    ? "{$method->name} is already active."
                    : "{$method->name} is already inactive."
                );
            }

            // At least one pay-in-full method must stay active.
            if (! $wanted) {
                $anotherActive = PaymentMethod::whereNotIn('code', [
                    PaymentMethod::KHATA_CODE,
                    PaymentMethod::PARTIAL_KHATA_CODE,
                ])
                    ->where('is_active', true)
                    ->whereKeyNot($method->getKey())
                    ->exists();

                if (! $anotherActive) {
                    return back()->with('error', "{$method->name} is your only pay-in-full method, so it can't be deactivated. Add another method first.");
                }
            }

            $method->update(['is_active' => $wanted]);

            return back()->with('success', $method->is_active
                ? "{$method->name} activated."
                : "{$method->name} deactivated."
            );
        });
    }
}
