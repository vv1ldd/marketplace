# CategoryParameterDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Идентификатор характеристики. |
**name** | **string** | Название характеристики. | [optional]
**type** | [**\AppYandexSdk\Model\ParameterType**](ParameterType.md) |  |
**unit** | [**\AppYandexSdk\Model\CategoryParameterUnitDTO**](CategoryParameterUnitDTO.md) |  | [optional]
**description** | **string** | Описание характеристики. | [optional]
**recommendation_types** | [**\AppYandexSdk\Model\OfferCardRecommendationType[]**](OfferCardRecommendationType.md) | Перечень возможных рекомендаций по заполнению карточки, к которым относится данная характеристика. | [optional]
**required** | **bool** | Обязательность характеристики. |
**filtering** | **bool** | Используется ли характеристика в фильтре. |
**distinctive** | **bool** | Является ли характеристика особенностью варианта. |
**multivalue** | **bool** | Можно ли передать сразу несколько значений. |
**allow_custom_values** | **bool** | Можно ли передавать собственное значение, которого нет в списке вариантов Маркета. Только для характеристик типа &#x60;ENUM&#x60;. |
**values** | [**\AppYandexSdk\Model\ParameterValueOptionDTO[]**](ParameterValueOptionDTO.md) | Список допустимых значений параметра. Только для характеристик типа &#x60;ENUM&#x60;. | [optional]
**constraints** | [**\AppYandexSdk\Model\ParameterValueConstraintsDTO**](ParameterValueConstraintsDTO.md) |  | [optional]
**value_restrictions** | [**\AppYandexSdk\Model\ValueRestrictionDTO[]**](ValueRestrictionDTO.md) | Ограничения на значения, накладываемые другими характеристиками. Только для характеристик типа &#x60;ENUM&#x60;. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
