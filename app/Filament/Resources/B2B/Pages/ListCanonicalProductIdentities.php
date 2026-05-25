<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\CanonicalProductIdentityResource;
use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentityOverride;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCanonicalProductIdentities extends ListRecords
{
    protected static string $resource = CanonicalProductIdentityResource::class;

    public function getTabs(): array
    {
        return [
            'All' => Tab::make('Все')
                ->badge(fn() => CanonicalProductIdentity::count()),

            'RequiresReview' => Tab::make('Требуется проверка')
                ->badge(fn() => CanonicalProductIdentity::where(function (Builder $query) {
                    $query->where('confidence', 'low')
                        ->orWhere('signals', 'like', '%brand_not_in_title%')
                        ->orWhere('signals', 'like', '%multiple_brand_tokens%')
                        ->orWhere('signals', 'like', '%brand_family_mismatch%');
                })
                ->whereDoesntHave('override', fn ($oq) => $oq->where('review_status', 'approved'))
                ->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where(function (Builder $q) {
                    $q->where('confidence', 'low')
                        ->orWhere('signals', 'like', '%brand_not_in_title%')
                        ->orWhere('signals', 'like', '%multiple_brand_tokens%')
                        ->orWhere('signals', 'like', '%brand_family_mismatch%');
                })
                ->whereDoesntHave('override', fn ($oq) => $oq->where('review_status', 'approved'))),

            'Pending' => Tab::make('На проверке (Ожидают)')
                ->badge(fn() => CanonicalProductIdentityOverride::where('review_status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('override', fn ($oq) => $oq->where('review_status', 'pending'))),

            'Approved' => Tab::make('Утвержденные')
                ->badge(fn() => CanonicalProductIdentityOverride::where('review_status', 'approved')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('override', fn ($oq) => $oq->where('review_status', 'approved'))),
        ];
    }
}
