# CreateOrderDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**external_order_id** | **string** | Внешний идентификатор заказа в системе магазина. |
**items_delivery** | [**\AppYandexSdk\Model\CreateOrderWarehouseItemsDTO[]**](CreateOrderWarehouseItemsDTO.md) | Список товаров в заказе. |
**destination** | [**\AppYandexSdk\Model\CreateOrderDeliveryOptionDTO**](CreateOrderDeliveryOptionDTO.md) |  |
**customer** | [**\AppYandexSdk\Model\CustomerDTO**](CustomerDTO.md) |  |
**packaging** | [**\AppYandexSdk\Model\CreateOrderPackagingDTO**](CreateOrderPackagingDTO.md) |  |
**payment_type** | [**\AppYandexSdk\Model\DeliveryPaymentType**](DeliveryPaymentType.md) |  |
**draft** | **bool** | Признак создания черновика заказа.  * &#x60;true&#x60; — Маркет создаст заказ в статусе &#x60;RESERVED&#x60; и будет ждать подтверждения от магазина. * &#x60;false&#x60; — Маркет создаст заказ в статусе &#x60;PROCESSING&#x60; с подстатусом &#x60;STARTED&#x60; и начнёт его обработку, дополнительных подтверждений не требуется. | [optional] [default to false]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
