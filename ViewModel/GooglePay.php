<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\ViewModel;

use CheckoutCom\Magento2\Model\Config\Backend\Source\ConfigGooglePayButton;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class GooglePay implements ArgumentInterface
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
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
}
