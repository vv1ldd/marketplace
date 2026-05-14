# GenerateUnitedMarketplaceServicesReportRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**business_id** | **int** | Идентификатор кабинета. {% if audience &#x3D;&#x3D; \&quot;partner\&quot; %}Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) {% endif %} |
**date_time_from** | **\DateTime** | Начало периода, включительно. | [optional]
**date_time_to** | **\DateTime** | Конец периода, включительно. Максимальный период — 3 месяца. | [optional]
**date_from** | **\DateTime** | Начало периода, включительно.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. | [optional]
**date_to** | **\DateTime** | Конец периода, включительно. Максимальный период — 3 месяца.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. | [optional]
**year_from** | **int** | Год. | [optional]
**month_from** | **int** | Номер месяца. | [optional]
**year_to** | **int** | Год. | [optional]
**month_to** | **int** | Номер месяца. | [optional]
**placement_programs** | [**\AppYandexSdk\Model\PlacementType[]**](PlacementType.md) | Список моделей, которые нужны в отчете. | [optional]
**inns** | **string[]** | Список ИНН, которые нужны в отчете. | [optional]
**campaign_ids** | **int[]** | Список идентификаторов кампании тех магазинов, которые нужны в отчете. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
