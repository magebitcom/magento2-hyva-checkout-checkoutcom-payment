<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Helper;

use CheckoutCom\Magento2\Model\Config\Backend\Source\ConfigGooglePayButton;
use CheckoutCom\Magento2\Model\Config\Backend\Source\ConfigApplePayButton;
use JsonException;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper implements ArgumentInterface
{
    /**
     * @param Session $session
     * @param Repository $assetRepository
     * @param Context $context
     */
    public function __construct(
        private readonly Session $session,
        private readonly Repository $assetRepository,
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Get the public key for the Checkout.com payment method
     *
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->scopeConfig->getValue(
            'settings/checkoutcom_configuration/public_key',
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * Check whether the vault is enabled
     *
     * @return bool
     */
    public function isVaultEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            'payment/checkoutcom_vault/active',
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * Check whether the save card option is enabled
     *
     * @return bool
     */
    public function isSaveCardEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/save_card_option',
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * Check whether the debug mode is enabled
     *
     * @return bool
     */
    public function isDebugModeEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            'settings/checkoutcom_configuration/debug_mode',
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * Check whether the customer is logged in
     *
     * @return bool
     */
    public function isCustomerLoggedIn(): bool
    {
        return $this->session->isLoggedIn();
    }

    /**
     * Get the card number placeholder
     *
     * @return string|null
     */
    public function getCardNumberPlaceholder(): ?string
    {
        $placeholder = $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/card_number_placeholder',
            ScopeInterface::SCOPE_STORE,
        );
        return ($placeholder && $placeholder !== '') ? $placeholder : null;
    }

    /**
     * Get the expiry month placeholder
     *
     * @return string|null
     */
    public function getExpiryMonthPlaceholder(): ?string
    {
        $placeholder = $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/expiration_date_month_placeholder',
            ScopeInterface::SCOPE_STORE,
        );
        return ($placeholder && $placeholder !== '') ? $placeholder : null;
    }

    /**
     * Get the expiry year placeholder
     *
     * @return string|null
     */
    public function getExpiryYearPlaceholder(): ?string
    {
        $placeholder = $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/expiration_date_year_placeholder',
            ScopeInterface::SCOPE_STORE,
        );
        return ($placeholder && $placeholder !== '') ? $placeholder : null;
    }

    /**
     * Get the CVV placeholder
     *
     * @return string|null
     */
    public function getCvvPlaceholder(): ?string
    {
        $placeholder = $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/cvv_placeholder',
            ScopeInterface::SCOPE_STORE,
        );
        return ($placeholder && $placeholder !== '') ? $placeholder : null;
    }

    /**
     * Get the Form Styles
     *
     * @return string|null
     */
    public function getFormStyles(): ?string
    {
        $formStyles = $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/payment_form_styles',
            ScopeInterface::SCOPE_STORE,
        );
        try {
            json_decode($formStyles, true, 512, JSON_THROW_ON_ERROR);
            return $formStyles;
        } catch (JsonException) {
            return '{}';
        }
    }

    /**
     * Get the Form Layout
     *
     * @return string
     */
    public function getFormLayout(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/payment_form_layout',
            ScopeInterface::SCOPE_STORE,
        ) ?? 'single';
    }

    /**
     * Get the Card Number Label
     *
     * @return string
     */
    public function getCardNumberLabel(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/card_number_label',
            ScopeInterface::SCOPE_STORE,
        ) ?? __('Card Number')->render();
    }

    /**
     * Get the Expiry Date Label
     *
     * @return string
     */
    public function getExpiryLabel(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/expiration_date_label',
            ScopeInterface::SCOPE_STORE,
        ) ?? __('Expiry Date')->render();
    }

    /**
     * Get the CVV Label
     *
     * @return string
     */
    public function getCvvLabel(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_card_payment/cvv_label',
            ScopeInterface::SCOPE_STORE,
        ) ?? __('CVV')->render();
    }

    /**
     * Get Images Path
     *
     * @return string
     */
    public function getImagesPath(): string
    {
        return $this->assetRepository->getUrl('CheckoutCom_Magento2::images');
    }

    /**
     * Check whether MB PAY phone validation is enabled
     *
     * @return bool
     */
    public function isPhoneValidationEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            'payment/checkoutcom_apm/mb_pay_phone_validation',
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * Get the store name
     *
     * @return string
     */
    public function getStoreName(): string
    {
        return $this->scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE,
        ) ?? '';
    }

    /**
     * Get the store country
     *
     * @return string
     */
    public function getStoreCountry(): string
    {
        return $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE,
        ) ?? 'US';
    }

    /**
     * Get Google Pay gateway name
     *
     * @return string
     */
    public function getGatewayName(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_google_pay/gateway_name',
            ScopeInterface::SCOPE_STORE
        ) ?? 'checkoutltd';
    }

    /**
     * Get Google Pay merchant ID
     *
     * @return string|null
     */
    public function getGoogleMerchantId(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_google_pay/merchant_id',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Google Pay environment
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_google_pay/environment',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Google Pay allowed card networks
     *
     * @return array
     */
    public function getGoogleAllowedCardNetworks(): array
    {
        $networks = $this->scopeConfig->getValue(
            'payment/checkoutcom_google_pay/allowed_card_networks',
            ScopeInterface::SCOPE_STORE
        );

        return explode(',', $networks);
    }

    /**
     * Google Pay button corner radius
     *
     * @return int
     */
    public function getGoogleButtonRadius(): int
    {
        return (int)$this->scopeConfig->getValue(
            'payment/checkoutcom_google_pay/button_radius',
            ScopeInterface::SCOPE_STORE,
        ) ?? 8;
    }

    /**
     * Get Google Pay button style
     *
     * @return string
     */
    public function getGoogleButtonStyle(): string
    {
        return $this->scopeConfig->getValue(
            'payment/checkoutcom_google_pay/button_style',
            ScopeInterface::SCOPE_STORE
        ) ?? ConfigGooglePayButton::BUTTON_BLACK;
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
     * Get Apple Pay merchant capabilities
     *
     * @return array
     */
    public function getAppleMerchantCapabilities(): array
    {
        $capabilities = $this->scopeConfig->getValue(
            'payment/checkoutcom_apple_pay/merchant_capabilities',
            ScopeInterface::SCOPE_STORE
        );

        return explode(',', $capabilities);
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
            ScopeInterface::SCOPE_STORE,
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
            ScopeInterface::SCOPE_STORE,
        ) ?? 40;
    }

    /**
     * Webhook authorization header key
     *
     * @return string
     */
    public function getAuthHeaderKey(): string
    {
        return $this->scopeConfig->getValue(
            'settings/checkoutcom_configuration/private_shared_key',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get MB WAY status page text
     *
     * @return string
     */
    public function getMBWayStatusText(): string
    {
        $text = $this->scopeConfig->getValue(
            'payment/checkoutcom_apm/mb_way_status_text',
            ScopeInterface::SCOPE_STORE
        );

        return $text ?: (string) __('Please complete the payment in your MB WAY app to avoid order cancellation.');
    }

    /**
     * Get MB WAY polling interval in milliseconds
     *
     * @return int
     */
    public function getMBWayPollingInterval(): int
    {
        $interval = $this->scopeConfig->getValue(
            'payment/checkoutcom_apm/mb_way_polling_interval',
            ScopeInterface::SCOPE_STORE
        );

        return (int)$interval ?: 5000;
    }

    /**
     * Get selected additional success states for MB WAY
     *
     * @return array
     */
    public function getMbWayAdditionalSuccessStates(): array
    {
        $successStates = $this->scopeConfig->getValue(
            'payment/checkoutcom_apm/mb_way_additional_success_states',
            ScopeInterface::SCOPE_STORE
        );

        if (!$successStates) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string)$successStates))));
    }
}
