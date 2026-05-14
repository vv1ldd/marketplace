<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CentralBankService
{
    /**
     * Fetch official rates from the Central Bank of Russia (CBR).
     * Returns an array where keys are ISO codes (USD, EUR, TRY) and values are cost in RUB.
     */
    public function getCbrRates(): array
    {
        try {
            // Using the fast JSON mirror of CBR's official daily XML
            $response = Http::timeout(10)->get('https://www.cbr-xml-daily.ru/daily_json.js');
            
            if ($response->failed()) {
                Log::warning('CBR API failed to respond.');
                return [];
            }

            $data = $response->json('Valute') ?? [];
            $rates = [];

            foreach ($data as $code => $currencyData) {
                // CBR sometimes returns cost per 10 or 100 units (Nominal)
                $nominal = (float)($currencyData['Nominal'] ?? 1);
                $value = (float)($currencyData['Value'] ?? 0);
                
                if ($nominal > 0 && $value > 0) {
                    $rates[$code] = $value / $nominal; // Exact cost of 1 unit in RUB
                }
            }

            return $rates;
        } catch (\Exception $e) {
            Log::error('CBR Parse Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch official rates from the European Central Bank (ECB).
     * Returns an array where keys are ISO codes and values are cost in USD (calculated via EUR/USD).
     */
    public function getEcbRates(): array
    {
        try {
            // ECB XML structure
            $response = Http::timeout(10)->get('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
            
            if ($response->failed()) {
                Log::warning('ECB API failed to respond.');
                return [];
            }

            $xml = simplexml_load_string($response->body());
            if (!$xml) return [];

            $rates = [];
            $eurToUsd = 1.0;

            // Parse ECB XML namespace
            foreach ($xml->Cube->Cube->Cube as $node) {
                $code = (string)$node['currency'];
                $rateToEur = (float)$node['rate']; // How much of $code you get for 1 EUR
                
                if ($code === 'USD') {
                    $eurToUsd = $rateToEur;
                }
                
                if ($rateToEur > 0) {
                    $rates[$code] = $rateToEur;
                }
            }

            // Convert everything to a base of USD so it matches our system logic
            $usdBasedRates = [];
            // Add EUR explicitly since it's the base of ECB
            $usdBasedRates['EUR'] = 1 / $eurToUsd; 

            foreach ($rates as $code => $rateToEur) {
                if ($code !== 'USD') {
                    // How much of $code you get for 1 USD
                    $usdBasedRates[$code] = $rateToEur / $eurToUsd;
                }
            }

            return $usdBasedRates;
        } catch (\Exception $e) {
            Log::error('ECB Parse Error: ' . $e->getMessage());
            return [];
        }
    }
}
