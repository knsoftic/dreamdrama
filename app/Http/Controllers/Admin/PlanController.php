<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()
                ->withCount(['subscriptions as active_users' => fn ($q) => $q->where('status', 'active')
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))])
                ->orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan([
            'is_active' => true, 'duration_days' => 30, 'daily_cycle_limit' => 5, 'rate_multiplier' => 1,
            'daily_earning_cap' => 0, 'min_withdrawal' => 500, 'color' => '#8b5cf6',
        ])]);
    }

    public function store(Request $request)
    {
        $plan = Plan::query()->create($this->validated($request));
        $this->syncDefault($plan);

        return redirect()->route('admin.plans.index')->with('success', 'Plan ban gaya.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));
        $this->syncDefault($plan);

        return redirect()->route('admin.plans.index')->with('success', 'Plan update ho gaya.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->is_default || $plan->subscriptions()->exists()) {
            $plan->update(['is_active' => false]);

            return back()->with('success', 'Is plan par users hain, is liye delete ki jagah inactive kar diya gaya.');
        }

        $plan->delete();

        return back()->with('success', 'Plan delete ho gaya.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:0'],
            'daily_cycle_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'rate_multiplier' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'daily_earning_cap' => ['required', 'numeric', 'min:0'],
            'min_withdrawal' => ['required', 'numeric', 'min:0'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $slug = Str::slug($data['name']);
        $exists = Plan::query()->where('slug', $slug)->when($plan, fn ($q) => $q->where('id', '!=', $plan->id))->exists();
        $data['slug'] = $exists ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }

    private function syncDefault(Plan $plan): void
    {
        if ($plan->is_default) {
            Plan::query()->where('id', '!=', $plan->id)->update(['is_default' => false]);
        }
    }
}
