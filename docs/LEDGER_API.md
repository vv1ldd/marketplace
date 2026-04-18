# Ledger API (read-only)

Тот же механизм авторизации, что у **Redeem API**: заголовок `Authorization: Bearer <token>` — строка `api_applications.token` (активная запись).

Префикс маршрутов Laravel API: по умолчанию `/api` (см. `bootstrap/app.php`).

## `GET /api/ledger/catalog-map`

Параметры query:

| Параметр        | Описание |
|-----------------|----------|
| `updated_since` | опционально, ISO-дата — только строки с `updated_at >=` |
| `limit`         | по умолчанию 500, максимум 2000 |

Ответ: `items[]` с полями `sku_marketplace`, `sku_proxy_key`, `sku_supplier`, `sku_map_version`, тип каталога и т.д. (см. `LedgerApiController::catalogMap`).

## `GET /api/ledger/redeem-events`

Параметры query:

| Параметр   | Описание |
|------------|----------|
| `from`     | опционально, дата начала периода по `updated_at` |
| `to`       | опционально, конец периода |
| `page`     | страница пагинации |
| `per_page` | по умолчанию 50, макс. 100 |

Ответ: только строки, где `is_redeemed` или `is_activated`; маскируется `redeem_code_masked`; из `client_info` отдаются только `email`, `first_name`, `last_name`, `phone`, `type_id` (без опций PSN / паролей).
