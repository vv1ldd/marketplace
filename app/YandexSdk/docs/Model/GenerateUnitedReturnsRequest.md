# GenerateUnitedReturnsRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**business_id** | **int** | Идентификатор кабинета. {% if audience &#x3D;&#x3D; \&quot;partner\&quot; %}Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) {% endif %} |
**date_from** | **\DateTime** | Начало периода, включительно.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. |
**date_to** | **\DateTime** | Конец периода, включительно.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. |
**campaign_ids** | **int[]** | Список идентификаторов кампании тех магазинов, которые нужны в отчете. | [optional]
**return_type** | [**\AppYandexSdk\Model\ReturnType**](ReturnType.md) |  | [optional]
**return_status_types** | [**\AppYandexSdk\Model\ReturnShipmentStatusType[]**](ReturnShipmentStatusType.md) | Статусы передачи возвратов, которые нужны в отчете.  Если их не указать, вернется информация по всем возвратам. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
