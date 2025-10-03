<?php
/**
 * Currency Helper Class
 * Handles currency formatting and conversion across the system
 */

class Currency {
    private $config;
    private $settings;
    
    public function __construct($systemConfig) {
        $this->config = $systemConfig;
        $this->settings = $this->config->getPaymentSettings();
    }
    
    /**
     * Format amount with currency
     */
    public function format($amount, $currency = null) {
        if ($currency === null) {
            $currency = $this->settings['currency'];
        }
        
        $symbol = $this->getCurrencySymbol($currency);
        $position = $this->settings['currency_position'];
        $decimalPlaces = (int)$this->settings['decimal_places'];
        $thousandSep = $this->settings['thousand_separator'];
        $decimalSep = $this->settings['decimal_separator'];
        
        // Format the number
        $formattedAmount = number_format($amount, $decimalPlaces, $decimalSep, $thousandSep);
        
        // Add currency symbol
        if ($position === 'before') {
            return $symbol . $formattedAmount;
        } else {
            return $formattedAmount . ' ' . $symbol;
        }
    }
    
    /**
     * Get currency symbol
     */
    public function getCurrencySymbol($currency = null) {
        if ($currency === null) {
            $currency = $this->settings['currency'];
        }
        
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'NGN' => '₦',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => '₹',
            'BRL' => 'R$',
            'RUB' => '₽',
            'KRW' => '₩',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'NZD' => 'NZ$',
            'MXN' => 'MX$',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr'
        ];
        
        return $symbols[$currency] ?? $currency;
    }
    
    /**
     * Convert amount between currencies
     */
    public function convert($amount, $fromCurrency, $toCurrency) {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        
        // Get exchange rate
        $rate = $this->getExchangeRate($fromCurrency, $toCurrency);
        
        return $amount * $rate;
    }
    
    /**
     * Get exchange rate between currencies
     */
    public function getExchangeRate($fromCurrency, $toCurrency) {
        // In a real system, this would fetch from an exchange rate API
        // For now, we'll use static rates
        $rates = [
            'USD' => [
                'EUR' => 0.85,
                'GBP' => 0.73,
                'NGN' => 410.0,
                'JPY' => 110.0,
                'CAD' => 1.25,
                'AUD' => 1.35
            ],
            'EUR' => [
                'USD' => 1.18,
                'GBP' => 0.86,
                'NGN' => 480.0,
                'JPY' => 130.0
            ],
            'GBP' => [
                'USD' => 1.37,
                'EUR' => 1.16,
                'NGN' => 560.0,
                'JPY' => 150.0
            ],
            'NGN' => [
                'USD' => 0.0024,
                'EUR' => 0.0021,
                'GBP' => 0.0018,
                'JPY' => 0.27
            ]
        ];
        
        if (isset($rates[$fromCurrency][$toCurrency])) {
            return $rates[$fromCurrency][$toCurrency];
        }
        
        // If direct rate not available, try reverse
        if (isset($rates[$toCurrency][$fromCurrency])) {
            return 1 / $rates[$toCurrency][$fromCurrency];
        }
        
        // Default to 1 if no rate found
        return 1;
    }
    
    /**
     * Get available currencies
     */
    public function getAvailableCurrencies() {
        $currencySettings = $this->config->getCurrencySettings();
        $availableCurrencies = explode(',', $currencySettings['available_currencies']);
        
        $currencies = [];
        foreach ($availableCurrencies as $currency) {
            $currency = trim($currency);
            $currencies[$currency] = [
                'code' => $currency,
                'name' => $this->getCurrencyName($currency),
                'symbol' => $this->getCurrencySymbol($currency)
            ];
        }
        
        return $currencies;
    }
    
    /**
     * Get currency name
     */
    public function getCurrencyName($currency) {
        $names = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'NGN' => 'Nigerian Naira',
            'JPY' => 'Japanese Yen',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'CHF' => 'Swiss Franc',
            'CNY' => 'Chinese Yuan',
            'INR' => 'Indian Rupee',
            'BRL' => 'Brazilian Real',
            'RUB' => 'Russian Ruble',
            'KRW' => 'South Korean Won',
            'SGD' => 'Singapore Dollar',
            'HKD' => 'Hong Kong Dollar',
            'NZD' => 'New Zealand Dollar',
            'MXN' => 'Mexican Peso',
            'SEK' => 'Swedish Krona',
            'NOK' => 'Norwegian Krone',
            'DKK' => 'Danish Krone'
        ];
        
        return $names[$currency] ?? $currency;
    }
    
    /**
     * Parse amount from formatted string
     */
    public function parseAmount($formattedAmount) {
        $thousandSep = $this->settings['thousand_separator'];
        $decimalSep = $this->settings['decimal_separator'];
        
        // Remove currency symbol and extra spaces
        $cleanAmount = preg_replace('/[^\d' . preg_quote($thousandSep, '/') . preg_quote($decimalSep, '/') . ']/', '', $formattedAmount);
        
        // Replace thousand separator
        $cleanAmount = str_replace($thousandSep, '', $cleanAmount);
        
        // Replace decimal separator with dot
        $cleanAmount = str_replace($decimalSep, '.', $cleanAmount);
        
        return (float)$cleanAmount;
    }
    
    /**
     * Get current currency settings
     */
    public function getSettings() {
        return $this->settings;
    }
    
    /**
     * Update currency settings
     */
    public function updateSettings($newSettings) {
        return $this->config->savePaymentSettings($newSettings);
    }
}
?>
