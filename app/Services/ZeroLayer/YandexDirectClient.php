<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class YandexDirectClient
{
    private const DEFAULT_BASE_URL = 'https://api.direct.yandex.com/json/v5';

    public function call(array $credentials, array $settings, string $service, string $method, array $params = []): array
    {
        return $this->request($credentials, $settings)
            ->post('/'.trim($service, '/'), [
                'method' => $method,
                'params' => $params,
            ])
            ->throw()
            ->json();
    }

    public function campaigns(array $credentials, array $settings, array $params): array
    {
        return $this->call($credentials, $settings, 'campaigns', 'get', $params);
    }

    public function keywords(array $credentials, array $settings, array $params): array
    {
        return $this->call($credentials, $settings, 'keywords', 'get', $params);
    }

    public function report(array $credentials, array $settings, array $params): array
    {
        $response = $this->request($credentials, $settings, report: true)
            ->post('/reports', $params)
            ->throw(fn ($response): bool => ! in_array($response->status(), [200, 201, 202], true));

        return [
            'status' => $response->status(),
            'ready' => $response->status() === 200,
            'body' => $response->body(),
            'request_id' => $response->header('RequestId') ?? $response->header('requestid'),
        ];
    }

    public function paidSearchSignals(array $credentials, array $settings, Carbon|string $from, Carbon|string $to): array
    {
        $report = $this->report($credentials, $settings, $settings['report'] ?? $this->keywordReportParams($from, $to));

        if (! $report['ready']) {
            return [];
        }

        return collect($this->parseTsv((string) $report['body']))
            ->map(fn (array $row): array => $this->paidSearchSignal($row))
            ->values()
            ->all();
    }

    private function request(array $credentials, array $settings, bool $report = false): PendingRequest
    {
        $token = $credentials['oauth_token']
            ?? $credentials['access_token']
            ?? config('services.yandex_direct.oauth_token');

        if (! $token) {
            throw new InvalidArgumentException('Yandex Direct requires oauth_token.');
        }

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept-Language' => $settings['accept_language'] ?? config('services.yandex_direct.accept_language', 'en'),
        ];

        $clientLogin = $settings['client_login']
            ?? $credentials['client_login']
            ?? config('services.yandex_direct.client_login');

        if ($clientLogin) {
            $headers['Client-Login'] = $clientLogin;
        }

        if ($report) {
            $headers += [
                'processingMode' => $settings['processing_mode'] ?? 'auto',
                'returnMoneyInMicros' => $settings['return_money_in_micros'] ?? 'false',
                'skipReportHeader' => $settings['skip_report_header'] ?? 'true',
                'skipReportSummary' => $settings['skip_report_summary'] ?? 'true',
            ];
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.yandex_direct.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders($headers);
    }

    private function keywordReportParams(Carbon|string $from, Carbon|string $to): array
    {
        return [
            'params' => [
                'SelectionCriteria' => [
                    'DateFrom' => Carbon::parse($from)->toDateString(),
                    'DateTo' => Carbon::parse($to)->toDateString(),
                ],
                'FieldNames' => [
                    'Date',
                    'CampaignName',
                    'AdGroupName',
                    'Criteria',
                    'Device',
                    'Impressions',
                    'Clicks',
                    'Cost',
                    'Conversions',
                    'Revenue',
                ],
                'ReportName' => 'zero-layer-paid-search-'.now()->format('YmdHis'),
                'ReportType' => 'CUSTOM_REPORT',
                'DateRangeType' => 'CUSTOM_DATE',
                'Format' => 'TSV',
                'IncludeVAT' => 'NO',
                'IncludeDiscount' => 'NO',
            ],
        ];
    }

    private function parseTsv(string $body): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($body)) ?: []));

        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines), "\t");

        return collect($lines)
            ->map(function (string $line) use ($headers): array {
                $values = str_getcsv($line, "\t");

                return array_combine($headers, array_pad($values, count($headers), null)) ?: [];
            })
            ->all();
    }

    private function paidSearchSignal(array $row): array
    {
        $cost = (float) ($row['Cost'] ?? 0);
        $revenue = (float) ($row['Revenue'] ?? 0);

        return [
            'source' => 'yandex_direct',
            'signal_type' => 'paid_search_keyword',
            'signal_date' => $row['Date'] ?? now()->toDateString(),
            'campaign' => $row['CampaignName'] ?? null,
            'ad_group' => $row['AdGroupName'] ?? null,
            'query_text' => $row['Criteria'] ?? null,
            'device' => $row['Device'] ?? null,
            'impressions' => (float) ($row['Impressions'] ?? 0),
            'clicks' => (float) ($row['Clicks'] ?? 0),
            'cost' => $cost,
            'conversions' => (float) ($row['Conversions'] ?? 0),
            'revenue' => $revenue,
            'roas' => $cost > 0 ? round($revenue / $cost, 4) : null,
            'payload' => $row,
        ];
    }
}
