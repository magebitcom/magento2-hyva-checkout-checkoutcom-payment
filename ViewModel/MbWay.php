<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class MbWay implements ArgumentInterface
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
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
