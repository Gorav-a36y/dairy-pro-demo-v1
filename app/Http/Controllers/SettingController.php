<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $setting = Setting::current();

        return view('settings.edit', compact('setting'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'dairy_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'currency' => 'required|string|max:10',
            'invoice_region' => 'required|string|max:50',
        ]);

        $setting = Setting::current();
        $setting->update($data);

        return redirect()->route('settings.edit')->with('success', 'Settings updated successfully.');
    }
}
