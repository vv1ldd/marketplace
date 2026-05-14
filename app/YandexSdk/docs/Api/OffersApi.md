# AppYandexSdk\OffersApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**deleteCampaignOffers()**](OffersApi.md#deleteCampaignOffers) | **POST** /v2/campaigns/{campaignId}/offers/delete | Удаление товаров из ассортимента магазина |
| [**getCampaignOffers()**](OffersApi.md#getCampaignOffers) | **POST** /v2/campaigns/{campaignId}/offers | Информация о товарах, которые размещены в заданном магазине |
| [**getOfferRecommendations()**](OffersApi.md#getOfferRecommendations) | **POST** /v2/businesses/{businessId}/offers/recommendations | Рекомендации Маркета, касающиеся цен |
| [**updateCampaignOffers()**](OffersApi.md#updateCampaignOffers) | **POST** /v2/campaigns/{campaignId}/offers/update | Изменение условий продажи товаров в магазине |


## `deleteCampaignOffers()`

```php
deleteCampaignOffers($campaign_id, $delete_campaign_offers_request): \AppYandexSdk\Model\DeleteCampaignOffersResponse
```

Удаление товаров из ассортимента магазина

{% include notitle [access](../../_auto/method_scopes/deleteCampaignOffers.md) %}  Удаляет заданные товары из заданного магазина.  {% note warning \"Запрос удаляет товары из конкретного магазина\" %}  На продажи в других магазинах и на наличие товара в общем каталоге он не влияет.  {% endnote %}  Товар не получится удалить, если он хранится на складах Маркета.  {% include notitle [limit](../../_auto/method_limits/deleteCampaignOffers.md) %}

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


$apiInstance = new AppYandexSdk\Api\OffersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$delete_campaign_offers_request = new \AppYandexSdk\Model\DeleteCampaignOffersRequest(); // \AppYandexSdk\Model\DeleteCampaignOffersRequest

try {
    $result = $apiInstance->deleteCampaignOffers($campaign_id, $delete_campaign_offers_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OffersApi->deleteCampaignOffers: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **delete_campaign_offers_request** | [**\AppYandexSdk\Model\DeleteCampaignOffersRequest**](../Model/DeleteCampaignOffersRequest.md)|  | |

### Return type

[**\AppYandexSdk\Model\DeleteCampaignOffersResponse**](../Model/DeleteCampaignOffersResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getCampaignOffers()`

```php
getCampaignOffers($campaign_id, $get_campaign_offers_request, $page_token, $limit): \AppYandexSdk\Model\GetCampaignOffersResponse
```

Информация о товарах, которые размещены в заданном магазине

{% include notitle [access](../../_auto/method_scopes/getCampaignOffers.md) %}  Возвращает список товаров, которые размещены в заданном магазине. Для каждого товара указываются параметры размещения.  {% include notitle [limit](../../_auto/method_limits/getCampaignOffers.md) %}

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


$apiInstance = new AppYandexSdk\Api\OffersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$get_campaign_offers_request = new \AppYandexSdk\Model\GetCampaignOffersRequest(); // \AppYandexSdk\Model\GetCampaignOffersRequest
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 100; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->getCampaignOffers($campaign_id, $get_campaign_offers_request, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OffersApi->getCampaignOffers: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **get_campaign_offers_request** | [**\AppYandexSdk\Model\GetCampaignOffersRequest**](../Model/GetCampaignOffersRequest.md)|  | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 100] |

### Return type

[**\AppYandexSdk\Model\GetCampaignOffersResponse**](../Model/GetCampaignOffersResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getOfferRecommendations()`

```php
getOfferRecommendations($business_id, $get_offer_recommendations_request, $page_token, $limit): \AppYandexSdk\Model\GetOfferRecommendationsResponse
```

Рекомендации Маркета, касающиеся цен

{% include notitle [access](../../_auto/method_scopes/getOfferRecommendations.md) %}  Метод возвращает рекомендации нескольких типов.  1. Порог для привлекательной цены. 2. Оценка привлекательности цен на витрине.  Рекомендации показывают, какие цены нужно установить, чтобы привлечь покупателя.  В запросе можно использовать фильтры. Результаты возвращаются постранично.  {% include notitle [limit](../../_auto/method_limits/getOfferRecommendations.md) %}

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


$apiInstance = new AppYandexSdk\Api\OffersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$get_offer_recommendations_request = new \AppYandexSdk\Model\GetOfferRecommendationsRequest(); // \AppYandexSdk\Model\GetOfferRecommendationsRequest
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 100; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->getOfferRecommendations($business_id, $get_offer_recommendations_request, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OffersApi->getOfferRecommendations: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **get_offer_recommendations_request** | [**\AppYandexSdk\Model\GetOfferRecommendationsRequest**](../Model/GetOfferRecommendationsRequest.md)|  | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 100] |

### Return type

[**\AppYandexSdk\Model\GetOfferRecommendationsResponse**](../Model/GetOfferRecommendationsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updateCampaignOffers()`

```php
updateCampaignOffers($campaign_id, $update_campaign_offers_request): \AppYandexSdk\Model\EmptyApiResponse
```

Изменение условий продажи товаров в магазине

{% include notitle [access](../../_auto/method_scopes/updateCampaignOffers.md) %}  Изменяет параметры размещения товаров в конкретном магазине: доступность товара, продажа квантами и применяемый НДС.  {% include notitle [limit](../../_auto/method_limits/updateCampaignOffers.md) %}

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


$apiInstance = new AppYandexSdk\Api\OffersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$update_campaign_offers_request = new \AppYandexSdk\Model\UpdateCampaignOffersRequest(); // \AppYandexSdk\Model\UpdateCampaignOffersRequest

try {
    $result = $apiInstance->updateCampaignOffers($campaign_id, $update_campaign_offers_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OffersApi->updateCampaignOffers: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **update_campaign_offers_request** | [**\AppYandexSdk\Model\UpdateCampaignOffersRequest**](../Model/UpdateCampaignOffersRequest.md)|  | |

### Return type

[**\AppYandexSdk\Model\EmptyApiResponse**](../Model/EmptyApiResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
