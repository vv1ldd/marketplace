# AppYandexSdk\GoodsQuestionsApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getGoodsQuestionAnswers()**](GoodsQuestionsApi.md#getGoodsQuestionAnswers) | **POST** /v1/businesses/{businessId}/goods-questions/answers | Получение ответов на вопрос |
| [**getGoodsQuestions()**](GoodsQuestionsApi.md#getGoodsQuestions) | **POST** /v1/businesses/{businessId}/goods-questions | Получение вопросов о товарах продавца |
| [**updateGoodsQuestionTextEntity()**](GoodsQuestionsApi.md#updateGoodsQuestionTextEntity) | **POST** /v1/businesses/{businessId}/goods-questions/update | Создание, изменение и удаление ответа или комментария |


## `getGoodsQuestionAnswers()`

```php
getGoodsQuestionAnswers($business_id, $get_answers_request, $page_token, $limit): \AppYandexSdk\Model\GetAnswersResponse
```

Получение ответов на вопрос

{% include notitle [access](../../_auto/method_scopes/getGoodsQuestionAnswers.md) %}  Возвращает ответы на вопрос о товаре по указанным фильтрам.  Результаты возвращаются постранично, одна страница содержит не более 50 ответов.  {% include notitle [limit](../../_auto/method_limits/getGoodsQuestionAnswers.md) %}

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


$apiInstance = new AppYandexSdk\Api\GoodsQuestionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$get_answers_request = new \AppYandexSdk\Model\GetAnswersRequest(); // \AppYandexSdk\Model\GetAnswersRequest
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 25; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->getGoodsQuestionAnswers($business_id, $get_answers_request, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling GoodsQuestionsApi->getGoodsQuestionAnswers: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **get_answers_request** | [**\AppYandexSdk\Model\GetAnswersRequest**](../Model/GetAnswersRequest.md)|  | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 25] |

### Return type

[**\AppYandexSdk\Model\GetAnswersResponse**](../Model/GetAnswersResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getGoodsQuestions()`

```php
getGoodsQuestions($business_id, $page_token, $limit, $get_questions_request): \AppYandexSdk\Model\GetQuestionsResponse
```

Получение вопросов о товарах продавца

{% include notitle [access](../../_auto/method_scopes/getGoodsQuestions.md) %}  Возвращает вопросы о товарах продавца по указанным фильтрам.  Результаты возвращаются постранично, одна страница содержит не более 50 вопросов.  {% include notitle [limit](../../_auto/method_limits/getGoodsQuestions.md) %}

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


$apiInstance = new AppYandexSdk\Api\GoodsQuestionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 25; // int | {{ limit-param-description }}
$get_questions_request = new \AppYandexSdk\Model\GetQuestionsRequest(); // \AppYandexSdk\Model\GetQuestionsRequest

try {
    $result = $apiInstance->getGoodsQuestions($business_id, $page_token, $limit, $get_questions_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling GoodsQuestionsApi->getGoodsQuestions: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 25] |
| **get_questions_request** | [**\AppYandexSdk\Model\GetQuestionsRequest**](../Model/GetQuestionsRequest.md)|  | [optional] |

### Return type

[**\AppYandexSdk\Model\GetQuestionsResponse**](../Model/GetQuestionsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updateGoodsQuestionTextEntity()`

```php
updateGoodsQuestionTextEntity($business_id, $update_goods_question_text_entity_request): \AppYandexSdk\Model\UpdateGoodsQuestionTextEntityResponse
```

Создание, изменение и удаление ответа или комментария

{% include notitle [access](../../_auto/method_scopes/updateGoodsQuestionTextEntity.md) %}  Создание, изменение и удаление ответа или комментария.  {% include notitle [limit](../../_auto/method_limits/updateGoodsQuestionTextEntity.md) %}

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


$apiInstance = new AppYandexSdk\Api\GoodsQuestionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$update_goods_question_text_entity_request = new \AppYandexSdk\Model\UpdateGoodsQuestionTextEntityRequest(); // \AppYandexSdk\Model\UpdateGoodsQuestionTextEntityRequest

try {
    $result = $apiInstance->updateGoodsQuestionTextEntity($business_id, $update_goods_question_text_entity_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling GoodsQuestionsApi->updateGoodsQuestionTextEntity: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **update_goods_question_text_entity_request** | [**\AppYandexSdk\Model\UpdateGoodsQuestionTextEntityRequest**](../Model/UpdateGoodsQuestionTextEntityRequest.md)|  | |

### Return type

[**\AppYandexSdk\Model\UpdateGoodsQuestionTextEntityResponse**](../Model/UpdateGoodsQuestionTextEntityResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
