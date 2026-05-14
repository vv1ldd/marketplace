<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return __('admin.shops.tabs.settings');
    }

    public function getRelationManagersContentComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getRelationManagersContentComponent()
            ->contained(true)
            ->extraAttributes([
                'class' => 'w-full',
                'style' => 'width: 100%',
            ]);
    }

    public function mount(int|string $record): void
    {
        ini_set('memory_limit', '512M');
        parent::mount($record);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $allowed = $data['allowed_categories'] ?? [];
        if (! is_array($allowed)) {
            $allowed = [];
        }

        $region = $data['shop_region'] ?? 'RU';
        $groupsMap = \App\Filament\Resources\ShopResource\Schemas\ShopForm::getGroupsMap($region);

        // Populate categories
        if ($groupsMap) {
            foreach ($groupsMap as $groupName => $brandOptions) {
                $groupKey = 'cat_'.Str::slug($groupName, '_');
                $brandIds = array_keys($brandOptions);
                $data[$groupKey] = array_values(array_intersect($allowed, $brandIds));
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 🌟 If we are NOT allowing all brands, we must update the allowed_categories list
        if (! ($data['allow_all_brands'] ?? false)) {
            $allSelected = [];

            // Merge all cat_* fields into allowed_categories
            foreach ($data as $key => $value) {
                if (str_starts_with($key, 'cat_') && is_array($value)) {
                    $allSelected = array_merge($allSelected, $value);
                }
            }

            $data['allowed_categories'] = array_values(array_unique($allSelected));
        } else {
            // If allow_all_brands is TRUE, we keep existing allowed_categories
            // OR we can just ignore them as the scope will ignore them anyway.
            // But we MUST NOT set it to [] here based on empty form fields.
            unset($data['allowed_categories']); // Don't overwrite if it's not in the form
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncLegalEntityManager();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
