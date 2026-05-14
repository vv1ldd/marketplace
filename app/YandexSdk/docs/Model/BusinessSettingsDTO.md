# BusinessSettingsDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**only_default_price** | **bool** | Управление ценами на товары:  * &#x60;false&#x60; — можно установить цену, которая действует:   * во всех магазинах кабинета — [POST v2/businesses/{businessId}/offer-prices/updates](../../reference/business-assortment/updateBusinessPrices.md);   * в конкретном магазине — [POST v2/campaigns/{campaignId}/offer-prices/updates](../../reference/assortment/updatePrices.md). * &#x60;true&#x60; — можно установить только цену, которая действует во всех магазинах кабинета, — [POST v2/businesses/{businessId}/offer-prices/updates](../../reference/business-assortment/updateBusinessPrices.md). | [optional]
**currency** | [**\AppYandexSdk\Model\CurrencyType**](CurrencyType.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
