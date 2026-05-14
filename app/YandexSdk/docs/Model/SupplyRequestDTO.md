# SupplyRequestDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | [**\AppYandexSdk\Model\SupplyRequestIdDTO**](SupplyRequestIdDTO.md) |  |
**type** | [**\AppYandexSdk\Model\SupplyRequestType**](SupplyRequestType.md) |  |
**subtype** | [**\AppYandexSdk\Model\SupplyRequestSubType**](SupplyRequestSubType.md) |  |
**status** | [**\AppYandexSdk\Model\SupplyRequestStatusType**](SupplyRequestStatusType.md) |  |
**updated_at** | **\DateTime** | Дата и время последнего обновления заявки. |
**counters** | [**\AppYandexSdk\Model\SupplyRequestCountersDTO**](SupplyRequestCountersDTO.md) |  |
**parent_link** | [**\AppYandexSdk\Model\SupplyRequestReferenceDTO**](SupplyRequestReferenceDTO.md) |  | [optional]
**children_links** | [**\AppYandexSdk\Model\SupplyRequestReferenceDTO[]**](SupplyRequestReferenceDTO.md) | Ссылки на дочерние заявки. | [optional]
**target_location** | [**\AppYandexSdk\Model\SupplyRequestLocationDTO**](SupplyRequestLocationDTO.md) |  |
**transit_location** | [**\AppYandexSdk\Model\SupplyRequestLocationDTO**](SupplyRequestLocationDTO.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
