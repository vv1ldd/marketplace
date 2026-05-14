# AppYandexSdk\AuthApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getAuthTokenInfo()**](AuthApi.md#getAuthTokenInfo) | **POST** /v2/auth/token | Получение информации о токене авторизации |


## `getAuthTokenInfo()`

```php
getAuthTokenInfo(): \AppYandexSdk\Model\GetTokenInfoResponse
```

Получение информации о токене авторизации

{% include notitle [access](../../_auto/method_scopes/getAuthTokenInfo.md) %}  {% note info \"Метод доступен только для Api-Key-токена.\" %}     {% endnote %}  Возвращает информацию о переданном токене авторизации.  {% include notitle [limit](../../_auto/method_limits/getAuthTokenInfo.md) %}

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


$apiInstance = new AppYandexSdk\Api\AuthApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->getAuthTokenInfo();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling AuthApi->getAuthTokenInfo: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\AppYandexSdk\Model\GetTokenInfoResponse**](../Model/GetTokenInfoResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
