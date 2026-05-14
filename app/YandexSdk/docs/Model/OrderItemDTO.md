# OrderItemDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор товара в заказе.  Позволяет идентифицировать товар в рамках заказа. |
**offer_id** | **string** | Ваш SKU — идентификатор товара в вашей системе.  Правила использования SKU:  * У каждого товара SKU должен быть свой.  * Уже заданный SKU нельзя освободить и использовать заново для другого товара. Каждый товар должен получать новый идентификатор, до того никогда не использовавшийся в вашем каталоге.  SKU товара можно изменить в кабинете продавца на Маркете. О том, как это сделать, читайте [в Справке Маркета для продавцов](https://yandex.ru/support2/marketplace/ru/assortment/operations/edit-sku).  {% note warning %}  Пробельные символы в начале и конце значения автоматически удаляются. Например, &#x60;\&quot;  SKU123  \&quot;&#x60; и &#x60;\&quot;SKU123\&quot;&#x60; будут обработаны как одинаковые значения.  {% endnote %}  [Что такое SKU и как его назначать](https://yandex.ru/support/marketplace/assortment/add/index.html#fields) |
**offer_name** | **string** | Название товара. |
**price** | **float** | Цена товара в валюте заказа без учета вознаграждения продавцу за скидки по промокодам, купонам и акциям (параметр &#x60;subsidies&#x60;).  Включает НДС. |
**buyer_price** | **float** | Цена товара в валюте покупателя. В цене уже учтены скидки по:  * акциям; * купонам; * промокодам. |
**buyer_price_before_discount** | **float** | Стоимость товара в валюте покупателя до применения скидок по:  * акциям; * купонам; * промокодам.  Это зачеркнутая цена, которая отображается покупателю на карточке товара до применения скидок. |
**price_before_discount** | **float** | Стоимость товара в валюте магазина до применения скидок. | [optional]
**count** | **int** | Количество единиц товара. |
**vat** | [**\AppYandexSdk\Model\OrderVatType**](OrderVatType.md) |  | [optional]
**shop_sku** | **string** | Ваш SKU — идентификатор товара в вашей системе.  Правила использования SKU:  * У каждого товара SKU должен быть свой.  * Уже заданный SKU нельзя освободить и использовать заново для другого товара. Каждый товар должен получать новый идентификатор, до того никогда не использовавшийся в вашем каталоге.  SKU товара можно изменить в кабинете продавца на Маркете. О том, как это сделать, читайте [в Справке Маркета для продавцов](https://yandex.ru/support2/marketplace/ru/assortment/operations/edit-sku).  {% note warning %}  Пробельные символы в начале и конце значения автоматически удаляются. Например, &#x60;\&quot;  SKU123  \&quot;&#x60; и &#x60;\&quot;SKU123\&quot;&#x60; будут обработаны как одинаковые значения.  {% endnote %}  [Что такое SKU и как его назначать](https://yandex.ru/support/marketplace/assortment/add/index.html#fields) | [optional]
**subsidy** | **float** | {% note warning \&quot;Вместо него используйте &#x60;subsidies&#x60;.\&quot; %}     {% endnote %}  Общее вознаграждение продавцу за DBS-доставку и все скидки на товар:  * по промокодам; * по купонам; * по баллам Плюса; * по акциям. | [optional]
**partner_warehouse_id** | **string** | **Только для моделей FBY и LaaS**  Идентификатор склада, на который сформирован заказ. | [optional]
**promos** | [**\AppYandexSdk\Model\OrderItemPromoDTO[]**](OrderItemPromoDTO.md) | Информация о вознаграждении продавцу за скидки на товар по промокодам, купонам и акциям. | [optional]
**instances** | [**\AppYandexSdk\Model\OrderItemInstanceDTO[]**](OrderItemInstanceDTO.md) | Информация о маркировке единиц товара.  Возвращаются данные для маркировки, переданные в запросе:  * Для DBS — [PUT v2/campaigns/{campaignId}/orders/{orderId}/identifiers](../../reference/orders/provideOrderItemIdentifiers.md) или [PUT v2/campaigns/{campaignId}/orders/{orderId}/boxes](../../reference/orders/setOrderBoxLayout.md). * Для FBS и EXPRESS — [PUT v2/campaigns/{campaignId}/orders/{orderId}/boxes](../../reference/orders/setOrderBoxLayout.md).  Для FBY возвращаются коды маркировки, переданные во время поставки.  Если магазин еще не передавал коды для этого заказа, &#x60;instances&#x60; отсутствует. | [optional]
**details** | [**\AppYandexSdk\Model\OrderItemDetailDTO[]**](OrderItemDetailDTO.md) | {% note warning \&quot;Для получения информации о невыкупах и возвратах используйте [GET v2/campaigns/{campaignId}/returns](../../reference/orders/getReturns.md).\&quot; %}     {% endnote %}  Информация о невыкупленных или возвращенных товарах в заказе. | [optional]
**subsidies** | [**\AppYandexSdk\Model\OrderItemSubsidyDTO[]**](OrderItemSubsidyDTO.md) | Список субсидий по типам. | [optional]
**required_instance_types** | [**\AppYandexSdk\Model\OrderItemInstanceType[]**](OrderItemInstanceType.md) | Список необходимых маркировок товара. | [optional]
**tags** | [**\AppYandexSdk\Model\OrderItemTagType[]**](OrderItemTagType.md) | Признаки товара. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
