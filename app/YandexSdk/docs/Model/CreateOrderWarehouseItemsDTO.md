# CreateOrderWarehouseItemsDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**warehouse_id** | **int** | Идентификатор фулфилмент-склада Маркета.  Получите его с помощью метода [POST v2/campaigns/{campaignId}/delivery-options](../../reference/delivery-options/getDeliveryOptions.md). |
**items** | [**\AppYandexSdk\Model\CreateOrderItemDTO[]**](CreateOrderItemDTO.md) | Список товаров в заказе.  В рамках одного запроса все значения &#x60;offerId&#x60; должны быть уникальными. Не допускается передача двух объектов с одинаковым &#x60;offerId&#x60;. |
**delivery_date_interval** | [**\AppYandexSdk\Model\DeliveryDateIntervalDTO**](DeliveryDateIntervalDTO.md) |  |
**delivery_time_interval** | [**\AppYandexSdk\Model\TimeIntervalDTO**](TimeIntervalDTO.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
