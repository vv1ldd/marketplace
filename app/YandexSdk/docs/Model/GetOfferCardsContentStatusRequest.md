# GetOfferCardsContentStatusRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**offer_ids** | **string[]** | Идентификаторы товаров, информация о которых нужна. &lt;br&gt;&lt;br&gt; ⚠️ Не используйте это поле одновременно с фильтрами по статусам карточек, категориям, брендам или тегам. Если вы хотите воспользоваться фильтрами, оставьте поле пустым. | [optional]
**card_statuses** | [**\AppYandexSdk\Model\OfferCardStatusType[]**](OfferCardStatusType.md) | Фильтр по статусам карточек.  [Что такое карточка товара](https://yandex.ru/support/marketplace/assortment/content/index.html) | [optional]
**category_ids** | **int[]** | Фильтр по категориям на Маркете. | [optional]
**with_recommendations** | **bool** | Возвращать ли список рекомендаций к заполнению карточки и средний рейтинг карточки у товаров той категории, которая указана в &#x60;marketCategoryId&#x60;.  Значение по умолчанию: &#x60;false&#x60;. Если информация нужна, передайте значение &#x60;true&#x60;. | [optional] [default to false]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
