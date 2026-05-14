# OrdersStatsPaymentDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **string** | Идентификатор денежного перевода. | [optional]
**date** | **\DateTime** | Дата денежного перевода.  Формат даты: &#x60;ГГГГ-ММ-ДД&#x60;. | [optional]
**type** | [**\AppYandexSdk\Model\OrdersStatsPaymentType**](OrdersStatsPaymentType.md) |  | [optional]
**source** | [**\AppYandexSdk\Model\OrdersStatsPaymentSourceType**](OrdersStatsPaymentSourceType.md) |  | [optional]
**total** | **float** | Сумма денежного перевода.  Точность — два знака после запятой. | [optional]
**payment_order** | [**\AppYandexSdk\Model\OrdersStatsPaymentOrderDTO**](OrdersStatsPaymentOrderDTO.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
