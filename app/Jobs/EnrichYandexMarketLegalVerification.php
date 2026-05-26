<?php

namespace App\Jobs;

use App\Actions\Yandex\RecordYandexLegalTrustSignalAction;
use App\Http\Services\YmService;
use App\Models\ProductSalesChannel;
use App\Models\Shop;
use App\Models\Warehouse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichYandexMarketLegalVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public readonly int $shopId,
        public readonly ?string $reportId = null,
        public readonly int $attempt = 0,
    ) {}

    public function handle(): void
    {
        $shop = Shop::with('legalEntity')->find($this->shopId);

        if (! $shop || ! $shop->legalEntity) {
            return;
        }

        if (blank($shop->business_id) || blank($shop->campaign_id) || blank($shop->api_key)) {
            return;
        }

        $service = new YmService($shop);

        if ($this->reportId === null) {
            $this->requestReport($shop, $service);
            return;
        }

        $this->inspectReport($shop, $service);
    }

    private function requestReport(Shop $shop, YmService $service): void
    {
        $periodTo = now()->subDay()->endOfDay();
        $periodFrom = $periodTo->copy()->subMonthsNoOverflow(2)->startOfMonth();

        try {
            $generated = $service->generateMarketplaceServicesReport(
                (int) $shop->business_id,
                (int) $shop->campaign_id,
                $periodFrom,
                $periodTo
            );
            $reportId = (string) data_get($generated, 'reportId', data_get($generated, 'report_id', ''));
        } catch (\Throwable $exception) {
            $this->storeBackgroundResult($shop, [
                'status' => 'failed',
                'checked_at' => now()->toIso8601String(),
                'error' => $exception->getMessage(),
            ]);
            $this->markVerificationRejected($shop, 'rejected', 'Не удалось получить отчет Yandex Market по услугам.', [
                'stage' => 'report_generation',
                'error' => $exception->getMessage(),
            ]);
            report($exception);

            return;
        }

        if ($reportId === '') {
            $this->storeBackgroundResult($shop, [
                'status' => 'failed',
                'checked_at' => now()->toIso8601String(),
                'error' => 'Yandex Market did not return reportId for services report.',
            ]);
            $this->markVerificationRejected($shop, 'rejected', 'Yandex Market не вернул идентификатор отчета по услугам.', [
                'stage' => 'report_generation',
            ]);

            return;
        }

        $this->storeBackgroundResult($shop, [
            'status' => 'processing',
            'report_id' => $reportId,
            'period' => [
                'from' => $periodFrom->toDateString(),
                'to' => $periodTo->toDateString(),
            ],
            'started_at' => now()->toIso8601String(),
            'estimated_generation_time' => data_get($generated, 'estimatedGenerationTime', data_get($generated, 'estimated_generation_time')),
        ]);

        self::dispatch($shop->id, $reportId, 1)
            ->delay(now()->addSeconds($this->nextDelaySeconds($generated)));
    }

    private function inspectReport(Shop $shop, YmService $service): void
    {
        try {
            $info = $service->getReportInfo($this->reportId);
        } catch (\Throwable $exception) {
            $this->storeBackgroundResult($shop, [
                'status' => 'failed',
                'report_id' => $this->reportId,
                'checked_at' => now()->toIso8601String(),
                'error' => $exception->getMessage(),
            ]);
            $this->markVerificationRejected($shop, 'rejected', 'Не удалось получить статус отчета Yandex Market.', [
                'stage' => 'report_status',
                'error' => $exception->getMessage(),
                'report_id' => $this->reportId,
            ]);
            report($exception);

            return;
        }

        $status = (string) data_get($info, 'status', '');
        $fileUrl = (string) data_get($info, 'file', '');

        if ($status !== 'DONE' || $fileUrl === '') {
            if ($this->attempt < 12) {
                self::dispatch($shop->id, $this->reportId, $this->attempt + 1)
                    ->delay(now()->addSeconds(30));
                return;
            }

            $this->storeBackgroundResult($shop, [
                'status' => 'timeout',
                'report_id' => $this->reportId,
                'checked_at' => now()->toIso8601String(),
                'report_status' => $status,
            ]);
            $this->markVerificationRejected($shop, 'attention', 'Отчет Yandex Market формируется слишком долго. Нужна ручная проверка.', [
                'stage' => 'report_timeout',
                'report_id' => $this->reportId,
                'report_status' => $status,
            ]);

            return;
        }

        try {
            $signals = $this->extractLegalSignals(
                $service->downloadReportFile($fileUrl),
                $shop
            );
        } catch (\Throwable $exception) {
            $this->storeBackgroundResult($shop, [
                'status' => 'failed',
                'report_id' => $this->reportId,
                'checked_at' => now()->toIso8601String(),
                'error' => $exception->getMessage(),
            ]);
            $this->markVerificationRejected($shop, 'rejected', 'Не удалось разобрать отчет Yandex Market.', [
                'stage' => 'report_parse',
                'error' => $exception->getMessage(),
                'report_id' => $this->reportId,
            ]);
            report($exception);

            return;
        }

        $signals['status'] = $signals['found_expected_inn'] ? 'approved' : 'review_required';
        $signals['report_id'] = $this->reportId;
        $signals['checked_at'] = now()->toIso8601String();

        $this->storeBackgroundResult($shop, $signals);

        if ($signals['found_expected_inn']) {
            $this->approveShopFromBackgroundSignal($shop, $signals);

            return;
        }

        $expectedInn = $this->normalizeLegalDigits($shop->legalEntity->inn);
        $detectedInns = array_values(array_unique($signals['detected_inn'] ?? []));
        $tier = 'attention';
        $resultStatus = 'review_required';
        $moderationReason = 'Не удалось автоматически подтвердить реквизиты по отчету Yandex.';

        if ($expectedInn !== '' && $detectedInns !== [] && ! in_array($expectedInn, $detectedInns, true)) {
            $tier = 'rejected';
            $resultStatus = 'rejected';
            $moderationReason = 'ИНН в кабинете Yandex Market не совпадает с юрлицом Meanly.';
        }

        $verification = $shop->fresh()->ym_legal_verification ?? [];
        $verification['verified'] = false;
        $verification['status'] = $resultStatus;
        $verification['verification_tier'] = $tier;
        $verification['checked_at'] = now()->toIso8601String();
        $verification['moderation_reason'] = $moderationReason;
        $verification['matches'] = [
            ...($verification['matches'] ?? []),
            'inn' => false,
            'services_report_inn' => false,
        ];
        $verification['background_services_report'] = [
            ...($verification['background_services_report'] ?? []),
            'status' => $resultStatus,
        ];

        $shop->ym_legal_verification = $verification;
        $shop->save();

        app(RecordYandexLegalTrustSignalAction::class)->execute($shop, $tier, [
            'expected_inn' => $expectedInn,
            'detected_inn' => $detectedInns,
            'report_id' => $this->reportId,
            'reason' => $moderationReason,
        ]);
    }

    private function extractLegalSignals(string $binary, Shop $shop): array
    {
        $expectedInn = $this->normalizeLegalDigits($shop->legalEntity->inn);
        $expectedKpp = $this->normalizeLegalDigits($shop->legalEntity->kpp);
        $expectedName = (string) ($shop->legalEntity->short_name ?: $shop->legalEntity->name);
        $texts = [];
        $files = [];

        $tmp = tempnam(sys_get_temp_dir(), 'ym-services-report-');
        file_put_contents($tmp, $binary);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    $content = $zip->getFromIndex($i);
                    if ($content === false) {
                        continue;
                    }

                    $files[] = $this->sanitizeUtf8Text($name);
                    $texts[] = $this->extractReportText($content);
                }
                $zip->close();
            } else {
                $texts[] = $this->extractReportText($binary);
            }
        } finally {
            @unlink($tmp);
        }

        $text = $this->sanitizeUtf8Text(implode("\n", $texts));
        $numberText = preg_replace('/\D+/', ' ', $text) ?? '';
        $normalizedText = $this->normalizeLegalName($text);
        $normalizedExpectedName = $this->normalizeLegalName($expectedName);

        preg_match_all('/(?<!\d)(\d{10}|\d{12})(?!\d)/', $numberText, $innMatches);
        preg_match_all('/(?<!\d)(\d{9})(?!\d)/', $numberText, $kppMatches);

        return [
            'files' => $files,
            'found_expected_inn' => $expectedInn !== '' && preg_match('/(?<!\d)'.preg_quote($expectedInn, '/').'(?!\d)/', $numberText) === 1,
            'found_expected_kpp' => $expectedKpp === '' ? null : preg_match('/(?<!\d)'.preg_quote($expectedKpp, '/').'(?!\d)/', $numberText) === 1,
            'found_expected_name' => $normalizedExpectedName !== '' && str_contains($normalizedText, $normalizedExpectedName),
            'detected_inn' => array_values(array_unique($innMatches[1] ?? [])),
            'detected_kpp' => array_values(array_unique($kppMatches[1] ?? [])),
            'name_candidates' => $this->extractNameCandidates($text),
            'text_excerpt' => mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 1200),
        ];
    }

    private function extractReportText(string $content): string
    {
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return implode("\n", $this->flattenScalarValues($decoded));
        }

        return $content;
    }

    private function flattenScalarValues(mixed $value, array $path = []): array
    {
        if (is_scalar($value)) {
            return [implode('.', $path).': '.(string) $value];
        }

        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $nested) {
            $result = [
                ...$result,
                ...$this->flattenScalarValues($nested, [...$path, (string) $key]),
            ];
        }

        return $result;
    }

    private function extractNameCandidates(string $text): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $candidates = [];

        foreach ($lines as $line) {
            if (preg_match('/(name|shop|business|campaign|магазин|кабинет|продав|организа|юр)/iu', $line)) {
                $candidates[] = mb_substr(trim($line), 0, 240);
            }
        }

        return array_values(array_unique(array_slice(array_filter($candidates), 0, 20)));
    }

    private function storeBackgroundResult(Shop $shop, array $result): void
    {
        $verification = $shop->ym_legal_verification ?? [];
        $verification['background_services_report'] = [
            ...($verification['background_services_report'] ?? []),
            ...$result,
        ];

        if (($result['status'] ?? null) === 'review_required' && empty($verification['verified'])) {
            $verification['verified'] = false;
            $verification['status'] = $verification['status'] ?? 'review_required';
            $verification['moderation_reason'] = $verification['moderation_reason']
                ?? 'Фоновый отчет по услугам не дал автоматического совпадения ИНН.';
        }

        $shop->ym_legal_verification = $verification;
        $shop->save();
    }

    private function approveShopFromBackgroundSignal(Shop $shop, array $signals): void
    {
        $verification = $shop->ym_legal_verification ?? [];
        $verification['verified'] = true;
        $verification['status'] = 'approved';
        $verification['verification_tier'] = 'approved';
        $verification['moderation_reason'] = 'Проверка прошла.';
        $verification['checked_at'] = now()->toIso8601String();
        $verification['matches'] = [
            ...($verification['matches'] ?? []),
            'inn' => true,
            'services_report_inn' => true,
            'services_report_kpp' => $signals['found_expected_kpp'],
            'services_report_name' => $signals['found_expected_name'],
        ];

        if ($signals['found_expected_kpp'] === true) {
            $verification['matches']['kpp'] = true;
        }

        $shop->ym_legal_verification = $verification;
        $shop->ym_legal_verified_at = now();
        $shop->save();

        Warehouse::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'channel' => 'yandex_market',
            ],
            [
                'ym_id' => (int) $shop->ym_warehouse_id,
                'name' => 'Yandex Market',
                'type' => 'channel',
                'is_active' => true,
                'is_main' => false,
                'channel_quota' => 100,
            ]
        );

        ProductSalesChannel::query()
            ->where('shop_id', $shop->id)
            ->where('channel', 'yandex_market')
            ->where('is_enabled', true)
            ->pluck('product_id')
            ->each(fn (int $productId) => PushProductToYandex::dispatch($productId, $shop->id));

        DistributeStockToChannels::dispatch($shop);

        Log::info('Yandex Market legal verification approved by services report', [
            'shop_id' => $shop->id,
            'report_id' => $this->reportId,
        ]);
    }

    private function markVerificationRejected(Shop $shop, string $tier, string $reason, array $context = []): void
    {
        $verification = $shop->fresh()->ym_legal_verification ?? [];
        $verification['verified'] = false;
        $verification['status'] = $tier === 'attention' ? 'review_required' : 'rejected';
        $verification['verification_tier'] = $tier;
        $verification['moderation_reason'] = $reason;
        $verification['checked_at'] = now()->toIso8601String();

        $shop->ym_legal_verification = $verification;
        $shop->save();

        app(RecordYandexLegalTrustSignalAction::class)->execute($shop, $tier, [
            'reason' => $reason,
            ...$context,
        ]);
    }

    private function nextDelaySeconds(array $generated): int
    {
        $milliseconds = (int) data_get($generated, 'estimatedGenerationTime', data_get($generated, 'estimated_generation_time', 30000));

        return max(15, min(120, (int) ceil($milliseconds / 1000)));
    }

    private function normalizeLegalDigits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function normalizeLegalName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/["«»]/u', '', $value) ?? $value;
        $value = preg_replace('/\b(ооо|оао|зао|ао|ип|общество с ограниченной ответственностью)\b/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function sanitizeUtf8Text(string $value): string
    {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($converted === false || $converted === '') {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        return preg_replace('/[^\P{C}\t\n\r]+/u', ' ', (string) $converted) ?? '';
    }
}
