<?php

use App\Models\BankAccount;
use App\Models\BillParticipant;
use App\Models\ParticipantShare;
use App\Models\SplitBill;

if (! function_exists('format_rupiah')) {
    function format_rupiah(mixed $amount): string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return 'Rp0';
        }

        $amount = (float) $amount;
        $negative = $amount < 0;
        $formatted = number_format((int) round(abs($amount)), 0, ',', '.');

        return ($negative ? '-' : '').'Rp'.$formatted;
    }
}

if (! function_exists('normalize_time')) {
    /**
     * The `time` column is CHAR(5) and expects "HH:MM". Scanner output may
     * include seconds ("10:11:07"), so reduce any readable time to that shape.
     */
    function normalize_time(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})\s*[:.]\s*(\d{1,2})/', $value, $matches)) {
            $hours = min(23, (int) $matches[1]);
            $minutes = min(59, (int) $matches[2]);

            return sprintf('%02d:%02d', $hours, $minutes);
        }

        return null;
    }
}

if (! function_exists('normalize_date')) {
    /**
     * Coerce scanner output into a MySQL DATE ("Y-m-d"). Receipts commonly use
     * "01/01/2023" or "01-01-2023", which would otherwise fail the insert.
     */
    function normalize_date(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
        }

        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y', 'd M Y'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);

            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }
}

if (! function_exists('participant_initials')) {
    function participant_initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach ($words as $word) {
            if ($word !== '') {
                $initials .= mb_substr($word, 0, 1);
            }
        }

        return mb_strtoupper(mb_substr($initials ?: 'P', 0, 2));
    }
}

if (! function_exists('participant_color')) {
    function participant_color(int $index): string
    {
        $palette = [
            '#16A34A',
            '#2563EB',
            '#9333EA',
            '#EA580C',
            '#DB2777',
            '#0891B2',
            '#D97706',
            '#4F46E5',
        ];

        return $palette[$index % count($palette)];
    }
}

if (! function_exists('whatsapp_url')) {
    function whatsapp_url(string $message): string
    {
        return 'https://api.whatsapp.com/send?text='.rawurlencode($message);
    }
}

if (! function_exists('guest_receipt_url')) {
    function guest_receipt_url(SplitBill $splitBill, BillParticipant $participant, ?string $appUrl = null): string
    {
        if ($appUrl) {
            return rtrim($appUrl, '/').'/receipt/'.$splitBill->code.'/'.$participant->public_token;
        }

        return route('guest.receipt', [$splitBill->code, $participant->public_token]);
    }
}

if (! function_exists('split_bill_whatsapp_message')) {
    function split_bill_whatsapp_message(
        SplitBill $splitBill,
        ParticipantShare $share,
        ?BankAccount $bankInfo = null,
        ?string $appUrl = null
    ): string {
        $itemLines = collect($share->items_list ?? [])
            ->map(fn (array $item) => '- '.$item['name'].' ('.($item['portion_text'] ?? '').'): '.format_rupiah($item['price_share'] ?? 0))
            ->implode("\n");

        $feesText = '';

        if (($share->tax_share ?? 0) > 0) {
            $feesText .= "\n- Bagian PPN: ".format_rupiah($share->tax_share);
        }
        if (($share->service_share ?? 0) > 0) {
            $feesText .= "\n- Biaya Layanan: ".format_rupiah($share->service_share);
        }
        if (($share->discount_share ?? 0) > 0) {
            $feesText .= "\n- Diskon: -".format_rupiah($share->discount_share);
        }
        if (($share->other_fees_share ?? 0) > 0) {
            $feesText .= "\n- Biaya Lainnya: ".format_rupiah($share->other_fees_share);
        }
        if (($share->rounding_adjustment ?? 0) !== 0) {
            $feesText .= "\n- Pembulatan: ".format_rupiah($share->rounding_adjustment);
        }

        $bankText = '';
        if ($bankInfo && $bankInfo->account_number) {
            $bankText = "\n\n*Rekening Pembayaran:*\n".$bankInfo->bank.' : *'.$bankInfo->account_number."*\na.n ".$bankInfo->account_holder;
        }

        $participant = $share->participant;
        $linkText = '';

        if ($participant) {
            $linkText = "\n\n*Lihat struk digital lengkapmu di sini:*\n".guest_receipt_url($splitBill, $participant, $appUrl);
        }

        $title = $splitBill->title ?: $splitBill->merchant_name;
        $participantName = $participant?->name ?? $share->participant_name;

        return "Halo *{$participantName}*!\n"
            ."Ini rincian split bill patungan kita untuk *{$title}* via *PatunganBeres*.\n\n"
            ."*Pesananmu:*\n"
            .($itemLines ?: '- (Item bersama)')
            ."{$feesText}\n\n"
            .'*TOTAL TAGIHAN: '.format_rupiah($share->final_total).'*'
            .$bankText.$linkText
            ."\n\n_Catatan: Struk digital resmi dibagikan melalui aplikasi PatunganBeres._\n"
            .'Terima kasih!';
    }
}
