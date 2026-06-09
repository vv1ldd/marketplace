<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcquiringCompliancePagesTest extends TestCase
{
    #[DataProvider('compliancePages')]
    public function test_public_acquiring_compliance_pages_are_available(string $path, string $expectedText): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee($expectedText)
            ->assertSee('Реквизиты и оплата')
            ->assertSee('Оплата банковской картой')
            ->assertSee('Публичная оферта')
            ->assertSee('Политика конфиденциальности');
    }

    public static function compliancePages(): array
    {
        return [
            ['/company', 'Информация о компании'],
            ['/payment', 'Банк-эквайер применяет 3-D Secure'],
            ['/delivery', 'Физическая доставка для цифровых товаров не применяется'],
            ['/refund', 'Возврат по банковской карте выполняется на ту же карту'],
            ['/offer', 'Оформляя заказ, покупатель подтверждает согласие с офертой'],
            ['/privacy', 'Полные данные банковской карты вводятся на стороне банка-эквайера'],
            ['/terms', 'Сайт не размещает материалы и ссылки на ресурсы с запрещённым содержимым'],
        ];
    }
}
