# AppYandexSdk\LogisticPointsApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getLogisticPoints()**](LogisticPointsApi.md#getLogisticPoints) | **POST** /v1/businesses/{businessId}/logistics-points | Получение точек ПВЗ Маркета |


## `getLogisticPoints()`

```php
getLogisticPoints($business_id, $page_token, $limit): \AppYandexSdk\Model\GetLogisticPointsResponse
```

Получение точек ПВЗ Маркета

{% include notitle [access](../../_auto/method_scopes/getLogisticPoints.md) %}  Возвращает список пунктов выдачи заказов Маркета.  Регулярно запрашивайте эту информацию, чтобы в системе магазина хранить актуальные данные. Например, раз в день.  {% include notitle [limit](../../_auto/method_limits/getLogisticPoints.md) %}

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: ApiKey
$config = AppYandexSdk\Configuration::getDefaultConfiguration()->setApiKey('Api-Key', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = AppYandexSdk\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Api-Key', 'Bearer');

// Configure OAuth2 access token for authorization: OAuth
$config = AppYandexSdk\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new AppYandexSdk\Api\LogisticPointsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 250; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->getLogisticPoints($business_id, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling LogisticPointsApi->getLogisticPoints: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 250] |

### Return type

[**\AppYandexSdk\Model\GetLogisticPointsResponse**](../Model/GetLogisticPointsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
