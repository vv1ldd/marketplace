# GenerateStocksOnWarehousesReportRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**campaign_id** | **int** | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | [optional]
**business_id** | **int** | Идентификатор кабинета. {% if audience &#x3D;&#x3D; \&quot;partner\&quot; %}Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) {% endif %} | [optional]
**warehouse_ids** | **int[]** | Фильтр по идентификаторам складов (только модели FBY и LaaS). Чтобы узнать идентификатор, воспользуйтесь запросом [GET v2/warehouses](../../reference/warehouses/getFulfillmentWarehouses.md). | [optional]
**report_date** | **\DateTime** | Фильтр по дате (для моделей FBY и LaaS). В отчет попадут данные за **предшествующий** дате день.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. | [optional]
**category_ids** | **int[]** | Фильтр по категориям на Маркете (кроме моделей FBY и LaaS). | [optional]
**has_stocks** | **bool** | Фильтр по наличию остатков (кроме моделей FBY и LaaS). | [optional]
**campaign_ids** | **int[]** | Фильтр по магазинам для отчета по кабинету (кроме моделей FBY и LaaS).  Передавайте вместе с &#x60;businessId&#x60;. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
