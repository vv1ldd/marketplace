# BusinessOrderDeliveryDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**type** | [**\AppYandexSdk\Model\OrderDeliveryType**](OrderDeliveryType.md) |  |
**service_name** | **string** | Название службы доставки. |
**delivery_service_id** | **int** | Идентификатор службы доставки. |
**warehouse_id** | **string** | Идентификатор склада в системе магазина, на который сформирован заказ. | [optional]
**delivery_partner_type** | [**\AppYandexSdk\Model\OrderDeliveryPartnerType**](OrderDeliveryPartnerType.md) |  |
**dispatch_type** | [**\AppYandexSdk\Model\OrderDeliveryDispatchType**](OrderDeliveryDispatchType.md) |  | [optional]
**dates** | [**\AppYandexSdk\Model\BusinessOrderDeliveryDatesDTO**](BusinessOrderDeliveryDatesDTO.md) |  |
**shipment** | [**\AppYandexSdk\Model\BusinessOrderShipmentDTO**](BusinessOrderShipmentDTO.md) |  | [optional]
**courier** | [**\AppYandexSdk\Model\BusinessOrderCourierDeliveryDTO**](BusinessOrderCourierDeliveryDTO.md) |  | [optional]
**pickup** | [**\AppYandexSdk\Model\BusinessOrderPickupDeliveryDTO**](BusinessOrderPickupDeliveryDTO.md) |  | [optional]
**transfer** | [**\AppYandexSdk\Model\BusinessOrderTransferDTO**](BusinessOrderTransferDTO.md) |  | [optional]
**boxes_layout** | [**\AppYandexSdk\Model\BusinessOrderBoxLayoutDTO[]**](BusinessOrderBoxLayoutDTO.md) | Раскладка товаров по коробкам. | [optional]
**tracks** | [**\AppYandexSdk\Model\OrderTrackDTO[]**](OrderTrackDTO.md) | Информация для отслеживания посылки. | [optional]
**estimated** | **bool** | Приблизительная ли дата доставки. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
