<?php

namespace App\Services\SearchSignals;

use App\Models\ExternalSearchQuerySignal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ExternalSearchSignalIngestor
{
    /**
     * @param iterable<int, array<string, mixed>> $records
     * @return array{imported: int, skipped: int}
     */
    public function persist(iterable $records, string $defaultSource): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $payload = $this->payload($record, $defaultSource);

            if ($payload === null) {
                $skipped++;
                continue;
            }

            ExternalSearchQuerySignal::updateOrCreate(
                ['signal_hash' => $payload['signal_hash']],
                $payload,
            );
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>|null
     */
    public function payload(array $record, string $defaultSource): ?array
    {
        $query = trim((string) data_get($record, 'query', data_get($record, 'search_query', '')));
        if ($query === '') {
            return null;
        }

        $normalizedQuery = $this->normalizeQuery((string) data_get($record, 'normalized_query', $query));
        $source = $this->normalizeSource((string) data_get($record, 'source', $defaultSource));
        $country = $this->nullableUpper(data_get($record, 'country'));
        $locale = $this->nullableLower(data_get($record, 'locale'));
        $observedAt = $this->observedAt(data_get($record, 'observed_at', data_get($record, 'date')));
        $landingUrl = $this->nullableString(data_get($record, 'landing_url', data_get($record, 'page')));
        $impressions = $this->integer(data_get($record, 'impressions'));
        $clicks = $this->integer(data_get($record, 'clicks'));
        $ctr = $this->decimal(data_get($record, 'ctr')) ?? ($impressions > 0 ? round($clicks / $impressions, 4) : null);
        $volume = $this->nullableInteger(data_get($record, 'volume', data_get($record, 'search_volume')));
        $metadata = $this->metadata($record);

        return [
            'signal_hash' => hash('sha256', json_encode([
                $source,
                $normalizedQuery,
                $country,
                $locale,
                $landingUrl,
                optional($observedAt)->toDateString(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'query' => $query,
            'normalized_query' => $normalizedQuery,
            'source' => $source,
            'country' => $country,
            'locale' => $locale,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $ctr,
            'volume' => $volume,
            'landing_url' => $landingUrl,
            'observed_at' => $observedAt,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function metadata(array $record): array
    {
        $known = [
            'query',
            'search_query',
            'normalized_query',
            'source',
            'country',
            'locale',
            'impressions',
            'clicks',
            'ctr',
            'volume',
            'search_volume',
            'landing_url',
            'page',
            'observed_at',
            'date',
            'metadata',
        ];

        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];

        foreach ($record as $key => $value) {
            if (! in_array($key, $known, true) && $value !== null && $value !== '') {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    private function normalizeQuery(string $query): string
    {
        $query = Str::lower(trim($query));
        $query = preg_replace('/\s+/u', ' ', $query) ?? '';

        return trim($query);
    }

    private function normalizeSource(string $source): string
    {
        return Str::snake(trim($source) !== '' ? $source : 'manual_import');
    }

    private function observedAt(mixed $value): ?CarbonInterface
    {
        if ($value === null || trim((string) $value) === '') {
            return now();
        }

        return Carbon::parse((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nullableLower(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value !== null ? Str::lower($value) : null;
    }

    private function nullableUpper(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value !== null ? Str::upper($value) : null;
    }

    private function integer(mixed $value): int
    {
        return max(0, (int) $value);
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->integer($value);
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $raw = trim((string) $value);
        $decimal = (float) str_replace('%', '', $raw);

        return round(str_contains($raw, '%') ? $decimal / 100 : $decimal, 4);
    }
}
