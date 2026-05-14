# OrderItemModificationDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор товара в рамках заказа.  Получить идентификатор можно с помощью метода:  * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md).  Обязательный параметр. |
**count** | **int** | Новое количество товара. |
**instances** | [**\AppYandexSdk\Model\BriefOrderItemInstanceDTO[]**](BriefOrderItemInstanceDTO.md) | Информация о маркировке единиц товара.  Передавайте в запросе все единицы товара, который подлежит маркировке.  Обязательный параметр, если в заказе от бизнеса есть товары, подлежащие маркировке в системе [«Честный ЗНАК»](https://честныйзнак.рф/) или [«ASL BELGISI»](https://aslbelgisi.uz) (для продавцов :no-translate[Market Yandex Go]). | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
