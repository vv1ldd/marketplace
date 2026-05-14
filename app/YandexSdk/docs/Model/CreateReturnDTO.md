# CreateReturnDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**external_return_id** | **string** | Внешний идентификатор возврата в системе магазина. |
**order_id** | **int** | Идентификатор заказа, по которому нужно сделать возврат. |
**items** | [**\AppYandexSdk\Model\CreateReturnItemDTO[]**](CreateReturnItemDTO.md) | Список товаров в возврате. |
**customer** | [**\AppYandexSdk\Model\CustomerDTO**](CustomerDTO.md) |  |
**return_option** | [**\AppYandexSdk\Model\CreateReturnOptionDTO**](CreateReturnOptionDTO.md) |  |

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
