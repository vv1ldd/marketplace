# UpdateBusinessPricesDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**value** | **float** | Цена товара. |
**currency_id** | [**\AppYandexSdk\Model\CurrencyType**](CurrencyType.md) |  |
**discount_base** | **float** | Зачеркнутая цена.  Число должно быть целым. Вы можете указать цену со скидкой от 5 до 99%.  Передавайте этот параметр при каждом обновлении цены, если предоставляете скидку на товар. | [optional]
**minimum_for_bestseller** | **float** | Минимальная цена товара для попадания в акцию «Бестселлеры Маркета». Подробнее об этом способе участия читайте [в Справке Маркета для продавцов](https://yandex.ru/support/marketplace/ru/marketing/promos/market/bestsellers#minimum).  При передаче цены ориентируйтесь на значение параметра &#x60;maxPromoPrice&#x60; (максимально возможная цена для участия в акции) в методе [POST v2/businesses/{businessId}/promos/offers](../../reference/promos/getPromoOffers.md).  Товар не попадет в акцию с помощью этого способа, если:  * Не передать этот параметр. Удалится значение, которое вы указали ранее. * В методе [POST v2/businesses/{businessId}/offer-prices](../../reference/prices/getDefaultPrices.md) для этого товара возвращается параметр &#x60;excludedFromBestsellers&#x60; со значением &#x60;true&#x60;.    Но товар по-прежнему сможет попасть в акцию через [автоматическое участие](*auto) или [ручное добавление](*updatePromoOffers). | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
