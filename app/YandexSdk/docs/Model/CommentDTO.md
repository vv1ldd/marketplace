# CommentDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор комментария к ответу. |
**text** | **string** | Текстовое содержимое. |
**can_modify** | **bool** | Может ли продавец изменять комментарий или удалять его. | [optional]
**parent_id** | **int** | Идентификатор комментария к ответу. | [optional]
**author** | [**\AppYandexSdk\Model\QuestionsTextContentAuthorDTO**](QuestionsTextContentAuthorDTO.md) |  | [optional]
**status** | [**\AppYandexSdk\Model\QuestionsTextContentModerationStatusType**](QuestionsTextContentModerationStatusType.md) |  |
**answer_id** | **int** | Идентификатор ответа на вопрос. |
**created_at** | **\DateTime** | Дата создания комментария. |
**votes** | [**\AppYandexSdk\Model\VotesDTO**](VotesDTO.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
