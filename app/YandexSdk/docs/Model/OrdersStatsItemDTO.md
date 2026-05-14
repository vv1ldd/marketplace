# OrdersStatsItemDTO

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**offer_name** | **string** | Название товара. | [optional]
**market_sku** | **int** | Идентификатор карточки товара на Маркете. | [optional]
**shop_sku** | **string** | Ваш SKU — идентификатор товара в вашей системе.  Правила использования SKU:  * У каждого товара SKU должен быть свой.  * Уже заданный SKU нельзя освободить и использовать заново для другого товара. Каждый товар должен получать новый идентификатор, до того никогда не использовавшийся в вашем каталоге.  SKU товара можно изменить в кабинете продавца на Маркете. О том, как это сделать, читайте [в Справке Маркета для продавцов](https://yandex.ru/support2/marketplace/ru/assortment/operations/edit-sku).  {% note warning %}  Пробельные символы в начале и конце значения автоматически удаляются. Например, &#x60;\&quot;  SKU123  \&quot;&#x60; и &#x60;\&quot;SKU123\&quot;&#x60; будут обработаны как одинаковые значения.  {% endnote %}  [Что такое SKU и как его назначать](https://yandex.ru/support/marketplace/assortment/add/index.html#fields) | [optional]
**count** | **int** | Количество единиц товара с учетом удаленных единиц.  Если из заказа удалены все единицы товара, он попадет только в список &#x60;initialItems&#x60;. | [optional]
**prices** | [**\AppYandexSdk\Model\OrdersStatsPriceDTO[]**](OrdersStatsPriceDTO.md) | Цена или скидки на товар. | [optional]
**warehouse** | [**\AppYandexSdk\Model\OrdersStatsWarehouseDTO**](OrdersStatsWarehouseDTO.md) |  | [optional]
**details** | [**\AppYandexSdk\Model\OrdersStatsDetailsDTO[]**](OrdersStatsDetailsDTO.md) | Информация об удалении товара из заказа. | [optional]
**cis_list** | **string[]** | Список кодов идентификации товара в системе [«Честный ЗНАК»](https://честныйзнак.рф/) или [«ASL BELGISI»](https://aslbelgisi.uz) (для продавцов :no-translate[Market Yandex Go]). | [optional]
**initial_count** | **int** | Первоначальное количество единиц товара. | [optional]
**bid_fee** | **int** | Списанная ставка ближайшего конкурента.  Указывается в процентах от стоимости товара и умножается на 100. Например, ставка 5% обозначается как 500. | [optional]
**cofinance_threshold** | **float** | Порог для скидок с Маркетом на момент оформления заказа. [Что это такое?](https://yandex.ru/support/marketplace/marketing/smart-pricing.html#sponsored-discounts)  Точность — два знака после запятой. | [optional]
**cofinance_value** | **float** | Скидка с Маркетом. [Что это такое?](https://yandex.ru/support/marketplace/marketing/smart-pricing.html#sponsored-discounts)  Точность — два знака после запятой. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
