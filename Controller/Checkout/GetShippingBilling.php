<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;

class GetShippingBilling implements HttpPostActionInterface
{
    public const ROUTE = 'checkoutcom/checkout/getshippingbilling';

    /**
     * Inject dependencies
     *
     * @param CheckoutSession $checkoutSession
     * @param Curl $curl
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param RequestInterface $request
     */
    public function __construct(
        protected readonly CheckoutSession $checkoutSession,
        protected readonly Curl $curl,
        protected readonly JsonFactory $jsonFactory,
        protected readonly LoggerInterface $logger,
        protected readonly RequestInterface $request
    ) {
    }

    /**
     * Returns currently selected shipping address and billing address
     *
     * @return Json|ResultInterface|ResponseInterface
     */
    public function execute(): Json|ResultInterface|ResponseInterface
    {
        $result = $this->jsonFactory->create();
        try {
            $quote = $this->getQuote();

            if (!$quote instanceof Quote) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Invalid quote type')
                ]);
            }

            $shippingAddress = $quote->getShippingAddress();
            $billingAddress = $quote->getBillingAddress();

            $data = [
                'success' => true,
                'shippingAddress' => $this->formatAddress($shippingAddress),
                'billingAddress' => $this->formatAddress($billingAddress)
            ];

            return $result->setData($data);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving checkout addresses: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => __('Unable to retrieve address information')
            ]);
        }
    }

    /**
     * Format address data according to the required structure
     *
     * @param Address|null $address
     * @return array<string, string>
     */
    protected function formatAddress(?Address $address): array
    {
        if (!$address) {
            return [
                'given_name' => '',
                'family_name' => '',
                'email' => '',
                'street_address' => '',
                'postal_code' => '',
                'city' => '',
                'phone' => '',
                'country' => ''
            ];
        }

        return [
            'given_name' => $address->getFirstname() ?? '',
            'family_name' => $address->getLastname() ?? '',
            'email' => $address->getEmail() ?? '',
            'street_address' => $this->getStreetAddress($address),
            'postal_code' => $address->getPostcode() ?? '',
            'city' => $address->getCity() ?? '',
            'phone' => $address->getTelephone() ?? '',
            'country' => $address->getCountryId() ?? ''
        ];
    }

    /**
     * Get formatted street address
     *
     * @param Address $address
     * @return string
     */
    protected function getStreetAddress(Address $address): string
    {
        $street = $address->getStreet();

        if (is_array($street)) {
            return implode(', ', array_filter($street));
        }

        return (string) $street;
    }

    /**
     * Get the quote
     *
     * @return CartInterface|Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getQuote(): CartInterface|Quote
    {
        return $this->checkoutSession->getQuote();
    }
}
