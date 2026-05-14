# LogisticPointDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**logistic_point_id** | **int** | Идентификатор пункта выдачи.  Его можно узнать с помощью метода [POST v1/businesses/{businessId}/logistics-points](../../reference/logistic-points/getLogisticPoints.md). |
**brand** | [**\AppYandexSdk\Model\LogisticPointBrandType**](LogisticPointBrandType.md) |  |
**address** | [**\AppYandexSdk\Model\LogisticPointAddressDTO**](LogisticPointAddressDTO.md) |  |
**working_schedule** | [**\AppYandexSdk\Model\LogisticPointScheduleDTO**](LogisticPointScheduleDTO.md) |  |
**delivery_restrictions** | [**\AppYandexSdk\Model\LogisticPointDeliveryRestrictionDTO**](LogisticPointDeliveryRestrictionDTO.md) |  |
**features** | [**\AppYandexSdk\Model\LogisticPointFeatureType[]**](LogisticPointFeatureType.md) | Свойства пункта выдачи. | [optional]
**storage_period** | **int** | Срок хранения заказа в пункте выдачи.  Указывается в днях. |

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
