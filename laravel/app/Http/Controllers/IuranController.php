<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\IuranParticipant;
use App\Models\IuranPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IuranController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $pools = $user->iuranPools()->with('participants')->get();

        $selectedId = (int) $request->query('pool', 0);
        $selected = $pools->firstWhere('id', $selectedId) ?? $pools->first();

        $totalCollected = $pools->sum('current_collected');
        $totalTarget = $pools->sum('target_amount');
        $activeCount = $pools->where('status', 'active')->count();

        return view('iuran.index', [
            'pools' => $pools,
            'selected' => $selected,
            'totalCollected' => $totalCollected,
            'totalTarget' => $totalTarget,
            'activeCount' => $activeCount,
            'defaultBank' => $user->defaultBankAccount(),
            'poolLink' => $selected ? route('guest.iuran', [$selected->code, $selected->public_token]) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_amount' => ['required', 'integer', 'min:1000'],
            'fee_per_person' => ['nullable', 'integer', 'min:0'],
            'pricing_type' => ['required', Rule::in(['fixed', 'flexible'])],
            'deadline' => ['required', 'date'],
            'category' => ['required', 'string', 'max:50'],
            'bank_name' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:100'],
        ]);

        $fee = (int) ($data['fee_per_person'] ?? 0);

        if ($fee <= 0) {
            $fee = (int) ceil($data['target_amount'] / 10);
        }

        // Iuran baru selalu mulai dari nol dan tanpa peserta bawaan: satu link
        // dibagikan ke kelompok, tiap orang mengisi setorannya sendiri.
        $pool = auth()->user()->iuranPools()->create([
            'code' => 'IRN-'.now()->format('Y').'-'.Str::upper(Str::random(4)),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_amount' => $data['target_amount'],
            'current_collected' => 0,
            'fee_per_person' => $fee,
            'pricing_type' => $data['pricing_type'],
            'deadline' => $data['deadline'],
            'category' => $data['category'],
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_holder' => $data['account_holder'],
            'status' => 'active',
        ]);

        AppNotification::create([
            'user_id' => auth()->id(),
            'title' => 'Iuran Baru Dibuat',
            'message' => 'Iuran "'.$pool->title.'" dengan target '.format_rupiah($pool->target_amount).' sudah dibuat.',
            'type' => 'iuran_created',
            'related_id' => $pool->code,
            'related_type' => 'iuran',
        ]);

        return redirect()->route('iuran.index', ['pool' => $pool->id])
            ->with('toast', ['type' => 'success', 'message' => 'Iuran berhasil dibuat.']);
    }

    public function destroy(IuranPool $iuran): RedirectResponse
    {
        abort_unless($iuran->user_id === auth()->id(), 403);

        $title = $iuran->title;

        $iuran->delete();

        return redirect()->route('iuran.index')
            ->with('toast', ['type' => 'success', 'message' => 'Iuran "'.$title.'" berhasil dihapus.']);
    }

    public function updateParticipantStatus(Request $request, IuranPool $iuran): RedirectResponse
    {
        abort_unless($iuran->user_id === auth()->id(), 403);

        $data = $request->validate([
            'participant_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(IuranParticipant::STATUSES)],
        ]);

        $participant = $iuran->participants()->whereKey($data['participant_id'])->firstOrFail();

        if ($data['status'] === 'paid') {
            // Verifikasi: nominal yang dilaporkan tetap dihitung, status menjadi Lunas.
            $participant->update([
                'status' => 'paid',
                'paid_amount' => $participant->paid_amount > 0 ? $participant->paid_amount : $participant->target_fee,
                'paid_at' => $participant->paid_at ?? now(),
            ]);
        } elseif ($data['status'] === 'rejected') {
            // Tolak: setoran berhenti dihitung sehingga total terkumpul berkurang lagi.
            $participant->update(['status' => 'rejected']);
        } elseif ($data['status'] === 'waiting_verification') {
            // Batal verifikasi: kembali menunggu verifikasi, nominal tetap dihitung.
            $participant->update([
                'status' => 'waiting_verification',
                'paid_at' => null,
            ]);
        } else {
            $participant->update([
                'status' => 'unpaid',
                'paid_amount' => 0,
                'paid_at' => null,
            ]);
        }

        $iuran->refreshCollected();

        AppNotification::create([
            'user_id' => $iuran->user_id,
            'title' => 'Status Iuran Diperbarui',
            'message' => 'Status '.$participant->name.' pada "'.$iuran->title.'" kini '.$participant->status_label.'.',
            'type' => 'iuran_updated',
            'related_id' => $iuran->code,
            'related_type' => 'iuran',
        ]);

        return redirect()->route('iuran.index', ['pool' => $iuran->id])
            ->with('toast', ['type' => 'success', 'message' => 'Status setoran berhasil diperbarui.']);
    }
}
