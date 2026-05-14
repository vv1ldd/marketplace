# OrderDeliveryDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **string** | Идентификатор доставки, присвоенный магазином.  Указывается, только если магазин передал данный идентификатор в ответе на запрос методом &#x60;POST cart&#x60;. | [optional]
**type** | [**\AppYandexSdk\Model\OrderDeliveryType**](OrderDeliveryType.md) |  |
**service_name** | **string** | Название службы доставки. |
**price** | **float** | {% note warning \&quot;Стоимость доставки смотрите в параметре &#x60;deliveryTotal&#x60;.\&quot; %}     {% endnote %}  Стоимость доставки в валюте заказа. | [optional]
**delivery_partner_type** | [**\AppYandexSdk\Model\OrderDeliveryPartnerType**](OrderDeliveryPartnerType.md) |  |
**courier** | [**\AppYandexSdk\Model\OrderCourierDTO**](OrderCourierDTO.md) |  | [optional]
**dates** | [**\AppYandexSdk\Model\OrderDeliveryDatesDTO**](OrderDeliveryDatesDTO.md) |  |
**region** | [**\AppYandexSdk\Model\RegionDTO**](RegionDTO.md) |  | [optional]
**address** | [**\AppYandexSdk\Model\OrderDeliveryAddressDTO**](OrderDeliveryAddressDTO.md) |  | [optional]
**vat** | [**\AppYandexSdk\Model\OrderVatType**](OrderVatType.md) |  | [optional]
**delivery_service_id** | **int** | Идентификатор службы доставки. |
**lift_type** | [**\AppYandexSdk\Model\OrderLiftType**](OrderLiftType.md) |  | [optional]
**lift_price** | **float** | Стоимость подъема на этаж. | [optional]
**outlet_code** | **string** | Идентификатор пункта выдачи, присвоенный магазином. | [optional]
**outlet_storage_limit_date** | **string** | Формат даты: &#x60;ДД-ММ-ГГГГ&#x60;. | [optional]
**dispatch_type** | [**\AppYandexSdk\Model\OrderDeliveryDispatchType**](OrderDeliveryDispatchType.md) |  | [optional]
**tracks** | [**\AppYandexSdk\Model\OrderTrackDTO[]**](OrderTrackDTO.md) | Информация для отслеживания посылки. | [optional]
**shipments** | [**\AppYandexSdk\Model\OrderShipmentDTO[]**](OrderShipmentDTO.md) | Информация о посылках. | [optional]
**estimated** | **bool** | Приблизительная ли дата доставки. | [optional]
**eac_type** | [**\AppYandexSdk\Model\OrderDeliveryEacType**](OrderDeliveryEacType.md) |  | [optional]
**eac_code** | **string** | Код подтверждения ЭАПП (для типа &#x60;MERCHANT_TO_COURIER&#x60;). | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
