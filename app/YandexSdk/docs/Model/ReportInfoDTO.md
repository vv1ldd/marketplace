# ReportInfoDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**status** | [**\AppYandexSdk\Model\ReportStatusType**](ReportStatusType.md) |  |
**sub_status** | [**\AppYandexSdk\Model\ReportSubStatusType**](ReportSubStatusType.md) |  | [optional]
**generation_requested_at** | **\DateTime** | Дата и время запроса на генерацию. |
**generation_finished_at** | **\DateTime** | Дата и время завершения генерации. | [optional]
**file** | **string** | Ссылка на готовый отчет или документ.  {% note warning \&quot;Срок действия ссылки\&quot; %}  Ссылка актуальна **60 минут** с момента получения ответа. При каждом запросе &#x60;GET /v2/reports/info/{reportId}&#x60; генерируется новая ссылка, срок действия которой ограничен.  **Рекомендация для интеграций:** сразу после получения ссылки скачайте отчет и сохраните его у себя. Не сохраняйте ссылку для последующего использования — она станет недействительной через после истечения срока действия.  {% endnote %} | [optional]
**estimated_generation_time** | **int** | Ожидаемая продолжительность генерации в миллисекундах. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
