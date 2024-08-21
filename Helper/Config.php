<?php
/**
 * @copyright Copyright (c) 2024 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Helper;

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
    public function getFormLayout(): string {
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
}
