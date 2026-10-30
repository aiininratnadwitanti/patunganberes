<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\IuranPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestIuranController extends Controller
{
    public function show(string $code, string $token): View
    {
        $pool = $this->pool($code, $token);

        return view('guest.iuran', [
            'pool' => $pool,
        ]);
    }

    public function report(Request $request, string $code, string $token): RedirectResponse
    {
        $pool = $this->pool($code, $token);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $proofUrl = $request->hasFile('proof')
            ? $request->file('proof')->store('proofs', 'public')
            : null;

        $index = (int) $pool->participants()->max('sort_order') + 1;

        $pool->participants()->create([
            'name' => $data['name'],
            'color' => participant_color($index),
            'target_fee' => $pool->fee_per_person,
            'paid_amount' => (int) $data['amount'],
            'status' => 'waiting_verification',
            'proof_url' => $proofUrl,
            'notes' => $data['notes'] ?? null,
            'paid_at' => now(),
            'sort_order' => $index,
        ]);

        // Nominal langsung mengurangi sisa target, verifikasi tinggal menguatkan bukti.
        $pool->refreshCollected();

        AppNotification::create([
            'user_id' => $pool->user_id,
            'title' => 'Setoran Iuran Baru',
            'message' => $data['name'].' menyetor '.format_rupiah((int) $data['amount']).' untuk "'.$pool->title.'".',
            'type' => 'iuran_reported',
            'related_id' => $pool->code,
            'related_type' => 'iuran',
        ]);

        return redirect()->route('guest.iuran', [$pool->code, $pool->public_token])
            ->with('toast', ['type' => 'success', 'message' => 'Terima kasih! Setoranmu tercatat dan menunggu verifikasi.']);
    }

    private function pool(string $code, string $token): IuranPool
    {
        return IuranPool::where('code', $code)->where('public_token', $token)->firstOrFail();
    }
}
