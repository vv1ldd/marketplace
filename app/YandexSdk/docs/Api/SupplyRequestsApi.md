# AppYandexSdk\SupplyRequestsApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getSupplyRequestDocuments()**](SupplyRequestsApi.md#getSupplyRequestDocuments) | **POST** /v2/campaigns/{campaignId}/supply-requests/documents | Получение документов по заявке на поставку, вывоз или утилизацию |
| [**getSupplyRequestItems()**](SupplyRequestsApi.md#getSupplyRequestItems) | **POST** /v2/campaigns/{campaignId}/supply-requests/items | Получение товаров в заявке на поставку, вывоз или утилизацию |
| [**getSupplyRequests()**](SupplyRequestsApi.md#getSupplyRequests) | **POST** /v2/campaigns/{campaignId}/supply-requests | Получение информации о заявках на поставку, вывоз и утилизацию |


## `getSupplyRequestDocuments()`

```php
getSupplyRequestDocuments($campaign_id, $get_supply_request_documents_request): \AppYandexSdk\Model\GetSupplyRequestDocumentsResponse
```

Получение документов по заявке на поставку, вывоз или утилизацию

{% include notitle [access](../../_auto/method_scopes/getSupplyRequestDocuments.md) %}  Возвращает документы по заявке.  {% include notitle [limit](../../_auto/method_limits/getSupplyRequestDocuments.md) %}

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


$apiInstance = new AppYandexSdk\Api\SupplyRequestsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$get_supply_request_documents_request = new \AppYandexSdk\Model\GetSupplyRequestDocumentsRequest(); // \AppYandexSdk\Model\GetSupplyRequestDocumentsRequest

try {
    $result = $apiInstance->getSupplyRequestDocuments($campaign_id, $get_supply_request_documents_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling SupplyRequestsApi->getSupplyRequestDocuments: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **get_supply_request_documents_request** | [**\AppYandexSdk\Model\GetSupplyRequestDocumentsRequest**](../Model/GetSupplyRequestDocumentsRequest.md)|  | |

### Return type

[**\AppYandexSdk\Model\GetSupplyRequestDocumentsResponse**](../Model/GetSupplyRequestDocumentsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getSupplyRequestItems()`

```php
getSupplyRequestItems($campaign_id, $get_supply_request_items_request, $page_token, $limit): \AppYandexSdk\Model\GetSupplyRequestItemsResponse
```

Получение товаров в заявке на поставку, вывоз или утилизацию

{% include notitle [access](../../_auto/method_scopes/getSupplyRequestItems.md) %}  Возвращает список товаров в заявке и информацию по ним.  {% include notitle [limit](../../_auto/method_limits/getSupplyRequestItems.md) %}

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


$apiInstance = new AppYandexSdk\Api\SupplyRequestsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$get_supply_request_items_request = new \AppYandexSdk\Model\GetSupplyRequestItemsRequest(); // \AppYandexSdk\Model\GetSupplyRequestItemsRequest
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 250; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->getSupplyRequestItems($campaign_id, $get_supply_request_items_request, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling SupplyRequestsApi->getSupplyRequestItems: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **get_supply_request_items_request** | [**\AppYandexSdk\Model\GetSupplyRequestItemsRequest**](../Model/GetSupplyRequestItemsRequest.md)|  | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 250] |

### Return type

[**\AppYandexSdk\Model\GetSupplyRequestItemsResponse**](../Model/GetSupplyRequestItemsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getSupplyRequests()`

```php
getSupplyRequests($campaign_id, $page_token, $limit, $get_supply_requests_request): \AppYandexSdk\Model\GetSupplyRequestsResponse
```

Получение информации о заявках на поставку, вывоз и утилизацию

{% include notitle [access](../../_auto/method_scopes/getSupplyRequests.md) %}  По указанным фильтрам возвращает заявки на поставку, вывоз и утилизацию, а также информацию по ним.  {% include notitle [limit](../../_auto/method_limits/getSupplyRequests.md) %}

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


$apiInstance = new AppYandexSdk\Api\SupplyRequestsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 50; // int | {{ limit-param-description }}
$get_supply_requests_request = new \AppYandexSdk\Model\GetSupplyRequestsRequest(); // \AppYandexSdk\Model\GetSupplyRequestsRequest

try {
    $result = $apiInstance->getSupplyRequests($campaign_id, $page_token, $limit, $get_supply_requests_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling SupplyRequestsApi->getSupplyRequests: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 50] |
| **get_supply_requests_request** | [**\AppYandexSdk\Model\GetSupplyRequestsRequest**](../Model/GetSupplyRequestsRequest.md)|  | [optional] |

### Return type

[**\AppYandexSdk\Model\GetSupplyRequestsResponse**](../Model/GetSupplyRequestsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
