# BusinessOrderPickupDeliveryDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**address** | [**\AppYandexSdk\Model\BusinessOrderDeliveryAddressDTO**](BusinessOrderDeliveryAddressDTO.md) |  | [optional]
**region** | [**\AppYandexSdk\Model\RegionDTO**](RegionDTO.md) |  | [optional]
**logistic_point_id** | **int** | Идентификатор пункта выдачи.  Его можно узнать с помощью метода [POST v1/businesses/{businessId}/logistics-points](../../reference/logistic-points/getLogisticPoints.md). | [optional]
**outlet_code** | **string** | Идентификатор пункта самовывоза, присвоенный магазином. | [optional]
**outlet_storage_limit_date** | **\DateTime** | Дата, до которой заказ будет храниться в пункте выдачи. Возвращается, когда заказ переходит в статус &#x60;PICKUP&#x60;.  Один раз дату можно поменять с помощью метода [PUT v2/campaigns/{campaignId}/orders/{orderId}/delivery/storage-limit](../../reference/orders/updateOrderStorageLimit.md).  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
