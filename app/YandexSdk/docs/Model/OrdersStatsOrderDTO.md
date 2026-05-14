# OrdersStatsOrderDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор заказа. | [optional]
**creation_date** | **\DateTime** | Дата создания заказа.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. | [optional]
**status_update_date** | **\DateTime** | Дата и время, когда статус заказа был изменен в последний раз.  Формат даты и времени: ISO 8601. Например, &#x60;2017-11-21T00:00:00&#x60;. Часовой пояс — UTC+03:00 (Москва). | [optional]
**status** | [**\AppYandexSdk\Model\OrderStatsStatusType**](OrderStatsStatusType.md) |  | [optional]
**partner_order_id** | **string** | Идентификатор заказа в информационной системе магазина. | [optional]
**payment_type** | [**\AppYandexSdk\Model\OrdersStatsOrderPaymentType**](OrdersStatsOrderPaymentType.md) |  | [optional]
**fake** | **bool** | Тип заказа:  * &#x60;false&#x60; — настоящий заказ покупателя.  * &#x60;true&#x60; — [тестовый заказ](../../concepts/sandbox.md) Маркета. | [optional]
**delivery_region** | [**\AppYandexSdk\Model\OrdersStatsDeliveryRegionDTO**](OrdersStatsDeliveryRegionDTO.md) |  | [optional]
**items** | [**\AppYandexSdk\Model\OrdersStatsItemDTO[]**](OrdersStatsItemDTO.md) | Список товаров в заказе после возможных изменений.  Информация о доставке заказа добавляется отдельным элементом в массиве &#x60;items&#x60;— параметр &#x60;offerName&#x60; со значением &#x60;Доставка&#x60;. |
**initial_items** | [**\AppYandexSdk\Model\OrdersStatsItemDTO[]**](OrdersStatsItemDTO.md) | Список товаров в заказе.  Возвращается, только если было изменение количества товаров. | [optional]
**payments** | [**\AppYandexSdk\Model\OrdersStatsPaymentDTO[]**](OrdersStatsPaymentDTO.md) | Информация о расчетах по заказу.  Возвращается пустым, если заказ:   * только начали обрабатывать (даже если он оплачен);   * отменили до момента передачи в доставку.  Окончательная информация о расчетах по заказу вернется после его финальной обработки (например, после перехода в статус &#x60;DELIVERED&#x60;). |
**commissions** | [**\AppYandexSdk\Model\OrdersStatsCommissionDTO[]**](OrdersStatsCommissionDTO.md) | Информация о стоимости услуг. |
**subsidies** | [**\AppYandexSdk\Model\OrdersStatsSubsidyDTO[]**](OrdersStatsSubsidyDTO.md) | Начисление баллов, которые используются для уменьшения стоимости размещения, и их списание в случае невыкупа или возврата. | [optional]
**currency** | [**\AppYandexSdk\Model\CurrencyType**](CurrencyType.md) |  |

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
