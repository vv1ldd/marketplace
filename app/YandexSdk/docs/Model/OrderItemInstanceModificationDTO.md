# OrderItemInstanceModificationDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор товара в заказе.  Он приходит в ответе метода [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md) — параметр &#x60;id&#x60; в &#x60;items&#x60;. |
**instances** | [**\AppYandexSdk\Model\BriefOrderItemInstanceDTO[]**](BriefOrderItemInstanceDTO.md) | Список кодов маркировки единиц товара. |

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
