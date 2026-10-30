<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\SplitBill;
use App\Services\SplitBillCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestReceiptController extends Controller
{
    public function __construct(private readonly SplitBillCalculator $calculator) {}

    public function show(string $code, string $token): View
    {
        [$bill, $participant] = $this->resolve($code, $token);

        $bill->load(['items', 'participants.share', 'shares.participant', 'user.bankAccounts']);
        $share = $bill->shareFor($participant);

        if (! $share) {
            $this->calculator->calculateForBill($bill, true);
            $bill->load('shares.participant', 'participants.share');
            $share = $bill->shareFor($participant);
        }

        return view('guest.receipt', [
            'bill' => $bill,
            'participant' => $participant,
            'share' => $share,
            'defaultBank' => $bill->user?->defaultBankAccount(),
        ]);
    }

    public function report(Request $request, string $code, string $token): RedirectResponse
    {
        [$bill, $participant] = $this->resolve($code, $token);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $proofUrl = $participant->payment_proof_url;

        if ($request->hasFile('proof')) {
            $path = $request->file('proof')->store('proofs', 'public');
            $proofUrl = $path;
        }

        $participant->update([
            'payment_status' => 'waiting_verification',
            'payment_method' => $data['payment_method'],
            'verification_notes' => $data['notes'] ?? null,
            'payment_proof_url' => $proofUrl,
        ]);

        $bill->load('participants');
        $bill->refreshPaymentStatus();

        AppNotification::create([
            'user_id' => $bill->user_id,
            'title' => 'Pembayaran Baru Dilaporkan',
            'message' => $participant->name.' melaporkan pembayaran untuk "'.$bill->title.'".',
            'type' => 'payment_reported',
            'related_id' => $bill->code,
            'related_type' => 'split',
        ]);

        return redirect()->route('guest.receipt', [$bill->code, $participant->public_token])
            ->with('toast', ['type' => 'success', 'message' => 'Terima kasih! Pembayaranmu menunggu verifikasi.']);
    }

    private function resolve(string $code, string $token): array
    {
        $bill = SplitBill::where('code', $code)->firstOrFail();

        $participant = $bill->participants()
            ->where('public_token', $token)
            ->firstOrFail();

        return [$bill, $participant];
    }
}
