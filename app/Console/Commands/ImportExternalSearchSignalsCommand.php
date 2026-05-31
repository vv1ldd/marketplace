<?php

namespace App\Console\Commands;

use App\Models\ExternalSearchQuerySignal;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;

class ImportExternalSearchSignalsCommand extends Command
{
    protected $signature = 'search-signals:import
                            {path : Path to a JSON or CSV export}
                            {--source=manual_import : Default source for rows without a source field}
                            {--format=auto : auto, json, or csv}';

    protected $description = 'Import external search query demand signals without mutating SearchProfile';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $format = Str::lower((string) $this->option('format'));
        $source = $this->normalizeSource((string) $this->option('source'));

        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        if ($format === 'auto') {
            $format = Str::lower((string) pathinfo($path, PATHINFO_EXTENSION));
        }

        try {
            $records = match ($format) {
                'json' => $this->jsonRecords($path),
                'csv' => $this->csvRecords($path),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
            };
        } catch (JsonException|\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $payload = $this->payload($record, $source);

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

        $this->info("Imported {$imported} external search signal(s).");
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} row(s) without a usable query.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function jsonRecords(string $path): array
    {
        $decoded = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        if (isset($decoded['signals']) && is_array($decoded['signals'])) {
            return $decoded['signals'];
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        if (isset($decoded['query'])) {
            return [$decoded];
        }

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function csvRecords(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException("Unable to read CSV: {$path}");
        }

        $headers = null;
        $records = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn (string $header): string => Str::snake(trim($header)), $row);
                continue;
            }

            $records[] = array_combine($headers, array_pad($row, count($headers), null)) ?: [];
        }

        fclose($handle);

        return $records;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>|null
     */
    private function payload(array $record, string $defaultSource): ?array
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
