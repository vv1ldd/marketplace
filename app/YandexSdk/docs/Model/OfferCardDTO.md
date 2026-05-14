# OfferCardDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**offer_id** | **string** | Ваш SKU — идентификатор товара в вашей системе.  Правила использования SKU:  * У каждого товара SKU должен быть свой.  * Уже заданный SKU нельзя освободить и использовать заново для другого товара. Каждый товар должен получать новый идентификатор, до того никогда не использовавшийся в вашем каталоге.  SKU товара можно изменить в кабинете продавца на Маркете. О том, как это сделать, читайте [в Справке Маркета для продавцов](https://yandex.ru/support2/marketplace/ru/assortment/operations/edit-sku).  {% note warning %}  Пробельные символы в начале и конце значения автоматически удаляются. Например, &#x60;\&quot;  SKU123  \&quot;&#x60; и &#x60;\&quot;SKU123\&quot;&#x60; будут обработаны как одинаковые значения.  {% endnote %}  [Что такое SKU и как его назначать](https://yandex.ru/support/marketplace/assortment/add/index.html#fields) |
**mapping** | [**\AppYandexSdk\Model\GetMappingDTO**](GetMappingDTO.md) |  | [optional]
**parameter_values** | [**\AppYandexSdk\Model\ParameterValueDTO[]**](ParameterValueDTO.md) | Список характеристик с их значениями. | [optional]
**card_status** | [**\AppYandexSdk\Model\OfferCardStatusType**](OfferCardStatusType.md) |  | [optional]
**content_rating** | **int** | Рейтинг карточки. | [optional]
**average_content_rating** | **int** | Средний рейтинг карточки у товаров той категории, которая указана в &#x60;marketCategoryId&#x60;.  Возвращается, только если параметр &#x60;withRecommendations&#x60; имеет значение &#x60;true&#x60;. | [optional]
**content_rating_status** | [**\AppYandexSdk\Model\OfferCardContentStatusType**](OfferCardContentStatusType.md) |  | [optional]
**recommendations** | [**\AppYandexSdk\Model\OfferCardRecommendationDTO[]**](OfferCardRecommendationDTO.md) | Список рекомендаций к заполнению карточки.  Возвращается, только если параметр &#x60;withRecommendations&#x60; имеет значение &#x60;true&#x60;.  Рекомендации Маркета помогают заполнять карточку так, чтобы покупателям было проще найти ваш товар и решиться на покупку. | [optional]
**group_id** | **string** | Идентификатор группы товаров.  У товаров, которые объединены в одну группу, будет одинаковый идентификатор.  [Как объединить товары на карточке](../../step-by-step/assortment-add-goods.md#combine-variants) | [optional]
**errors** | [**\AppYandexSdk\Model\OfferErrorDTO[]**](OfferErrorDTO.md) | Ошибки в контенте, препятствующие размещению товара на витрине. | [optional]
**warnings** | [**\AppYandexSdk\Model\OfferErrorDTO[]**](OfferErrorDTO.md) | Связанные с контентом предупреждения, не препятствующие размещению товара на витрине. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
