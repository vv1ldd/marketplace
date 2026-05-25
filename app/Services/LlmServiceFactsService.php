<?php

namespace App\Services;

use Illuminate\Support\Collection;

class LlmServiceFactsService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function services(): Collection
    {
        return collect((array) config('meanly_services.services', []))
            ->map(fn (array $service, string $slug) => $this->serviceFacts($slug, $service))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        $service = config("meanly_services.services.{$slug}");
        if (! is_array($service)) {
            return null;
        }

        return $this->serviceFacts($slug, $service);
    }

    /**
     * @param  array<string, mixed>  $service
     * @return array<string, mixed>
     */
    public function serviceFacts(string $slug, array $service): array
    {
        $url = url((string) ($service['url'] ?? '/business'));

        return [
            'type' => 'MeanlyService',
            'slug' => $slug,
            'url' => $url,
            'machine_readable_at' => route('llms.services.show', $slug),
            'name' => $service['name'] ?? $slug,
            'service_type' => $service['service_type'] ?? 'platform_service',
            'audience' => array_values((array) ($service['audience'] ?? [])),
            'description' => $service['description'] ?? null,
            'deliverable' => $service['deliverable'] ?? null,
            'outcome' => $service['outcome'] ?? null,
            'sla' => $service['sla'] ?? null,
            'delivery_time' => $service['delivery_time'] ?? null,
            'execution_mode' => $service['execution_mode'] ?? null,
            'pricing' => $service['pricing'] ?? [
                'model' => 'custom',
                'currency' => 'RUB',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    public function serviceJsonLd(array $facts): array
    {
        $pricing = (array) ($facts['pricing'] ?? []);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            '@id' => $facts['url'].'#service-'.$facts['slug'],
            'name' => $facts['name'],
            'description' => $facts['description'],
            'serviceType' => $facts['service_type'],
            'audience' => collect($facts['audience'] ?? [])
                ->map(fn (string $audience) => [
                    '@type' => 'Audience',
                    'audienceType' => $audience,
                ])
                ->values()
                ->all(),
            'provider' => [
                '@type' => 'Organization',
                'name' => 'Meanly',
                'url' => url('/'),
            ],
            'areaServed' => 'Global',
            'termsOfService' => route('llms.services.show', $facts['slug']),
            'additionalProperty' => $this->serviceProperties($facts),
            'offers' => [
                '@type' => 'Offer',
                'url' => $facts['url'],
                'priceSpecification' => [
                    '@type' => 'PriceSpecification',
                    'priceCurrency' => $pricing['currency'] ?? 'RUB',
                    'description' => $pricing['summary'] ?? 'Custom commercial offer',
                ],
                'availability' => 'https://schema.org/InStock',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceListJsonLd(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Meanly business services',
            'itemListElement' => $this->services()
                ->values()
                ->map(fn (array $service, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $service['url'],
                    'name' => $service['name'],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<int, array<string, mixed>>
     */
    private function serviceProperties(array $facts): array
    {
        return collect([
            'deliverable' => $facts['deliverable'] ?? null,
            'outcome' => $facts['outcome'] ?? null,
            'sla' => $facts['sla'] ?? null,
            'delivery_time' => $facts['delivery_time'] ?? null,
            'execution_mode' => $facts['execution_mode'] ?? null,
        ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, string $name) => [
                '@type' => 'PropertyValue',
                'name' => $name,
                'value' => $value,
            ])
            ->values()
            ->all();
    }
}
