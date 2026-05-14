# ReturnDecisionDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**return_item_id** | **int** | Идентификатор товара в возврате. | [optional]
**count** | **int** | Количество единиц товара. | [optional]
**comment** | **string** | Комментарий. | [optional]
**reason_type** | [**\AppYandexSdk\Model\ReturnDecisionReasonType**](ReturnDecisionReasonType.md) |  | [optional]
**subreason_type** | [**\AppYandexSdk\Model\ReturnDecisionSubreasonType**](ReturnDecisionSubreasonType.md) |  | [optional]
**decision_type** | [**\AppYandexSdk\Model\ReturnDecisionType**](ReturnDecisionType.md) |  | [optional]
**refund_amount** | **int** | {% note warning \&quot;Вместо него используйте &#x60;amount&#x60;.\&quot; %}     {% endnote %}  Сумма возврата в копейках. | [optional]
**amount** | [**\AppYandexSdk\Model\CurrencyValueDTO**](CurrencyValueDTO.md) |  | [optional]
**partner_compensation** | **int** | {% note warning \&quot;Вместо него используйте &#x60;partnerCompensationAmount&#x60;.\&quot; %}     {% endnote %}  Компенсация за обратную доставку в копейках. | [optional]
**partner_compensation_amount** | [**\AppYandexSdk\Model\CurrencyValueDTO**](CurrencyValueDTO.md) |  | [optional]
**images** | **string[]** | Список хеш-кодов фотографий товара от покупателя. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
