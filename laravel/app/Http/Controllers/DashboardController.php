<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $bills = $user->splitBills()
            ->with(['participants', 'shares', 'items'])
            ->get();

        $activeBills = $bills->where('status', '!=', 'archived');

        $totalSpent = $activeBills->sum(function ($bill) {
            $share = $bill->currentUserShare();

            return $share?->final_total ?? 0;
        });

        $totalBillVolume = $activeBills->sum('grand_total');
        $pendingBills = $activeBills->filter(fn ($bill) => $bill->isPending())->values();
        $recentBills = $bills->take(4);

        return view('dashboard.index', [
            'totalSpent' => $totalSpent,
            'totalBillVolume' => $totalBillVolume,
            'pendingBills' => $pendingBills,
            'recentBills' => $recentBills,
            'totalBillsCount' => $activeBills->count(),
            'completedBillsCount' => $activeBills->where('status', 'completed')->count(),
            'pendingBillsCount' => $pendingBills->count(),
        ]);
    }
}
