# LogisticPointAddressDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**full_address** | **string** | Полный адрес. |
**gps** | [**\AppYandexSdk\Model\GpsDTO**](GpsDTO.md) |  |
**region_id** | **int** | Идентификатор региона.  Информацию о регионе можно получить c помощью метода [GET v2/regions](../../reference/regions/searchRegionsById.md). |
**city** | **string** | Город. | [optional]
**street** | **string** | Улица. | [optional]
**house** | **string** | Номер дома. | [optional]
**building** | **string** | Номер строения. | [optional]
**block** | **string** | Номер корпуса. | [optional]
**km** | **int** | Порядковый номер километра, на котором располагается пункт выдачи.  Указывается, если в адресе нет улицы. | [optional]
**additional** | **string** | Дополнительная информация. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
