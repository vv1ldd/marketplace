# AppYandexSdk\ShipmentsApi



All URIs are relative to https://api.partner.market.yandex.ru, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**confirmShipment()**](ShipmentsApi.md#confirmShipment) | **POST** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/confirm | Подтверждение отгрузки |
| [**downloadShipmentAct()**](ShipmentsApi.md#downloadShipmentAct) | **GET** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/act | Получение акта приема-передачи |
| [**downloadShipmentDiscrepancyAct()**](ShipmentsApi.md#downloadShipmentDiscrepancyAct) | **GET** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/discrepancy-act | Получение акта расхождений |
| [**downloadShipmentInboundAct()**](ShipmentsApi.md#downloadShipmentInboundAct) | **GET** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/inbound-act | Получение фактического акта приема-передачи |
| [**downloadShipmentPalletLabels()**](ShipmentsApi.md#downloadShipmentPalletLabels) | **GET** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/pallet/labels | Ярлыки для доверительной приемки |
| [**downloadShipmentReceptionTransferAct()**](ShipmentsApi.md#downloadShipmentReceptionTransferAct) | **GET** /v2/campaigns/{campaignId}/shipments/reception-transfer-act | Подтверждение ближайшей отгрузки и получение акта приема-передачи для нее |
| [**downloadShipmentTransportationWaybill()**](ShipmentsApi.md#downloadShipmentTransportationWaybill) | **GET** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/transportation-waybill | Получение транспортной накладной |
| [**getShipment()**](ShipmentsApi.md#getShipment) | **GET** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId} | Получение информации об одной отгрузке |
| [**getShipmentOrdersInfo()**](ShipmentsApi.md#getShipmentOrdersInfo) | **GET** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/orders/info | Получение информации о возможности печати ярлыков |
| [**searchShipments()**](ShipmentsApi.md#searchShipments) | **PUT** /v2/campaigns/{campaignId}/first-mile/shipments | Получение информации о нескольких отгрузках |
| [**setShipmentPalletsCount()**](ShipmentsApi.md#setShipmentPalletsCount) | **PUT** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/pallets | Передача количества упаковок для доверительной приемки |
| [**transferOrdersFromShipment()**](ShipmentsApi.md#transferOrdersFromShipment) | **POST** /v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/orders/transfer | Перенос заказов в следующую отгрузку |


## `confirmShipment()`

```php
confirmShipment($campaign_id, $shipment_id, $confirm_shipment_request): \AppYandexSdk\Model\EmptyApiResponse
```

Подтверждение отгрузки

{% include notitle [access](../../_auto/method_scopes/confirmShipment.md) %}  Подтверждает отгрузку товаров в сортировочный центр или пункт приема заказов. Действие доступно только после того, как отгрузка сформирована.  График отгрузок настраивается отдельно для каждого склада в личном кабинете и недоступен через API. Проверить возможность подтверждения отгрузки можно с помощью метода [GET v2/campaigns/{campaignId}/shipments/{shipmentId}](../../reference/shipments/getShipment): среди доступных действий `availableActions` должно быть действие `CONFIRM`. До наступления времени подтверждения метод вернет код `400` и ошибку :no-translate[\"Cutoff time for shipments has not been reached yet\"].  Подробнее о приеме заказов и расписании отгрузок читайте [в Справке Маркета для продавцов](https://yandex.ru/support/marketplace/ru/orders/fbs/settings/shipment#schedule).  {% include notitle [limit](../../_auto/method_limits/confirmShipment.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.
$confirm_shipment_request = new \AppYandexSdk\Model\ConfirmShipmentRequest(); // \AppYandexSdk\Model\ConfirmShipmentRequest

try {
    $result = $apiInstance->confirmShipment($campaign_id, $shipment_id, $confirm_shipment_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->confirmShipment: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |
| **confirm_shipment_request** | [**\AppYandexSdk\Model\ConfirmShipmentRequest**](../Model/ConfirmShipmentRequest.md)|  | [optional] |

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

## `downloadShipmentAct()`

```php
downloadShipmentAct($campaign_id, $shipment_id): \SplFileObject
```

Получение акта приема-передачи

{% include notitle [access](../../_auto/method_scopes/downloadShipmentAct.md) %}  {% note warning \"Экспресс‑доставка\" %}  Если ваш магазин подключен к экспресс‑доставке и вы отгружаете заказы курьерам [Яндекс Go](https://go.yandex/), подготавливать акт приема‑передачи не нужно.  {% endnote %}  Запрос формирует акт приема-передачи заказов, входящих в отгрузку, и возвращает акт в формате PDF. В акте содержатся собранные и готовые к отправке заказы.  Метод доступен только для подтвержденной отгрузки. Сначала подтвердите отгрузку запросом [POST v2/campaigns/{campaignId}/shipments/{shipmentId}/confirm](../../reference/shipments/confirmShipment.md), затем вызовите этот метод.  При формировании акта Маркет автоматически находит и подставляет в шаблон следующие данные:  {% cut \"Данные, из которых Маркет формирует акт\" %}  #| || **Данные в акте**                                         | **Описание**                                                                                                                                                                                                                                                         || || Дата                                                      | Дата запроса.                                                                                                                                                                                                                                                        || || Отправитель                                               | Название вашего юридического лица, указанное в кабинете продавца на Маркете.                                                                                                                                                                                         || || Исполнитель                                               | Название юридического лица сортировочного центра или службы доставки.                                                                                                                                                                                                || || № отправления в системе заказчика                         | Внешний идентификатор заказа продавца, который можно передать запросом [POST v2/campaigns/{campaignId}/orders/{orderId}/external-id](../../reference/orders/updateExternalOrderId.md).                                                                            || || № отправления в системе исполнителя (субподрядчика)       |   Идентификатор заказа на Маркете, как в выходных данных запроса:    * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md).||  || Объявленная ценность                                      |   Общая сумма заказа без учета стоимости доставки, как в выходных данных запроса:    * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md).||  || Вес                                                       |   Масса брутто грузового места (суммарная масса упаковки и содержимого), как в выходных данных запроса:    * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md).||  || Количество мест                                           |   Количество грузовых мест в заказе, как в выходных данных запроса:    * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md). || |#  {% endcut %}  В распечатанном акте укажите отправителя и исполнителя. Они должны подписать акт и указать фамилию и инициалы рядом с подписью. При необходимости также заполните реквизиты доверенности.  {% include notitle [limit](../../_auto/method_limits/downloadShipmentAct.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.

try {
    $result = $apiInstance->downloadShipmentAct($campaign_id, $shipment_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->downloadShipmentAct: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |

### Return type

**\SplFileObject**

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/pdf`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `downloadShipmentDiscrepancyAct()`

```php
downloadShipmentDiscrepancyAct($campaign_id, $shipment_id): \SplFileObject
```

Получение акта расхождений

{% include notitle [access](../../_auto/method_scopes/downloadShipmentDiscrepancyAct.md) %}  Возвращает акт расхождений для заданной отгрузки.  {% include notitle [limit](../../_auto/method_limits/downloadShipmentDiscrepancyAct.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.

try {
    $result = $apiInstance->downloadShipmentDiscrepancyAct($campaign_id, $shipment_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->downloadShipmentDiscrepancyAct: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |

### Return type

**\SplFileObject**

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/vnd.ms-excel`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `downloadShipmentInboundAct()`

```php
downloadShipmentInboundAct($campaign_id, $shipment_id): \SplFileObject
```

Получение фактического акта приема-передачи

{% include notitle [access](../../_auto/method_scopes/downloadShipmentInboundAct.md) %}  Возвращает фактический акт приема-передачи для заданной отгрузки.  Такой акт становится доступен спустя несколько часов после завершения отгрузки. Он может понадобиться, если после отгрузки обнаружатся расхождения.  {% include notitle [limit](../../_auto/method_limits/downloadShipmentInboundAct.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.

try {
    $result = $apiInstance->downloadShipmentInboundAct($campaign_id, $shipment_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->downloadShipmentInboundAct: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |

### Return type

**\SplFileObject**

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/pdf`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `downloadShipmentPalletLabels()`

```php
downloadShipmentPalletLabels($campaign_id, $shipment_id, $format): \SplFileObject
```

Ярлыки для доверительной приемки

{% include notitle [access](../../_auto/method_scopes/downloadShipmentPalletLabels.md) %}  PDF-файл с ярлыками на каждую упаковку в отгрузке для доверительной приемки. Подробнее о таком виде приемки читайте в [Справке Маркета для продавцов](https://yandex.ru/support/marketplace/orders/fbs/process.html#acceptance).  Распечатайте по несколько копий каждого ярлыка: на одну упаковку нужно наклеить минимум 2 ярлыка с разных сторон.  Количество упаковок в отгрузке передается в методе [PUT v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/pallets](../../reference/shipments/setShipmentPalletsCount.md).  {% include notitle [limit](../../_auto/method_limits/downloadShipmentPalletLabels.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.
$format = new \AppYandexSdk\Model\\AppYandexSdk\Model\ShipmentPalletLabelPageFormatType(); // \AppYandexSdk\Model\ShipmentPalletLabelPageFormatType | Формат страниц PDF-файла с ярлыками:  * `A4` — по 16 ярлыков на странице. * `A8` — по одному ярлыку на странице.

try {
    $result = $apiInstance->downloadShipmentPalletLabels($campaign_id, $shipment_id, $format);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->downloadShipmentPalletLabels: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |
| **format** | [**\AppYandexSdk\Model\ShipmentPalletLabelPageFormatType**](../Model/.md)| Формат страниц PDF-файла с ярлыками:  * &#x60;A4&#x60; — по 16 ярлыков на странице. * &#x60;A8&#x60; — по одному ярлыку на странице. | [optional] |

### Return type

**\SplFileObject**

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/pdf`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `downloadShipmentReceptionTransferAct()`

```php
downloadShipmentReceptionTransferAct($campaign_id, $warehouse_id, $signatory): \SplFileObject
```

Подтверждение ближайшей отгрузки и получение акта приема-передачи для нее

{% include notitle [access](../../_auto/method_scopes/downloadShipmentReceptionTransferAct.md) %}  Запрос подтверждает ближайшую отгрузку и возвращает акт приема-передачи в формате PDF.  Подтверждение отгрузки доступно только после того, как она сформирована, иначе метод вернет код `400` и ошибку :no-translate[\"Closest shipment for reception transfer act generation not found.\"].  Подробнее о приеме заказов и расписании отгрузок читайте [в Справке Маркета для продавцов](https://yandex.ru/support/marketplace/ru/orders/fbs/settings/shipment#schedule).  {% note warning \"Экспресс‑доставка\" %}  Если ваш магазин подключен к экспресс‑доставке и вы отгружаете заказы курьерам [Яндекс Go](https://go.yandex/), подготавливать акт приема‑передачи не нужно.  {% endnote %}  В акт входят собранные и готовые к отправке заказы, которые отгружаются в сортировочный центр или пункт приема либо передаются курьерам Маркета.  При формировании акта Маркет автоматически находит и подставляет в шаблон следующие данные:  {% cut \"Данные, из которых Маркет формирует акт\" %}  #| || **Данные в акте**                                  | **Описание**                                                                                                                                                                                                                                                         || || Отправитель                                      | Название вашего юридического лица, указанное в кабинете продавца на Маркете.                                                                                                                                                                                         || || Исполнитель                                         | Название юридического лица сортировочного центра или службы доставки.                                                                                                                                                                                                || || № отправления в системе заказчика                   | Внешний идентификатор заказа продавца, который можно передать запросом [POST v2/campaigns/{campaignId}/orders/{orderId}/external-id](../../reference/orders/updateExternalOrderId.md).                                                                            || || № отправления в системе исполнителя (субподрядчика) |   Идентификатор заказа на Маркете, как в выходных данных запроса:    * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md).||  || Объявленная ценность                                |   Общая сумма заказа без учета стоимости доставки, как в выходных данных запроса:    * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md).||  || Стоимость всех товаров в заказе                     | Стоимость всех заказанных товаров.                                                                                                                                                                                                                                   ||  || Вес                                                 |   Масса брутто грузового места (суммарная масса упаковки и содержимого), как в выходных данных запроса:    * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md).||  || Количество мест                                     |   Количество грузовых мест в заказе, как в выходных данных запроса:    * [POST v1/businesses/{businessId}/orders](../../reference/orders/getBusinessOrders.md).|| |#  {% endcut %}  {% note info \"Электронная подпись акта\" %}  Если вы указываете параметр `signatory`, акт приема-передачи подписывается электронной подписью и становится электронным документом. В этом случае печатать и подписывать акт вручную не требуется — он уже имеет юридическую силу в электронном виде.  Если параметр `signatory` не указан, акт нужно распечатать. В распечатанном акте укажите отправителя и исполнителя. Они должны подписать акт и указать фамилию и инициалы рядом с подписью. При необходимости также заполните реквизиты доверенности.  Подробнее о работе с актами приема-передачи читайте [в Справке Маркета для продавцов](https://yandex.ru/support/marketplace/ru/orders/fbs/process#act).  {% endnote %}  {% include notitle [limit](../../_auto/method_limits/downloadShipmentReceptionTransferAct.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$warehouse_id = 123123; // int | Идентификатор склада.
$signatory = 'signatory_example'; // string | Логин пользователя в Яндекс ID, от имени которого будет подписываться электронный акт приема-передачи.  Указывается без `@yandex.ru`.  {% note info \"Электронная подпись\" %}  Если вы указываете параметр `signatory`, акт приема-передачи подписывается электронной подписью и становится электронным документом. В этом случае печатать и подписывать акт вручную не требуется — он уже имеет юридическую силу в электронном виде.  Подробнее о работе с актами приема-передачи читайте [в Справке Маркета для продавцов](https://yandex.ru/support/marketplace/ru/orders/fbs/process#act).  {% endnote %}  Где найти логин:  * на странице [Яндекс ID](https://id.yandex.ru); * в [кабинете продавца на Маркете](https://partner.market.yandex.ru/):  * в правом верхнем углу под иконкой пользователя;   * на странице **Настройки** → **Сотрудники и доступы**.

try {
    $result = $apiInstance->downloadShipmentReceptionTransferAct($campaign_id, $warehouse_id, $signatory);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->downloadShipmentReceptionTransferAct: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **warehouse_id** | **int**| Идентификатор склада. | [optional] |
| **signatory** | **string**| Логин пользователя в Яндекс ID, от имени которого будет подписываться электронный акт приема-передачи.  Указывается без &#x60;@yandex.ru&#x60;.  {% note info \&quot;Электронная подпись\&quot; %}  Если вы указываете параметр &#x60;signatory&#x60;, акт приема-передачи подписывается электронной подписью и становится электронным документом. В этом случае печатать и подписывать акт вручную не требуется — он уже имеет юридическую силу в электронном виде.  Подробнее о работе с актами приема-передачи читайте [в Справке Маркета для продавцов](https://yandex.ru/support/marketplace/ru/orders/fbs/process#act).  {% endnote %}  Где найти логин:  * на странице [Яндекс ID](https://id.yandex.ru); * в [кабинете продавца на Маркете](https://partner.market.yandex.ru/):  * в правом верхнем углу под иконкой пользователя;   * на странице **Настройки** → **Сотрудники и доступы**. | [optional] |

### Return type

**\SplFileObject**

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/pdf`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `downloadShipmentTransportationWaybill()`

```php
downloadShipmentTransportationWaybill($campaign_id, $shipment_id): \SplFileObject
```

Получение транспортной накладной

{% include notitle [access](../../_auto/method_scopes/downloadShipmentTransportationWaybill.md) %}  Возвращает транспортную накладную для заданной отгрузки, если Маркет забирает товары с вашего склада. Подробнее о таком способе отгрузки читайте [в Справке Маркета для продавцов](https://yandex.ru/support/marketplace/ru/orders/fbs/settings/shipment#at-your-warehouse).  Накладная не возвращается, если вы привозите товары в ПВЗ или сортировочный центр.  {% include notitle [limit](../../_auto/method_limits/downloadShipmentTransportationWaybill.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.

try {
    $result = $apiInstance->downloadShipmentTransportationWaybill($campaign_id, $shipment_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->downloadShipmentTransportationWaybill: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |

### Return type

**\SplFileObject**

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/vnd.ms-excel`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getShipment()`

```php
getShipment($campaign_id, $shipment_id, $cancelled_orders): \AppYandexSdk\Model\GetShipmentResponse
```

Получение информации об одной отгрузке

{% include notitle [access](../../_auto/method_scopes/getShipment.md) %}  Возвращает информацию об отгрузке по ее идентификатору.  {% include notitle [limit](../../_auto/method_limits/getShipment.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.
$cancelled_orders = true; // bool | Возвращать ли отмененные заказы.  Значение по умолчанию: `true`. Если возвращать отмененные заказы не нужно, передайте значение `false`.

try {
    $result = $apiInstance->getShipment($campaign_id, $shipment_id, $cancelled_orders);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->getShipment: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |
| **cancelled_orders** | **bool**| Возвращать ли отмененные заказы.  Значение по умолчанию: &#x60;true&#x60;. Если возвращать отмененные заказы не нужно, передайте значение &#x60;false&#x60;. | [optional] [default to true] |

### Return type

[**\AppYandexSdk\Model\GetShipmentResponse**](../Model/GetShipmentResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getShipmentOrdersInfo()`

```php
getShipmentOrdersInfo($campaign_id, $shipment_id): \AppYandexSdk\Model\GetShipmentOrdersInfoResponse
```

Получение информации о возможности печати ярлыков

{% include notitle [access](../../_auto/method_scopes/getShipmentOrdersInfo.md) %}  Возвращает информацию о возможности печати ярлыков-наклеек для заказов в отгрузке.  {% include notitle [limit](../../_auto/method_limits/getShipmentOrdersInfo.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.

try {
    $result = $apiInstance->getShipmentOrdersInfo($campaign_id, $shipment_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->getShipmentOrdersInfo: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |

### Return type

[**\AppYandexSdk\Model\GetShipmentOrdersInfoResponse**](../Model/GetShipmentOrdersInfoResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `searchShipments()`

```php
searchShipments($campaign_id, $search_shipments_request, $page_token, $limit): \AppYandexSdk\Model\SearchShipmentsResponse
```

Получение информации о нескольких отгрузках

{% include notitle [access](../../_auto/method_scopes/searchShipments.md) %}  Возвращает информацию об отгрузках по заданным параметрам:  * дате; * статусу; * идентификаторам заказов.  Результаты возвращаются постранично.  {% include notitle [limit](../../_auto/method_limits/searchShipments.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$search_shipments_request = new \AppYandexSdk\Model\SearchShipmentsRequest(); // \AppYandexSdk\Model\SearchShipmentsRequest
$page_token = 'page_token_example'; // string | Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра `nextPageToken`, полученное при последнем запросе.
$limit = 15; // int | {{ limit-param-description }}

try {
    $result = $apiInstance->searchShipments($campaign_id, $search_shipments_request, $page_token, $limit);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->searchShipments: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **search_shipments_request** | [**\AppYandexSdk\Model\SearchShipmentsRequest**](../Model/SearchShipmentsRequest.md)|  | |
| **page_token** | **string**| Идентификатор страницы c результатами.  Если параметр не указан, возвращается первая страница.  Передавайте значение выходного параметра &#x60;nextPageToken&#x60;, полученное при последнем запросе. | [optional] |
| **limit** | **int**| {{ limit-param-description }} | [optional] [default to 15] |

### Return type

[**\AppYandexSdk\Model\SearchShipmentsResponse**](../Model/SearchShipmentsResponse.md)

### Authorization

[ApiKey](../../README.md#ApiKey), [OAuth](../../README.md#OAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `setShipmentPalletsCount()`

```php
setShipmentPalletsCount($campaign_id, $shipment_id, $set_shipment_pallets_count_request): \AppYandexSdk\Model\EmptyApiResponse
```

Передача количества упаковок для доверительной приемки

{% include notitle [access](../../_auto/method_scopes/setShipmentPalletsCount.md) %}  Передает Маркету количество упаковок в отгрузке для доверительной приемки. Подробнее о таком виде приемки читайте в [Справке Маркета для продавцов](https://yandex.ru/support/marketplace/orders/fbs/process.html#acceptance).  {% note info \"Как передавать упаковки\" %}  Передавайте количество упаковок, которые вы везете в отгрузке, а не сумму грузомест по заказам.  **Пример:** в отгрузке 2 заказа, в каждом по 5 грузомест. Если вы везете их в 2 палетах — передайте в запросе `2`, а не `10`.  {% endnote %}  Получить PDF-файл с ярлыками для упаковок можно с помощью метода [GET v2/campaigns/{campaignId}/first-mile/shipments/{shipmentId}/pallet/labels](../../reference/shipments/downloadShipmentPalletLabels.md).  {% include notitle [limit](../../_auto/method_limits/setShipmentPalletsCount.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.
$set_shipment_pallets_count_request = new \AppYandexSdk\Model\SetShipmentPalletsCountRequest(); // \AppYandexSdk\Model\SetShipmentPalletsCountRequest

try {
    $result = $apiInstance->setShipmentPalletsCount($campaign_id, $shipment_id, $set_shipment_pallets_count_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->setShipmentPalletsCount: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |
| **set_shipment_pallets_count_request** | [**\AppYandexSdk\Model\SetShipmentPalletsCountRequest**](../Model/SetShipmentPalletsCountRequest.md)|  | |

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

## `transferOrdersFromShipment()`

```php
transferOrdersFromShipment($campaign_id, $shipment_id, $transfer_orders_from_shipment_request): \AppYandexSdk\Model\EmptyApiResponse
```

Перенос заказов в следующую отгрузку

{% include notitle [access](../../_auto/method_scopes/transferOrdersFromShipment.md) %}  Переносит указанные заказы из указанной отгрузки в следующую отгрузку. [Что такое отгрузка?](https://yandex.ru/support/marketplace/orders/fbs/process.html#ship)  Используйте этот запрос, если не успеваете собрать и упаковать заказы вовремя.  {% note warning \"Такие переносы снижают индекс качества магазина\" %}  Этот запрос предназначен для исключительных случаев. Если вы будете переносить заказы слишком часто, магазин столкнется с ограничениями. [Что за ограничения?](https://yandex.ru/support/marketplace/quality/score/fbs.html)  {% endnote %}  Переносить заказы можно, если до формирования отгрузки осталось больше получаса.  Перенос происходит не мгновенно, а занимает несколько минут.  {% include notitle [limit](../../_auto/method_limits/transferOrdersFromShipment.md) %}

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


$apiInstance = new AppYandexSdk\Api\ShipmentsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$campaign_id = 56; // int | Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями.
$shipment_id = 56; // int | Идентификатор отгрузки.
$transfer_orders_from_shipment_request = new \AppYandexSdk\Model\TransferOrdersFromShipmentRequest(); // \AppYandexSdk\Model\TransferOrdersFromShipmentRequest

try {
    $result = $apiInstance->transferOrdersFromShipment($campaign_id, $shipment_id, $transfer_orders_from_shipment_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ShipmentsApi->transferOrdersFromShipment: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **campaign_id** | **int**| Идентификатор кампании (магазина) — технический идентификатор, который представляет ваш магазин в системе Яндекс Маркета при работе через API. Он однозначно связывается с вашим магазином, но предназначен только для автоматизированного взаимодействия.  Его можно узнать с помощью запроса [GET v2/campaigns](../../reference/campaigns/getCampaigns.md) или найти в кабинете продавца на Маркете. Нажмите на иконку вашего аккаунта → **Настройки** и в меню слева выберите **API и модули**:  * блок **Идентификатор кампании**; * вкладка **Лог запросов** → выпадающий список в блоке **Показывать логи**.  ⚠️ Не путайте его с: - идентификатором магазина, который отображается в личном кабинете продавца; - рекламными кампаниями. | |
| **shipment_id** | **int**| Идентификатор отгрузки. | |
| **transfer_orders_from_shipment_request** | [**\AppYandexSdk\Model\TransferOrdersFromShipmentRequest**](../Model/TransferOrdersFromShipmentRequest.md)|  | |

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
