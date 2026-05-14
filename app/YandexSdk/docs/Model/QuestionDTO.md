# QuestionDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**question_identifiers** | [**\AppYandexSdk\Model\QuestionIdentifiersDTO**](QuestionIdentifiersDTO.md) |  |
**business_id** | **int** | Идентификатор кабинета. {% if audience &#x3D;&#x3D; \&quot;partner\&quot; %}Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) {% endif %} |
**text** | **string** | Текстовое содержимое. |
**created_at** | **\DateTime** | Дата и время создания вопроса. |
**votes** | [**\AppYandexSdk\Model\VotesDTO**](VotesDTO.md) |  |
**author** | [**\AppYandexSdk\Model\QuestionsTextContentAuthorDTO**](QuestionsTextContentAuthorDTO.md) |  |

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
