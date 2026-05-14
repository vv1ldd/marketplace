<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Str;

class YandexGenerateEnums extends Command
{
    protected $signature = 'ym:generate-enums';
    protected $description = 'Generate Yandex Market Enums from OpenAPI specification';

    protected array $mappings = [
        'OrderStatusType.yaml' => 'YmOrderStatus',
        'OrderSubstatusType.yaml' => 'YmOrderSubstatus',
        'OrderVatType.yaml' => 'YmVat',
        'OfferCampaignStatusType.yaml' => 'YmOfferStatus',
        'OfferMappingErrorType.yaml' => 'YmMappingError',
        'WarehouseStockType.yaml' => 'YmWarehouseStockType',
        'ShipmentType.yaml' => 'YmShipmentType',
        'PromoParticipationType.yaml' => 'YmPromoParticipationType',
        'PriceCompetitivenessType.yaml' => 'YmPriceCompetitivenessType',
        'QualityRatingComponentType.yaml' => 'YmQualityRatingType',
        'ReturnInstanceStatusType.yaml' => 'YmReturnStatus',
        'RefundStatusType.yaml' => 'YmRefundStatus',
        'OrderPaymentType.yaml' => 'YmPaymentType',
        'OrderPaymentMethodType.yaml' => 'YmPaymentMethod',
        'OrderTaxSystemType.yaml' => 'YmTaxSystem',
        'OfferMappingErrorType.yaml' => 'YmMappingError',
        'OfferContentErrorType.yaml' => 'YmContentError',
    ];

    public function handle()
    {
        $specPath = base_path('vendor/yandex-market/partner-api-spec/openapi/components/schemas/');
        $targetPath = app_path('Enums/Yandex/');

        if (!file_exists(base_path('vendor/yandex-market/partner-api-spec/openapi'))) {
            $this->error('Yandex spec not found in vendor! Please run: composer require yandex-market/partner-api-spec');
            return;
        }

        foreach ($this->mappings as $yamlFile => $enumName) {
            $this->info("Processing {$yamlFile}...");
            $fullPath = $specPath . $yamlFile;

            if (!file_exists($fullPath)) {
                $this->warn("File {$yamlFile} not found, skipping.");
                continue;
            }

            $spec = Yaml::parseFile($fullPath);
            $enums = $spec['enum'] ?? [];
            $description = $spec['description'] ?? '';

            $this->generateEnum($enumName, $enums, $description, $targetPath);
        }

        $this->info('Yandex Enums synchronized successfully!');
    }

    protected function generateEnum(string $name, array $values, string $description, string $path)
    {
        $content = "<?php\n\nnamespace App\Enums\Yandex;\n\n";
        $content .= "/**\n * Generated from Yandex Market OpenAPI spec\n";
        $content .= " * " . str_replace("\n", "\n * ", trim($description)) . "\n */\n";
        $content .= "enum {$name}: string\n{\n";

        foreach ($values as $value) {
            $content .= "    case {$value} = '{$value}';\n";
        }

        $content .= "\n    public function label(): string\n    {\n        return match(\$this) {\n";
        foreach ($values as $value) {
            $content .= "            self::{$value} => '{$value}',\n";
        }
        $content .= "        };\n    }\n}\n";

        file_put_contents($path . $name . '.php', $content);
    }
}
