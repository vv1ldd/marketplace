# OrderBoxLayoutItemDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор товара в заказе.  Он приходит в ответе метода [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md) — параметр &#x60;id&#x60; в &#x60;items&#x60;. |
**full_count** | **int** | Количество единиц товара в коробке.  Используйте это поле, если в коробке поедут целые товары, не разделенные на части. Не используйте это поле одновременно с &#x60;partialCount&#x60;. | [optional]
**partial_count** | [**\AppYandexSdk\Model\OrderBoxLayoutPartialCountDTO**](OrderBoxLayoutPartialCountDTO.md) |  | [optional]
**instances** | [**\AppYandexSdk\Model\BriefOrderItemInstanceDTO[]**](BriefOrderItemInstanceDTO.md) | Переданные коды маркировки. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
