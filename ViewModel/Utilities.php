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
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;

class Utilities implements ArgumentInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly QuoteIdMaskResource $quoteIdMaskResource,
        private readonly SerializerInterface $serializer
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

    /**
     * Get cart ID for API calls
     * Returns masked quote ID for guests, quote ID for logged-in customers
     *
     * @return string|null
     */
    public function getCartId(): ?string
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $quoteId = $quote->getId();

            // For logged-in customers, return the quote ID directly
            if ($this->customerSession->isLoggedIn()) {
                return (string) $quoteId;
            }

            // For guest customers, return the masked quote ID
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $this->quoteIdMaskResource->load($quoteIdMask, $quoteId, 'quote_id');
            if ($quoteIdMask->getMaskedId()) {
                return $quoteIdMask->getMaskedId();
            }

            // Fallback to quote ID if no mask found
            return (string) $quoteId;
        } catch (LocalizedException | NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    public function isCustomerLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Unserialize the given string
     *
     * @param string $data
     * @return array
     */
    public function unserialize(string $data): array
    {
        return $this->serializer->unserialize($data);
    }
}
