<?php

namespace App\Http\Controllers\SubAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $status = $request->query('status');   // approved | pending | rejected

        $deposits = Payment::query()
            ->where('sub_admin_id', $me->id)
            ->with(['user:id,name,phone', 'plan:id,name,color'])
            ->when($status && in_array($status, ['approved', 'pending', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($query, $s) => $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('subadmin.deposits', [
            'deposits' => $deposits,
            'status' => $status,
            'taxPercent' => (float) $me->commission_percent,
        ]);
    }
}
