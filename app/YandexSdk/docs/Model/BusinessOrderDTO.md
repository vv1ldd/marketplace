# BusinessOrderDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**order_id** | **int** | Идентификатор заказа. |
**campaign_id** | **int** | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. |
**program_type** | [**\AppYandexSdk\Model\SellingProgramType**](SellingProgramType.md) |  | [optional]
**external_order_id** | **string** | Внешний идентификатор заказа, который вы передали в [POST v2/campaigns/{campaignId}/orders/{orderId}/external-id](../../reference/orders/updateExternalOrderId.md). | [optional]
**status** | [**\AppYandexSdk\Model\OrderStatusType**](OrderStatusType.md) |  |
**substatus** | [**\AppYandexSdk\Model\OrderSubstatusType**](OrderSubstatusType.md) |  |
**creation_date** | **\DateTime** | Дата и время оформления заказа.  Формат даты: ISO 8601 со смещением относительно UTC. |
**update_date** | **\DateTime** | Дата и время последнего обновления заказа.  Формат даты: ISO 8601 со смещением относительно UTC. | [optional]
**payment_type** | [**\AppYandexSdk\Model\OrderPaymentType**](OrderPaymentType.md) |  |
**payment_method** | [**\AppYandexSdk\Model\OrderPaymentMethodType**](OrderPaymentMethodType.md) |  |
**fake** | **bool** | Тип заказа:  * &#x60;false&#x60; — настоящий заказ покупателя.  * &#x60;true&#x60; — [тестовый заказ](../../concepts/sandbox.md) Маркета. |
**items** | [**\AppYandexSdk\Model\BusinessOrderItemDTO[]**](BusinessOrderItemDTO.md) | Список товаров в заказе. |
**prices** | [**\AppYandexSdk\Model\OrderPriceDTO**](OrderPriceDTO.md) |  | [optional]
**delivery** | [**\AppYandexSdk\Model\BusinessOrderDeliveryDTO**](BusinessOrderDeliveryDTO.md) |  |
**services** | [**\AppYandexSdk\Model\BusinessOrderServicesDTO**](BusinessOrderServicesDTO.md) |  | [optional]
**buyer_type** | [**\AppYandexSdk\Model\OrderBuyerType**](OrderBuyerType.md) |  | [optional]
**notes** | **string** | Комментарий к заказу. | [optional]
**cancel_requested** | **bool** | **Только для модели DBS**  Запрошена ли отмена. | [optional]
**source_platform** | [**\AppYandexSdk\Model\OrderSourcePlatformType**](OrderSourcePlatformType.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
