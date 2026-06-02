<?php

namespace EzPin;

use EzPin\DTO\OrderRequest;
use EzPin\DTO\PhysicalCardActivationRequest;
use EzPin\DTO\RetailerOrderRequest;
use EzPin\Exception\EzPinException;
use GuzzleHttp\Client;

class EzPinClient
{
    private Client $http;

    private ?string $token = null;

    private string $baseUrl = 'https://api.ezpaypin.com/vendors/v2/';

    public function __construct(
        private readonly string $clientId,
        private readonly string $secretKey,
        ?Client $http = null,
    ) {
        $this->http = $http ?? new Client(['timeout' => 30]);
    }

    public function authenticate(): string
    {
        $response = $this->http->post($this->baseUrl.'auth/token/', [
            'json' => [
                'client_id' => $this->clientId,
                'secret_key' => $this->secretKey,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (! isset($data['access'])) {
            throw new EzPinException('Access token not returned');
        }

        return $this->token = 'Bearer '.$data['access'];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $body = []): array
    {
        if (! $this->token) {
            $this->authenticate();
        }

        $response = $this->http->request($method, $this->baseUrl.ltrim($uri, '/'), [
            'headers' => [
                'Authorization' => $this->token,
                'Content-Type' => 'application/json',
            ],
            ...($body ? ['json' => $body] : []),
        ]);

        $code = $response->getStatusCode();
        $data = json_decode($response->getBody()->getContents(), true);

        if ($code !== 200) {
            throw new EzPinException($data['message'] ?? "HTTP {$code} error");
        }

        return $data;
    }

    public function getBalance(): array
    {
        return $this->request('GET', 'balance/');
    }

    public function getCatalog(): array
    {
        return $this->request('GET', 'catalogs/');
    }

    public function checkAvailability(string $sku, ?int $itemCount = null, ?float $price = null): array
    {
        $queryParams = [];
        if ($itemCount !== null) {
            $queryParams['item_count'] = $itemCount;
        }
        if ($price !== null) {
            $queryParams['price'] = $price;
        }

        $query = $queryParams ? '?'.http_build_query($queryParams) : '';

        return $this->request('GET', "catalogs/{$sku}/availability/{$query}");
    }

    public function createOrder(OrderRequest $order): array
    {
        return $this->request('POST', 'orders/', $order->toArray());
    }

    public function getOrderDetails(string $referenceCode): array
    {
        return $this->request('GET', "orders/{$referenceCode}/");
    }

    public function getCards(string $referenceCode): array
    {
        return $this->request('GET', "orders/{$referenceCode}/cards/");
    }

    public function getOrderHistory(string $startDate, string $endDate, ?int $limit = null, ?int $offset = null): array
    {
        $queryParams = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        if ($limit !== null) {
            $queryParams['limit'] = $limit;
        }
        if ($offset !== null) {
            $queryParams['offset'] = $offset;
        }

        return $this->request('GET', 'orders/?'.http_build_query($queryParams));
    }

    public function getRetailerProducts(): array
    {
        return $this->request('GET', 'retailer_products/');
    }

    public function createOrderRetailer(RetailerOrderRequest $order): array
    {
        return $this->request('POST', 'retailer_order/', $order->toArray());
    }

    public function checkRetailerAvailability(string $productCode, int $itemCount): array
    {
        return $this->request('GET', "retailer_product_availability/?item_count={$itemCount}&product_code={$productCode}");
    }

    public function exchangeRates(): array
    {
        return $this->request('GET', 'exchange_rates/');
    }

    public function getPhysicalCardHistory(string $startDate, string $endDate, ?int $limit = null, ?int $offset = null): array
    {
        $queryParams = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        if ($limit !== null) {
            $queryParams['limit'] = $limit;
        }
        if ($offset !== null) {
            $queryParams['offset'] = $offset;
        }

        return $this->request('GET', 'cards/?'.http_build_query($queryParams));
    }

    public function checkPhysicalCard(string $barcode): array
    {
        return $this->request('GET', 'cards/check/?barcode='.urlencode($barcode));
    }

    public function activatePhysicalCard(PhysicalCardActivationRequest $request): array
    {
        return $this->request('POST', 'cards/activate/', $request->toArray());
    }

    public function getPhysicalCardStatus(string $referenceCode): array
    {
        return $this->request('GET', "cards/{$referenceCode}/");
    }

    public function setNotificationConfig(string $endpoint, string $confidentialKey): array
    {
        return $this->request('POST', 'notification_config/', [
            'endpoint' => $endpoint,
            'confidential_key' => $confidentialKey,
        ]);
    }

    public function getNotificationConfig(): array
    {
        return $this->request('GET', 'notification_config/');
    }
}
