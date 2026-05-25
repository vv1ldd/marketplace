<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\CanonicalProductIdentityResource;
use App\Services\CanonicalProductIdentityCurationService;
use App\Services\CanonicalProductIdentityIndexService;
use Filament\Resources\Pages\EditRecord;

class EditCanonicalProductIdentity extends EditRecord
{
    protected static string $resource = CanonicalProductIdentityResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $override = $this->record->override;
        if ($override) {
            $data['override_brand'] = $override->brand;
            $data['override_product_family'] = $override->product_family;
            $data['override_canonical_category'] = $override->canonical_category;
            $data['override_face_value'] = $override->face_value;
            $data['override_face_value_currency'] = $override->face_value_currency;
            $data['override_region'] = $override->region;
            $data['override_platform'] = $override->platform;
            $data['override_confidence'] = $override->confidence;
            $data['override_review_status'] = $override->review_status;
            $data['override_review_notes'] = $override->review_notes;
        } else {
            $data['override_review_status'] = 'pending';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();

        $attributes = [
            'brand' => $data['override_brand'] ?? null,
            'product_family' => $data['override_product_family'] ?? null,
            'canonical_category' => $data['override_canonical_category'] ?? null,
            'face_value' => $data['override_face_value'] ?? null,
            'face_value_currency' => $data['override_face_value_currency'] ?? null,
            'region' => $data['override_region'] ?? null,
            'platform' => $data['override_platform'] ?? null,
            'confidence' => $data['override_confidence'] ?? null,
            'review_status' => $data['override_review_status'] ?? 'pending',
            'review_notes' => $data['override_review_notes'] ?? null,
        ];

        app(CanonicalProductIdentityCurationService::class)->saveOverride(
            fingerprint: $this->record->fingerprint,
            attributes: $attributes,
            actorId: auth()->id()
        );

        // Rebuild catalog index synchronously so that the seller storefront updates instantly!
        app(CanonicalProductIdentityIndexService::class)->rebuild();
    }
}
