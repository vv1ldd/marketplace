# SupplyRequestIdDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор заявки.  {% note warning \&quot;Используется только в API\&quot; %}  По нему не получится найти заявки в кабинете продавца на Маркете. Для этого используйте &#x60;marketplaceRequestId&#x60; или &#x60;warehouseRequestId&#x60;.  {% endnote %} |
**marketplace_request_id** | **string** | Номер заявки на маркетплейсе.  Также указывается в кабинете продавца на Маркете. | [optional]
**warehouse_request_id** | **string** | Номер заявки на складе.  Также указывается в кабинете продавца на Маркете. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
