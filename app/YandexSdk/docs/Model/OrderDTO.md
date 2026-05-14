# OrderDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор заказа. |
**external_order_id** | **string** | Внешний идентификатор заказа, который вы передали в [POST v2/campaigns/{campaignId}/orders/{orderId}/external-id](../../reference/orders/updateExternalOrderId.md). | [optional]
**status** | [**\AppYandexSdk\Model\OrderStatusType**](OrderStatusType.md) |  |
**substatus** | [**\AppYandexSdk\Model\OrderSubstatusType**](OrderSubstatusType.md) |  |
**creation_date** | **string** |  |
**updated_at** | **string** |  | [optional]
**currency** | [**\AppYandexSdk\Model\CurrencyType**](CurrencyType.md) |  |
**items_total** | **float** | Платеж покупателя. |
**delivery_total** | **float** | Стоимость доставки. |
**buyer_items_total** | **float** | Стоимость всех товаров в заказе в валюте покупателя после применения скидок и без учета стоимости доставки. | [optional]
**buyer_total** | **float** | Стоимость всех товаров в заказе в валюте покупателя после применения скидок и с учетом стоимости доставки. | [optional]
**buyer_items_total_before_discount** | **float** | Стоимость всех товаров в заказе в валюте покупателя без учета стоимости доставки и до применения скидок по:  * акциям; * купонам; * промокодам. |
**buyer_total_before_discount** | **float** | Стоимость всех товаров в заказе в валюте покупателя до применения скидок и с учетом стоимости доставки (&#x60;buyerItemsTotalBeforeDiscount&#x60; + стоимость доставки). | [optional]
**payment_type** | [**\AppYandexSdk\Model\OrderPaymentType**](OrderPaymentType.md) |  |
**payment_method** | [**\AppYandexSdk\Model\OrderPaymentMethodType**](OrderPaymentMethodType.md) |  |
**fake** | **bool** | Тип заказа:  * &#x60;false&#x60; — настоящий заказ покупателя.  * &#x60;true&#x60; — [тестовый заказ](../../concepts/sandbox.md) Маркета. |
**items** | [**\AppYandexSdk\Model\OrderItemDTO[]**](OrderItemDTO.md) | Список товаров в заказе. |
**subsidies** | [**\AppYandexSdk\Model\OrderSubsidyDTO[]**](OrderSubsidyDTO.md) | Список субсидий по типам. | [optional]
**delivery** | [**\AppYandexSdk\Model\OrderDeliveryDTO**](OrderDeliveryDTO.md) |  |
**buyer** | [**\AppYandexSdk\Model\OrderBuyerDTO**](OrderBuyerDTO.md) |  |
**notes** | **string** | Комментарий к заказу. | [optional]
**tax_system** | [**\AppYandexSdk\Model\OrderTaxSystemType**](OrderTaxSystemType.md) |  |
**cancel_requested** | **bool** | **Только для модели DBS**  Запрошена ли отмена. | [optional]
**expiry_date** | **string** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
