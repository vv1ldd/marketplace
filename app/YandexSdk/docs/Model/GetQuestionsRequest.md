# GetQuestionsRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**category_ids** | **int[]** | Идентификаторы категорий товаров. | [optional]
**date_from** | **\DateTime** | Дата начала периода создания вопроса.  Если параметр не указан, возвращается информация за 1 месяц до указанной в &#x60;dateTo&#x60; даты.  Максимальный интервал 1 месяц. | [optional]
**date_to** | **\DateTime** | Дата окончания периода создания вопроса.  Если параметр не указан, используется текущая дата.  Максимальный интервал 1 месяц. | [optional]
**need_answer** | **bool** | Нужен ли ответ на вопрос.  * &#x60;true&#x60; — только вопросы, которые ждут ответа. * &#x60;false&#x60; — все вопросы. | [optional] [default to false]
**sort** | [**\AppYandexSdk\Model\QuestionSortOrderType**](QuestionSortOrderType.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
