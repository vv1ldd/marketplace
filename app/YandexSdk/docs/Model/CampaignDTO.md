# CampaignDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**domain** | **string** | Название магазина. | [optional]
**id** | **int** | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | [optional]
**client_id** | **int** | Идентификатор плательщика в Яндекс Балансе. | [optional]
**business** | [**\AppYandexSdk\Model\BusinessDTO**](BusinessDTO.md) |  | [optional]
**placement_type** | [**\AppYandexSdk\Model\PlacementType**](PlacementType.md) |  | [optional]
**api_availability** | [**\AppYandexSdk\Model\ApiAvailabilityStatusType**](ApiAvailabilityStatusType.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
