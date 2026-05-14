# CalculateTariffsParametersDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**campaign_id** | **int** | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | [optional]
**selling_program** | [**\AppYandexSdk\Model\SellingProgramType**](SellingProgramType.md) |  | [optional]
**frequency** | [**\AppYandexSdk\Model\PaymentFrequencyType**](PaymentFrequencyType.md) |  | [optional]
**payment_delay_weeks** | **int** | Отсрочка выплат при еженедельном графике — сколько недель назад были доставлены заказы, за которые приходит выплата.  Допустимые значения: 0, 1, 2 или 4.  Значения параметра &#x60;paymentDelayWeeks&#x60;, отличные от 0, допускаются только вместе с параметром &#x60;frequency&#x60; равным &#39;WEEKLY&#39;. Использование других значений параметра &#x60;frequency&#x60; совместно с &#x60;paymentDelayWeeks&#x60; приведет к ошибке. | [optional]
**currency** | [**\AppYandexSdk\Model\CurrencyType**](CurrencyType.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
