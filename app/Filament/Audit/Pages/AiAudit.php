<?php

namespace App\Filament\Audit\Pages;

use App\Services\Ai\LedgerAnalystService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class AiAudit extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    protected string $view = 'filament.partner.pages.ai-audit';

    protected static ?string $navigationLabel = 'AI Аудит';

    protected static ?string $title = 'Интеллектуальный Аудит Ledger';

    protected static string | \UnitEnum | null $navigationGroup = 'Аудит и Безопасность';

    public ?string $auditResult = null;
    public bool $isAnalyzing = false;

    public function runAudit(LedgerAnalystService $analyst)
    {
        $this->isAnalyzing = true;
        
        $tenant = Filament::getTenant();
        $shop = $tenant instanceof \App\Models\LegalEntity 
            ? $tenant->shops()->first() 
            : ($tenant instanceof \App\Models\Shop ? $tenant : \App\Models\Shop::first());

        if (!$shop) {
            Notification::make()->title('Магазин не найден для анализа')->danger()->send();
            $this->isAnalyzing = false;
            return;
        }
        
        $this->auditResult = $analyst->analyze($shop);
        $this->isAnalyzing = false;
        
        Notification::make()
            ->title('Аудит успешно проведен')
            ->success()
            ->send();
    }

    public function saveForTraining(LedgerAnalystService $analyst)
    {
        if (Filament::getCurrentPanel()->getId() !== 'admin') {
            return;
        }

        $tenant = Filament::getTenant();
        $shop = $tenant instanceof \App\Models\LegalEntity 
            ? $tenant->shops()->first() 
            : ($tenant instanceof \App\Models\Shop ? $tenant : \App\Models\Shop::first());
        
        if (!$shop || !filled($this->auditResult)) {
            return;
        }
        
        $jsonl = $analyst->exportForTraining($shop, $this->auditResult);
        
        \Illuminate\Support\Facades\Storage::append('ai_training_data.jsonl', $jsonl);
        
        Notification::make()
            ->title('Пример сохранен для обучения')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveForTraining')
                ->label('В базу обучения')
                ->color('info')
                ->icon('heroicon-o-academic-cap')
                ->action('saveForTraining')
                ->visible(fn() => 
                    filled($this->auditResult) && 
                    Filament::getCurrentPanel()?->getId() === 'admin'
                ),
            Action::make('runAudit')
                ->label('Запустить AI Аудит')
                ->color('success')
                ->icon('heroicon-m-play')
                ->action('runAudit'),
        ];
    }
}
