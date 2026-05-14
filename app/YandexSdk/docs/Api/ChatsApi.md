# AppYandexSdk\ChatsApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createChat()**](ChatsApi.md#createChat) | **POST** /v2/businesses/{businessId}/chats/new | Создание нового чата с покупателем |
| [**getChat()**](ChatsApi.md#getChat) | **GET** /v2/businesses/{businessId}/chat | Получение чата по идентификатору |
| [**getChatHistory()**](ChatsApi.md#getChatHistory) | **POST** /v2/businesses/{businessId}/chats/history | Получение истории сообщений в чате |
| [**getChatMessage()**](ChatsApi.md#getChatMessage) | **GET** /v2/businesses/{businessId}/chats/message | Получение сообщения в чате |
| [**getChats()**](ChatsApi.md#getChats) | **POST** /v2/businesses/{businessId}/chats | Получение доступных чатов |
| [**sendFileToChat()**](ChatsApi.md#sendFileToChat) | **POST** /v2/businesses/{businessId}/chats/file/send | Отправка файла в чат |
| [**sendMessageToChat()**](ChatsApi.md#sendMessageToChat) | **POST** /v2/businesses/{businessId}/chats/message | Отправка сообщения в чат |


## `createChat()`

```php
createChat($business_id, $create_chat_request): \AppYandexSdk\Model\CreateChatResponse
```

Создание нового чата с покупателем

{% include notitle [access](../../_auto/method_scopes/createChat.md) %}  Создает новый чат с покупателем и возвращает информацию о нем или созданном ранее.  Типы чатов, которые может начать продавец:  * по заказам; * по возвратам (доступны только для FBY-, FBS- и Экспресс-магазинов).  {% include notitle [limit](../../_auto/method_limits/createChat.md) %}

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


$apiInstance = new AppYandexSdk\Api\ChatsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$create_chat_request = new \AppYandexSdk\Model\CreateChatRequest(); // \AppYandexSdk\Model\CreateChatRequest | description

try {
    $result = $apiInstance->createChat($business_id, $create_chat_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ChatsApi->createChat: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **create_chat_request** | [**\AppYandexSdk\Model\CreateChatRequest**](../Model/CreateChatRequest.md)| description | |

### Return type

[**\AppYandexSdk\Model\CreateChatResponse**](../Model/CreateChatResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getChat()`

```php
getChat($business_id, $chat_id): \AppYandexSdk\Model\GetChatResponse
```

Получение чата по идентификатору

{% include notitle [access](../../_auto/method_scopes/getChat.md) %}  Возвращает чат по его идентификатору.  {% note tip \"Подключите API-уведомления\" %}  Маркет отправит вам запрос [POST notification](../../push-notifications/reference/sendNotification.md), когда появится новый чат или сообщение.  [{#T}](../../push-notifications/index.md)  {% endnote %}  {% include notitle [limit](../../_auto/method_limits/getChat.md) %}

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


$apiInstance = new AppYandexSdk\Api\ChatsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$chat_id = 56; // int | Идентификатор чата.

try {
    $result = $apiInstance->getChat($business_id, $chat_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ChatsApi->getChat: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **chat_id** | **int**| Идентификатор чата. | |

### Return type

[**\AppYandexSdk\Model\GetChatResponse**](../Model/GetChatResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getChatHistory()`

```php
getChatHistory($business_id, $chat_id, $get_chat_history_request, $page_token, $limit): \AppYandexSdk\Model\GetChatHistoryResponse
```

Получение истории сообщений в чате

{% include notitle [access](../../_auto/method_scopes/getChatHistory.md) %}  Возвращает историю сообщений в чате с покупателем.  {% include notitle [limit](../../_auto/method_limits/getChatHistory.md) %}

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


$apiInstance = new AppYandexSdk\Api\ChatsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$chat_id = 56; // int | Идентификатор чата.
$get_chat_history_request = new \AppYandexSdk\Model\GetChatHistoryRequest(); // \AppYandexSdk\Model\GetChatHistoryRequest | description
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 50; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->getChatHistory($business_id, $chat_id, $get_chat_history_request, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ChatsApi->getChatHistory: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **chat_id** | **int**| Идентификатор чата. | |
| **get_chat_history_request** | [**\AppYandexSdk\Model\GetChatHistoryRequest**](../Model/GetChatHistoryRequest.md)| description | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 50] |

### Return type

[**\AppYandexSdk\Model\GetChatHistoryResponse**](../Model/GetChatHistoryResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getChatMessage()`

```php
getChatMessage($business_id, $chat_id, $message_id): \AppYandexSdk\Model\GetChatMessageResponse
```

Получение сообщения в чате

{% include notitle [access](../../_auto/method_scopes/getChatMessage.md) %}  Возвращает сообщение по его идентификатору.  {% note tip \"Подключите API-уведомления\" %}  Маркет отправит вам запрос [POST notification](../../push-notifications/reference/sendNotification.md), когда появится новый чат или сообщение.  [{#T}](../../push-notifications/index.md)  {% endnote %}  {% include notitle [limit](../../_auto/method_limits/getChatMessage.md) %}

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


$apiInstance = new AppYandexSdk\Api\ChatsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$chat_id = 56; // int | Идентификатор чата.
$message_id = 56; // int | Идентификатор сообщения.

try {
    $result = $apiInstance->getChatMessage($business_id, $chat_id, $message_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ChatsApi->getChatMessage: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **chat_id** | **int**| Идентификатор чата. | |
| **message_id** | **int**| Идентификатор сообщения. | |

### Return type

[**\AppYandexSdk\Model\GetChatMessageResponse**](../Model/GetChatMessageResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getChats()`

```php
getChats($business_id, $get_chats_request, $page_token, $limit): \AppYandexSdk\Model\GetChatsResponse
```

Получение доступных чатов

{% include notitle [access](../../_auto/method_scopes/getChats.md) %}  Возвращает чаты с покупателями.  {% note tip \"Подключите API-уведомления\" %}  Маркет отправит вам запрос [POST notification](../../push-notifications/reference/sendNotification.md), когда появится новый чат или сообщение.  [{#T}](../../push-notifications/index.md)  {% endnote %}  {% include notitle [limit](../../_auto/method_limits/getChats.md) %}

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


$apiInstance = new AppYandexSdk\Api\ChatsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$get_chats_request = new \AppYandexSdk\Model\GetChatsRequest(); // \AppYandexSdk\Model\GetChatsRequest | description
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 10; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->getChats($business_id, $get_chats_request, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ChatsApi->getChats: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **get_chats_request** | [**\AppYandexSdk\Model\GetChatsRequest**](../Model/GetChatsRequest.md)| description | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 10] |

### Return type

[**\AppYandexSdk\Model\GetChatsResponse**](../Model/GetChatsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `sendFileToChat()`

```php
sendFileToChat($business_id, $chat_id, $file): \AppYandexSdk\Model\EmptyApiResponse
```

Отправка файла в чат

{% include notitle [access](../../_auto/method_scopes/sendFileToChat.md) %}  Отправляет файл в чат с покупателем.  {% include notitle [limit](../../_auto/method_limits/sendFileToChat.md) %}

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


$apiInstance = new AppYandexSdk\Api\ChatsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$chat_id = 56; // int | Идентификатор чата.
$file = '/path/to/file.txt'; // \SplFileObject | Содержимое файла. Максимальный размер файла — 5 Мбайт.

try {
    $result = $apiInstance->sendFileToChat($business_id, $chat_id, $file);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ChatsApi->sendFileToChat: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **chat_id** | **int**| Идентификатор чата. | |
| **file** | **\SplFileObject****\SplFileObject**| Содержимое файла. Максимальный размер файла — 5 Мбайт. | |

### Return type

[**\AppYandexSdk\Model\EmptyApiResponse**](../Model/EmptyApiResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `sendMessageToChat()`

```php
sendMessageToChat($business_id, $chat_id, $send_message_to_chat_request): \AppYandexSdk\Model\EmptyApiResponse
```

Отправка сообщения в чат

{% include notitle [access](../../_auto/method_scopes/sendMessageToChat.md) %}  Отправляет сообщение в чат с покупателем.  {% include notitle [limit](../../_auto/method_limits/sendMessageToChat.md) %}

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


$apiInstance = new AppYandexSdk\Api\ChatsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$business_id = 56; // int | Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html)
$chat_id = 56; // int | Идентификатор чата.
$send_message_to_chat_request = new \AppYandexSdk\Model\SendMessageToChatRequest(); // \AppYandexSdk\Model\SendMessageToChatRequest | description

try {
    $result = $apiInstance->sendMessageToChat($business_id, $chat_id, $send_message_to_chat_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ChatsApi->sendMessageToChat: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **business_id** | **int**| Идентификатор кабинета. Чтобы его узнать, воспользуйтесь запросом [GET v2/campaigns](../../reference/campaigns/getCampaigns.md).  ℹ️ [Что такое кабинет и магазин на Маркете](https://yandex.ru/support/marketplace/account/introduction.html) | |
| **chat_id** | **int**| Идентификатор чата. | |
| **send_message_to_chat_request** | [**\AppYandexSdk\Model\SendMessageToChatRequest**](../Model/SendMessageToChatRequest.md)| description | |

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
