<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\ViewModel;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Data implements ArgumentInterface
{

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * Get Currency
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        try {
            return $this->checkoutSession
                ->getQuote()
                ->getQuoteCurrencyCode();
        } catch (LocalizedException | NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Get total
     *
     * @return float
     */
    public function getTotal(): float
    {
        try {
            return (float) $this->checkoutSession
                ->getQuote()
                ->getGrandTotal();
        } catch (Exception $e) {
            return 0.00;
        }
    }
}
