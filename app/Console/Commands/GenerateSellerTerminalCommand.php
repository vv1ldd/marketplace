<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Models\SellerTerminal;
use Illuminate\Console\Command;

class GenerateSellerTerminalCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seller:generate-terminal
                            {legal_entity_id? : ID юридического лица (если не указан — генерирует для всех активных)}
                            {--force : Пересоздать терминал даже если уже существует}
                            {--all : Генерировать для всех активных юридических лиц без терминала}';

    protected $description = 'Генерация API-терминала (terminal_id + PIN) для продавца на платформе Marketplace';

    public function handle(): int
    {
        if ($this->argument('legal_entity_id')) {
            return $this->generateForOne((int)$this->argument('legal_entity_id'));
        }

        if ($this->option('all')) {
            return $this->generateForAll();
        }

        $this->error('Укажите legal_entity_id или используйте --all для всех активных продавцов.');
        return 1;
    }

    private function generateForOne(int $id): int
    {
        $entity = LegalEntity::find($id);

        if (!$entity) {
            $this->error("LegalEntity #{$id} не найдена.");
            return 1;
        }

        $existing = $entity->activeTerminal;

        if ($existing && !$this->option('force')) {
            $this->warn("У LegalEntity #{$id} ({$entity->name}) уже есть активный терминал.");
            $this->table(
                ['Terminal ID', 'Создан', 'Последнее использование'],
                [[
                    $existing->terminal_id,
                    $existing->created_at->format('d.m.Y H:i'),
                    $existing->last_used_at?->format('d.m.Y H:i') ?? 'Никогда',
                ]]
            );
            $this->line('Используйте <comment>--force</comment> для пересоздания.');
            return 0;
        }

        // Деактивируем старые терминалы если --force
        if ($this->option('force')) {
            $entity->terminals()->update(['is_active' => false]);
            $this->warn("Старые терминалы деактивированы.");
        }

        [$terminal, $rawPin] = $this->create($entity);

        $this->displayResult($entity, $terminal, $rawPin);
        return 0;
    }

    private function generateForAll(): int
    {
        $entities = LegalEntity::where('is_active', true)
            ->whereDoesntHave('terminals', fn($q) => $q->where('is_active', true))
            ->get();

        if ($entities->isEmpty()) {
            $this->info('Все активные продавцы уже имеют терминалы.');
            return 0;
        }

        $this->info("Генерация терминалов для {$entities->count()} продавцов...");
        $this->newLine();

        $rows = [];
        foreach ($entities as $entity) {
            [$terminal, $rawPin] = $this->create($entity);
            $rows[] = [
                $entity->id,
                mb_substr($entity->name, 0, 35),
                $terminal->terminal_id,
                $rawPin,
            ];
        }

        $this->table(
            ['ID', 'Продавец', 'Terminal ID', 'PIN (показан 1 раз!)'],
            $rows
        );

        $this->newLine();
        $this->error('⚠️  Сохраните PIN-коды! После закрытия терминала восстановить невозможно.');

        return 0;
    }

    /**
     * @return array{0: SellerTerminal, 1: string}  [terminal model, raw pin string]
     */
    private function create(LegalEntity $entity): array
    {
        $rawPin    = SellerTerminal::generatePin();
        $terminalId = SellerTerminal::generateTerminalId();

        $terminal = SellerTerminal::create([
            'legal_entity_id' => $entity->id,
            'terminal_id'     => $terminalId,
            'terminal_pin'    => $rawPin,   // encrypted by model cast
            'is_active'       => true,
        ]);

        return [$terminal, $rawPin];
    }

    private function displayResult(LegalEntity $entity, SellerTerminal $terminal, string $rawPin): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║    ✅  Терминал создан                               ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->table(
            ['Параметр', 'Значение'],
            [
                ['Продавец',    $entity->name],
                ['LegalEntity ID', $entity->id],
                ['Terminal ID', '<info>' . $terminal->terminal_id . '</info>'],
                ['PIN',         '<comment>' . $rawPin . '</comment>'],
                ['Статус',      'Активен'],
                ['Создан',      $terminal->created_at->format('d.m.Y H:i:s')],
            ]
        );
        $this->newLine();
        $this->error('⚠️  Сохраните PIN! После закрытия терминала восстановить невозможно.');
        $this->newLine();
    }
}
