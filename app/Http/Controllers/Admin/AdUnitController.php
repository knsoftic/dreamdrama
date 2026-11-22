<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdUnitController extends Controller
{
    public function index()
    {
        return view('admin.ads.index', ['units' => AdUnit::query()->orderBy('placement')->orderByDesc('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.ads.form', ['unit' => new AdUnit(['is_active' => true, 'network' => 'adsterra', 'placement' => 'ad_break'])]);
    }

    public function store(Request $request)
    {
        AdUnit::query()->create($this->validated($request));

        return redirect()->route('admin.ads.index')->with('success', 'Ad unit added.');
    }

    public function edit(AdUnit $ad)
    {
        return view('admin.ads.form', ['unit' => $ad]);
    }

    public function update(Request $request, AdUnit $ad)
    {
        $ad->update($this->validated($request));

        return redirect()->route('admin.ads.index')->with('success', 'Ad unit updated.');
    }

    public function destroy(AdUnit $ad)
    {
        $ad->delete();

        return redirect()->route('admin.ads.index')->with('success', 'Ad unit deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'network' => ['required', Rule::in(array_keys(AdUnit::NETWORKS))],
            'placement' => ['required', Rule::in(array_keys(AdUnit::PLACEMENTS))],
            'code' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
