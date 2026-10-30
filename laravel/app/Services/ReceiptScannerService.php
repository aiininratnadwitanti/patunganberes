<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReceiptScannerService
{
    private const MAX_DIMENSION = 1600;

    private const JPEG_QUALITY = 80;

    private const MAX_ATTEMPTS = 5;

    private const PER_MODEL_TIMEOUT = 25;

    private const TOTAL_BUDGET_SECONDS = 45;

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Scan a receipt image with Gemini.
     *
     * The configured model is tried first, then each fallback model, so a model
     * that is deprecated or briefly overloaded does not break scanning.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the scanner is not configured or every model fails.
     */
    public function scan(string $contents, string $mimeType = 'image/jpeg'): array
    {
        $apiKey = config('services.gemini.key');

        if (blank($apiKey)) {
            throw new RuntimeException('Fitur scan struk butuh GEMINI_API_KEY yang aktif. Isi di file .env, atau gunakan input manual.');
        }

        $endpoint = rtrim((string) config('services.gemini.endpoint'), '/');
        $image = $this->prepareImage($contents, $mimeType);
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => $this->prompt()],
                    ['inline_data' => [
                        'mime_type' => $image['mime'],
                        'data' => base64_encode($image['data']),
                    ]],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'thinkingConfig' => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];

        $deadline = microtime(true) + self::TOTAL_BUDGET_SECONDS;
        $unreadable = false;
        $quotaExhausted = false;

        foreach ($this->candidateModels() as $model) {
            $remaining = (int) floor($deadline - microtime(true));

            if ($remaining < 1) {
                break;
            }

            $result = $this->request($endpoint, $model, $apiKey, $payload, min(self::PER_MODEL_TIMEOUT, $remaining));

            if ($result['status'] === 'quota') {
                $quotaExhausted = true;

                continue;
            }

            if ($result['status'] !== 'ok') {
                continue;
            }

            $parsed = $this->decode($result['text']);

            if (is_array($parsed)) {
                return $this->normalize($parsed);
            }

            $unreadable = true;
        }

        if ($unreadable) {
            throw new RuntimeException('Struk tidak bisa dibaca otomatis. Coba foto ulang dengan pencahayaan lebih baik, atau input manual.');
        }

        if ($quotaExhausted) {
            throw new RuntimeException('Kuota AI harian sudah habis. Coba lagi nanti atau gunakan input manual.');
        }

        throw new RuntimeException('Layanan AI sedang tidak tersedia. Coba lagi sebentar lagi atau gunakan input manual.');
    }

    /**
     * Downscale and re-encode the upload so phone photos are sent at a
     * predictable, small size. Gemini reads them faster and more reliably.
     *
     * @return array{data: string, mime: string}
     */
    private function prepareImage(string $contents, string $mimeType): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return ['data' => $contents, 'mime' => $mimeType];
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return ['data' => $contents, 'mime' => $mimeType];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        $encoded = imagejpeg($canvas, null, self::JPEG_QUALITY);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        if ($encoded === false || $jpeg === false || $jpeg === '') {
            return ['data' => $contents, 'mime' => $mimeType];
        }

        return ['data' => $jpeg, 'mime' => 'image/jpeg'];
    }

    /**
     * @return list<string>
     */
    private function candidateModels(): array
    {
        $models = array_merge(
            [(string) config('services.gemini.model')],
            (array) config('services.gemini.fallback_models', []),
        );

        $models = array_values(array_unique(array_filter(array_map('trim', $models))));

        if ($models === []) {
            $models = ['gemini-3.6-flash'];
        }

        return array_slice($models, 0, self::MAX_ATTEMPTS);
    }

    /**
     * Send one request to one model. A short timeout keeps a hanging model from
     * stalling the whole scan, so the next candidate starts quickly.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: 'ok'|'quota'|'unavailable', text: string}
     */
    private function request(string $endpoint, string $model, string $apiKey, array $payload, int $timeout): array
    {
        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($timeout)->post($endpoint.'/'.$model.':generateContent', $payload);
        } catch (\Throwable $e) {
            Log::warning('Gemini scan request error', ['model' => $model, 'error' => $e->getMessage()]);

            return ['status' => 'unavailable', 'text' => ''];
        }

        if ($response->status() === 429) {
            Log::warning('Gemini scan quota exceeded', ['model' => $model]);

            return ['status' => 'quota', 'text' => ''];
        }

        if (! $response->successful()) {
            Log::warning('Gemini scan failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return ['status' => 'unavailable', 'text' => ''];
        }

        return [
            'status' => 'ok',
            'text' => (string) $response->json('candidates.0.content.parts.0.text', ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $text): ?array
    {
        $parsed = json_decode($text, true);

        if (! is_array($parsed) && preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $parsed = json_decode($matches[0], true);
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        $items = [];

        foreach (($raw['items'] ?? []) as $index => $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = (int) ($item['unitPrice'] ?? $item['unit_price'] ?? $item['price'] ?? 0);

            $items[] = [
                'name' => $item['name'] ?? 'Item '.($index + 1),
                'quantity' => max(1, $quantity),
                'unit_price' => $unitPrice,
                'total_price' => (int) ($item['totalPrice'] ?? $item['total_price'] ?? ($quantity * $unitPrice)),
                'confidence' => ($item['confidence'] ?? 'high') === 'needs_review' ? 'needs_review' : 'high',
                'notes' => $item['notes'] ?? null,
            ];
        }

        $subtotal = (int) ($raw['subtotal'] ?? array_sum(array_column($items, 'total_price')));
        $taxAmount = (int) ($raw['taxAmount'] ?? $raw['tax_amount'] ?? 0);
        $serviceAmount = (int) ($raw['serviceChargeAmount'] ?? $raw['service_charge_amount'] ?? 0);
        $discountAmount = (int) ($raw['discountAmount'] ?? $raw['discount_amount'] ?? 0);
        $otherFees = (int) ($raw['otherFeesAmount'] ?? $raw['other_fees_amount'] ?? 0);

        return [
            'merchant_name' => $raw['merchantName'] ?? $raw['merchant_name'] ?? 'Restoran / Toko',
            'date' => normalize_date($raw['date'] ?? null) ?? now()->toDateString(),
            'time' => normalize_time($raw['time'] ?? null) ?? now()->format('H:i'),
            'category' => 'restoran',
            'items' => $items,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate_percent' => (int) ($raw['taxRatePercent'] ?? $raw['tax_rate_percent'] ?? 10),
            'is_tax_included' => (bool) ($raw['isTaxIncluded'] ?? $raw['is_tax_included'] ?? false),
            'service_charge_amount' => $serviceAmount,
            'service_rate_percent' => (int) ($raw['serviceRatePercent'] ?? $raw['service_rate_percent'] ?? 0),
            'discount_amount' => $discountAmount,
            'other_fees_amount' => $otherFees,
            'grand_total' => (int) ($raw['grandTotal'] ?? $raw['grand_total'] ?? ($subtotal + $taxAmount + $serviceAmount - $discountAmount + $otherFees)),
            'notes' => $raw['notes'] ?? 'Hasil pemindaian otomatis oleh Gemini AI',
            'scanner_source' => 'gemini_ai',
            'confidence_score' => (int) ($raw['confidenceScore'] ?? $raw['confidence_score'] ?? 90),
        ];
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Anda adalah sistem OCR dan ekstraksi struk restoran/toko profesional untuk aplikasi split bill PatunganBeres di Indonesia.
Analisis gambar struk ini dengan cermat dan ekstrak informasinya dalam format JSON murni tanpa markdown/backticks.
Aturan:
1. Identifikasi nama merchant/restoran/toko, tanggal (format YYYY-MM-DD jika memungkinkan), dan jam.
2. Identifikasi setiap item menu: nama lengkap, kuantitas (qty), harga satuan (unitPrice), total harga item (totalPrice).
3. Jika tulisan buram atau kurang yakin, tandai confidence "needs_review", selain itu "high".
4. Identifikasi subtotal item sebelum pajak dan servis.
5. Identifikasi PPN / pajak restoran (PB1) jika terpisah dalam struk.
6. Identifikasi biaya layanan (service charge) jika ada.
7. Identifikasi diskon (per item atau keseluruhan) jika ada.
8. Identifikasi biaya lain (misal biaya kemasan, pembulatan, dll) jika ada.
9. Identifikasi total akhir (grandTotal).
10. Semua nominal dalam angka integer Rupiah (tanpa titik/koma/Rp).

Format JSON:
{
  "merchantName": "Nama Restoran",
  "date": "2026-10-28",
  "time": "13:45",
  "items": [
    {
      "name": "Nasi Goreng Spesial",
      "quantity": 2,
      "unitPrice": 30000,
      "totalPrice": 60000,
      "confidence": "high"
    }
  ],
  "subtotal": 60000,
  "taxAmount": 6000,
  "taxRatePercent": 10,
  "isTaxIncluded": false,
  "serviceChargeAmount": 3000,
  "serviceRatePercent": 5,
  "discountAmount": 0,
  "otherFeesAmount": 0,
  "grandTotal": 69000,
  "confidenceScore": 95,
  "notes": "Struk terbaca jelas"
}
PROMPT;
    }
}
