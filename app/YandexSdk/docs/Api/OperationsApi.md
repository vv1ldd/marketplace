# AppYandexSdk\OperationsApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getOperations()**](OperationsApi.md#getOperations) | **POST** /v1/businesses/{businessId}/operations | Получение статусов операций |


## `getOperations()`

```php
getOperations($business_id, $get_operations_request): \AppYandexSdk\Model\GetOperationsResponse
```

Получение статусов операций

{% include notitle [access](../../_auto/method_scopes/getOperations.md) %}  Возвращает статусы запущенных операций по их идентификаторам.  {% include notitle [limit](../../_auto/method_limits/getOperations.md) %}

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


$apiInstance = new AppYandexSdk\Api\OperationsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$get_operations_request = new \AppYandexSdk\Model\GetOperationsRequest(); // \AppYandexSdk\Model\GetOperationsRequest

try {
    $result = $apiInstance->getOperations($business_id, $get_operations_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OperationsApi->getOperations: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **get_operations_request** | [**\AppYandexSdk\Model\GetOperationsRequest**](../Model/GetOperationsRequest.md)|  | |

### Return type

[**\AppYandexSdk\Model\GetOperationsResponse**](../Model/GetOperationsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
