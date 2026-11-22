<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings', [
            'groups' => config('platform.settings'),
            'values' => Setting::allValues(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];
        $fields = [];
        foreach (config('platform.settings') as $group) {
            foreach ($group['fields'] as $key => $field) {
                $fields[$key] = $field;
                $rules[$key] = match ($field['type']) {
                    'number' => ['required', 'integer', 'min:0'],
                    'decimal' => ['required', 'numeric', 'min:0'],
                    'bool' => ['nullable'],
                    'select' => ['required', 'in:'.implode(',', array_keys($field['options']))],
                    'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                    'text' => ['nullable', 'string', 'max:255'],
                    default => ['nullable', 'string', 'max:20000'],
                };
            }
        }

        $rules['videos_per_cycle'] = ['required', 'integer', 'min:1', 'max:200'];
        $rules['ads_per_cycle'] = ['required', 'integer', 'min:0', 'max:200'];
        $rules['heartbeat_seconds'] = ['required', 'integer', 'min:2', 'max:30'];
        $rules['ad_duration_seconds'] = ['required', 'integer', 'min:1', 'max:120'];
        $data = $request->validate($rules);

        $values = [];
        foreach ($fields as $key => $field) {
            $values[$key] = $field['type'] === 'bool' ? ($request->boolean($key) ? '1' : '0') : (string) ($data[$key] ?? '');
        }
        Setting::put($values);

        return back()->with('success', 'Settings saved. New cycles will use the new rates.');
    }
}
