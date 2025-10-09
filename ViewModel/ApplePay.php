<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\ViewModel;

use CheckoutCom\Magento2\Model\Config\Backend\Source\ConfigApplePayButton;
use Magebit\CheckoutComPayment\Helper\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class ApplePay implements ArgumentInterface
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $config
     * @param UrlInterface $urls
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config,
        private readonly UrlInterface $urls
    ) {
    }

    /**
     * Get Apple Pay Merchant ID
     *
     * @return string
     */
    public function getAppleMerchantID(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/merchant_id',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Apple Pay supported networks
     *
     * @return array
     */
    public function getAppleSupportedNetworks(): array
    {
        $networks = $this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/supported_networks',
            ScopeInterface::SCOPE_STORE
        );

        return explode(',', $networks);
    }

    /**
     * Values always must contain "supports3DS" to enable Touch ID / Face ID verification
     *
     * @return array
     */
    public function getAppleMerchantCapabilities(): array
    {
        $capabilities = $this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/merchant_capabilities',
            ScopeInterface::SCOPE_STORE
        );

        if (!$capabilities) {
            return ['supports3DS'];
        }

        $capabilitiesArray = explode(',', $capabilities);
        array_unshift($capabilitiesArray, 'supports3DS');

        return $capabilitiesArray;
    }

    /**
     * Get Apple Pay button style
     *
     * @return string
     */
    public function getAppleButtonStyle(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/button_style',
            ScopeInterface::SCOPE_STORE
        ) ?? ConfigApplePayButton::BUTTON_BLACK;
    }

    /**
     * Apple Pay button corner radius
     *
     * @return int
     */
    public function getAppleButtonRadius(): int
    {
        return (int)$this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/button_radius',
            ScopeInterface::SCOPE_STORE
        ) ?? 8;
    }

    /**
     * Apple Pay button height
     *
     * @return int
     */
    public function getAppleButtonHeight(): int
    {
        return (int)$this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/button_height',
            ScopeInterface::SCOPE_STORE
        ) ?? 40;
    }

    /**
     * Get Apple Pay supported networks
     *
     * @return array
     */
    public function getSupportedNetworks(): array
    {
        $networksEnabled = $this->getAppleSupportedNetworks();
        return $this->processSupportedNetworks($networksEnabled);
    }

    /**
     * Exclude 'mada' network if store country is not SA
     *
     * @param array $networksEnabled
     * @return array
     */
    public function processSupportedNetworks(array $networksEnabled): array
    {
        $storeCountry = $this->config->getStoreCountry();

        if (in_array('mada', $networksEnabled) && $storeCountry !== 'SA') {
            $key = array_search('mada', $networksEnabled);
            if ($key !== false) {
                unset($networksEnabled[$key]);
                $networksEnabled = array_values($networksEnabled);
            }
        }

        return $networksEnabled;
    }

    /**
     * Get Countries in Apple Pay that are regions in Magento
     *
     * @return array
     */
    public function getUsTerritories(): array
    {
        return [
            'PR', // Puerto Rico
            'VI', // U.S. Virgin Islands
            'GU', // Guam
            'AS', // American Samoa
            'MP'  // Northern Mariana Islands
        ];
    }

    /**
     * Get countries that don't require postal codes
     *
     * @return array
     */
    public function getCountriesNotRequiringPostalCode(): array
    {
        return [
            'AE', // United Arab Emirates
            'AG', // Antigua and Barbuda
            'AO', // Angola
            'AW', // Aruba
            'BF', // Burkina Faso
            'BI', // Burundi
            'BJ', // Benin
            'BO', // Bolivia
            'BQ', // Bonaire, Sint Eustatius and Saba
            'BS', // Bahamas
            'BW', // Botswana
            'BZ', // Belize
            'CD', // Congo, the Democratic Republic of the
            'CF', // Central African Republic
            'CG', // Congo
            'CI', // Cote d'Ivoire
            'CM', // Cameroon
            'CK', // Cook Islands
            'CW', // Curaçao
            'DJ', // Djibouti
            'DM', // Dominica
            'ER', // Eritrea
            'FJ', // Fiji
            'GA', // Gabon
            'GD', // Grenada
            'GH', // Ghana
            'GM', // Gambia
            'GQ', // Equatorial Guinea
            'GY', // Guyana
            'HK', // Hong Kong
            'HM', // Heard and McDonald Islands
            'KI', // Kiribati
            'KM', // Comoros
            'KN', // Saint Kitts and Nevis
            'KP', // North Korea
            'LY', // Libya
            'ML', // Mali
            'MO', // Macau
            'MR', // Mauritania
            'MW', // Malawi
            'NR', // Nauru
            'NU', // Niue
            'QA', // Qatar
            'RW', // Rwanda
            'SB', // Solomon Islands
            'SC', // Seychelles
            'SL', // Sierra Leone
            'SR', // Suriname
            'ST', // Sao Tome and Principe
            'SY', // Syria
            'TF', // French Southern Territories
            'TG', // Togo
            'TK', // Tokelau
            'TL', // Timor-Leste / East Timor
            'TO', // Tonga
            'TV', // Tuvalu
            'UG', // Uganda
            'VU', // Vanuatu
            'YE', // Yemen
            'ZW'  // Zimbabwe
        ];
    }

    /**
     * Get Apple Pay static configuration as JSON
     * Only includes configuration that doesn't change per session/user
     *
     * @return string
     */
    public function getApplePayConfigJson(): string
    {
        $supportedNetworks = $this->getSupportedNetworks();

        $config = [
            // Merchant settings
            'merchantName' => $this->config->getStoreName(),
            'merchantId' => $this->getAppleMerchantID(),
            'country' => $this->config->getStoreCountry(),
            'supportedNetworks' => $supportedNetworks,
            'merchantCapabilities' => $this->getAppleMerchantCapabilities(),

            // Button styling
            'buttonStyle' => $this->getAppleButtonStyle(),
            'buttonRadius' => $this->getAppleButtonRadius(),
            'buttonHeight' => $this->getAppleButtonHeight(),

            // URLs
            'validationUrl' => $this->urls->getUrl('checkout_com/applepay/validation'),
            'placeOrderUrl' => $this->urls->getUrl('checkout_com/cart/placeorder'),
            'methodId' => 'checkoutcom_apple_pay',

            // Reference lists
            'usTerritories' => $this->getUsTerritories(),
            'countriesNotRequiringPostalCode' => $this->getCountriesNotRequiringPostalCode()
        ];

        return json_encode($config);
    }

    /**
     * Is Apple Pay enabled in cart
     *
     * @return bool
     */
    public function isApplePayCartEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/enabled_on_cart',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is Apple Pay enabled in minicart
     *
     * @return bool
     */
    public function isApplePayMinicartEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/enabled_on_minicart',
            ScopeInterface::SCOPE_STORE
        );
    }
}
