# AppYandexSdk\RegionsApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getRegionsCodes()**](RegionsApi.md#getRegionsCodes) | **POST** /v2/regions/countries | Список допустимых кодов стран |
| [**searchRegionChildren()**](RegionsApi.md#searchRegionChildren) | **GET** /v2/regions/{regionId}/children | Информация о дочерних регионах |
| [**searchRegionsById()**](RegionsApi.md#searchRegionsById) | **GET** /v2/regions/{regionId} | Информация о регионе |
| [**searchRegionsByName()**](RegionsApi.md#searchRegionsByName) | **GET** /v2/regions | Поиск регионов по их имени |


## `getRegionsCodes()`

```php
getRegionsCodes(): \AppYandexSdk\Model\GetRegionsCodesResponse
```

Список допустимых кодов стран

{% include notitle [access](../../_auto/method_scopes/getRegionsCodes.md) %}  Возвращает список стран с их кодами в формате :no-translate[ISO 3166-1 alpha-2].  Страна производства `countryCode` понадобится при продаже товаров из-за рубежа для бизнеса. [Инструкция](../../step-by-step/business-info.md)  {% include notitle [limit](../../_auto/method_limits/getRegionsCodes.md) %}

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


$apiInstance = new AppYandexSdk\Api\RegionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->getRegionsCodes();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling RegionsApi->getRegionsCodes: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\AppYandexSdk\Model\GetRegionsCodesResponse**](../Model/GetRegionsCodesResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `searchRegionChildren()`

```php
searchRegionChildren($region_id, $page_token, $limit, $page, $page_size): \AppYandexSdk\Model\GetRegionWithChildrenResponse
```

Информация о дочерних регионах

{% include notitle [access](../../_auto/method_scopes/searchRegionChildren.md) %}  Возвращает информацию о регионах, являющихся дочерними по отношению к региону, идентификатор которого указан в запросе.  {% include notitle [limit](../../_auto/method_limits/searchRegionChildren.md) %}

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


$apiInstance = new AppYandexSdk\Api\RegionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$region_id = 56; // int | Идентификатор региона.  Идентификатор региона можно получить c помощью запроса [GET v2/regions](../../reference/regions/searchRegionsByName.md).
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 56; // int | {{ limit-truncate-param-description }}  {% note warning %}  У данного лимита нет значения по умолчанию.  {% endnote %}
$page = 1; // int | {% note warning \"Устаревший параметр\" %}  Вместо `page` и `pageSize` используйте пагинацию по `pageToken` и `limit`.  [Подробнее о типах пагинации и их использовании](../../concepts/pagination.md)  {% endnote %}  Номер страницы результатов.  Используется вместе с параметром `pageSize`.
$page_size = 56; // int | {% note warning \"Устаревший параметр\" %}  Вместо `page` и `pageSize` используйте пагинацию по `pageToken` и `limit`.  [Подробнее о типах пагинации и их использовании](../../concepts/pagination.md)  {% endnote %}  Размер страницы.  Используется вместе с параметром `page`.

try {
    $result = $apiInstance->searchRegionChildren($region_id, $page_token, $limit, $page, $page_size);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling RegionsApi->searchRegionChildren: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **region_id** | **int**| Идентификатор региона.  Идентификатор региона можно получить c помощью запроса [GET v2/regions](../../reference/regions/searchRegionsByName.md). | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-truncate-param-description }}  {% note warning %}  У данного лимита нет значения по умолчанию.  {% endnote %} | [optional] |
| **page** | **int**| {% note warning \&quot;Устаревший параметр\&quot; %}  Вместо &#x60;page&#x60; и &#x60;pageSize&#x60; используйте пагинацию по &#x60;pageToken&#x60; и &#x60;limit&#x60;.  [Подробнее о типах пагинации и их использовании](../../concepts/pagination.md)  {% endnote %}  Номер страницы результатов.  Используется вместе с параметром &#x60;pageSize&#x60;. | [optional] [default to 1] |
| **page_size** | **int**| {% note warning \&quot;Устаревший параметр\&quot; %}  Вместо &#x60;page&#x60; и &#x60;pageSize&#x60; используйте пагинацию по &#x60;pageToken&#x60; и &#x60;limit&#x60;.  [Подробнее о типах пагинации и их использовании](../../concepts/pagination.md)  {% endnote %}  Размер страницы.  Используется вместе с параметром &#x60;page&#x60;. | [optional] |

### Return type

[**\AppYandexSdk\Model\GetRegionWithChildrenResponse**](../Model/GetRegionWithChildrenResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `searchRegionsById()`

```php
searchRegionsById($region_id): \AppYandexSdk\Model\GetRegionByIdResponse
```

Информация о регионе

{% include notitle [access](../../_auto/method_scopes/searchRegionsById.md) %}  Возвращает информацию о регионе.  {% include notitle [limit](../../_auto/method_limits/searchRegionsById.md) %}

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


$apiInstance = new AppYandexSdk\Api\RegionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$region_id = 56; // int | Идентификатор региона.  Идентификатор региона можно получить c помощью запроса [GET v2/regions](../../reference/regions/searchRegionsByName.md).

try {
    $result = $apiInstance->searchRegionsById($region_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling RegionsApi->searchRegionsById: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **region_id** | **int**| Идентификатор региона.  Идентификатор региона можно получить c помощью запроса [GET v2/regions](../../reference/regions/searchRegionsByName.md). | |

### Return type

[**\AppYandexSdk\Model\GetRegionByIdResponse**](../Model/GetRegionByIdResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `searchRegionsByName()`

```php
searchRegionsByName($name, $page_token, $limit): \AppYandexSdk\Model\GetRegionsResponse
```

Поиск регионов по их имени

{% include notitle [access](../../_auto/method_scopes/searchRegionsByName.md) %}  Возвращает информацию о регионе, удовлетворяющем заданным в запросе условиям поиска.  Если найдено несколько регионов, удовлетворяющих условиям поиска, возвращается информация по каждому найденному региону (но не более десяти регионов) для возможности определения нужного региона по родительским регионам.  {% include notitle [limit](../../_auto/method_limits/searchRegionsByName.md) %}

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


$apiInstance = new AppYandexSdk\Api\RegionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$name = 'name_example'; // string | Название региона.  Важно учитывать регистр: первая буква должна быть заглавной, остальные — строчными. Например, `Москва`.
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 10; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->searchRegionsByName($name, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling RegionsApi->searchRegionsByName: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **name** | **string**| Название региона.  Важно учитывать регистр: первая буква должна быть заглавной, остальные — строчными. Например, &#x60;Москва&#x60;. | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 10] |

### Return type

[**\AppYandexSdk\Model\GetRegionsResponse**](../Model/GetRegionsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
