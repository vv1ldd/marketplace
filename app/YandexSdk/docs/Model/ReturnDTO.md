# ReturnDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор невыкупа или возврата. |
**order_id** | **int** | Номер заказа. |
**creation_date** | **\DateTime** | Дата создания невыкупа или возврата.  Формат даты: ISO 8601 со смещением относительно UTC. | [optional]
**update_date** | **\DateTime** | Дата обновления невыкупа или возврата.  Формат даты: ISO 8601 со смещением относительно UTC. | [optional]
**refund_status** | [**\AppYandexSdk\Model\RefundStatusType**](RefundStatusType.md) |  | [optional]
**logistic_pickup_point** | [**\AppYandexSdk\Model\LogisticPickupPointDTO**](LogisticPickupPointDTO.md) |  | [optional]
**pickup_till_date** | **\DateTime** | Дата, до которой можно забрать товар.  Только для невыкупов и возвратов в логистическом статусе &#x60;READY_FOR_PICKUP&#x60;.  Формат даты: ISO 8601 со смещением относительно UTC. | [optional]
**shipment_recipient_type** | [**\AppYandexSdk\Model\RecipientType**](RecipientType.md) |  | [optional]
**shipment_status** | [**\AppYandexSdk\Model\ReturnShipmentStatusType**](ReturnShipmentStatusType.md) |  | [optional]
**refund_amount** | **int** | {% note warning \&quot;Вместо него используйте &#x60;amount&#x60;.\&quot; %}     {% endnote %}  Сумма возврата в копейках. | [optional]
**amount** | [**\AppYandexSdk\Model\CurrencyValueDTO**](CurrencyValueDTO.md) |  | [optional]
**items** | [**\AppYandexSdk\Model\ReturnItemDTO[]**](ReturnItemDTO.md) | Список товаров в невыкупе или возврате. |
**return_type** | [**\AppYandexSdk\Model\ReturnType**](ReturnType.md) |  |
**fast_return** | **bool** | Используется ли опция **Быстрый возврат денег за дешевый брак**.  Актуально только для &#x60;returnType&#x3D;RETURN&#x60;. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
