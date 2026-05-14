# GenerateUnitedOrdersRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**business_id** | **int** | Идентификатор кабинета. {% if audience &#x3D;&#x3D; \&quot;partner\&quot; %}Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) {% endif %} |
**date_from** | **\DateTime** | Начало периода, включительно.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. |
**date_to** | **\DateTime** | Конец периода, включительно. Максимальный период — 1 год.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. |
**campaign_ids** | **int[]** | Список идентификаторов кампании тех магазинов, которые нужны в отчете. | [optional]
**promo_id** | **string** | Идентификатор акции, товары из которой нужны в отчете. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
