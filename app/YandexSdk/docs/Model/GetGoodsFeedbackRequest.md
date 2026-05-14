# GetGoodsFeedbackRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**feedback_ids** | **int[]** | Идентификаторы отзывов.  ⚠️ Не используйте это поле одновременно с другими фильтрами. Если вы хотите воспользоваться ими, оставьте поле пустым. | [optional]
**date_time_from** | **\DateTime** | Начало периода. Не включительно.  Если параметр не указан, возвращается информация за 6 месяцев до указанной в &#x60;dateTimeTo&#x60; даты.  Максимальный интервал 6 месяцев. | [optional]
**date_time_to** | **\DateTime** | Конец периода. Не включительно.  Если параметр не указан, используется текущая дата.  Максимальный интервал 6 месяцев. | [optional]
**reaction_status** | [**\AppYandexSdk\Model\FeedbackReactionStatusType**](FeedbackReactionStatusType.md) |  | [optional]
**rating_values** | **int[]** | Оценка товара. | [optional]
**offer_ids** | **string[]** | Фильтр по идентификатору товара. | [optional]
**paid** | **bool** | Фильтр отзывов за баллы Плюса. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
