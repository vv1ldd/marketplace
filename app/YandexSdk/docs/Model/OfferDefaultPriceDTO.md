# OfferDefaultPriceDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**minimum_for_bestseller** | **float** | Минимальная цена товара для попадания в акцию «Бестселлеры Маркета». Подробнее об этом способе участия читайте [в Справке Маркета для продавцов](https://yandex.ru/support/marketplace/ru/marketing/promos/market/bestsellers#minimum).  Передается в методе [POST v2/businesses/{businessId}/offer-prices/updates](../../reference/business-assortment/updateBusinessPrices.md). | [optional]
**excluded_from_bestsellers** | **bool** | Признак того, что товар не попадает в акцию «Бестселлеры Маркета». Подробнее об акции читайте [в Справке Маркета для продавцов](https://yandex.ru/support2/marketplace/ru/marketing/promos/market/bestsellers).  Если значение &#x60;true&#x60;, в методе [POST v2/businesses/{businessId}/offer-prices/updates](../../reference/business-assortment/updateBusinessPrices.md) параметр &#x60;minimumForBestseller&#x60; игнорируется. | [optional]
**value** | **float** | Цена товара. | [optional]
**currency_id** | [**\AppYandexSdk\Model\CurrencyType**](CurrencyType.md) |  | [optional]
**discount_base** | **float** | Зачеркнутая цена.  Число должно быть целым. Вы можете указать цену со скидкой от 5 до 99%.  Передавайте этот параметр при каждом обновлении цены, если предоставляете скидку на товар. | [optional]
**updated_at** | **\DateTime** | Время последнего обновления. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
