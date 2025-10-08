<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Controller\Cart;

use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magebit\CheckoutComPayment\Model\Magewire\Payment\CheckoutComPlaceOrderService;
use Magebit\CheckoutComPayment\Magewire\Payment\Method\CheckoutComApplePay;

class PlaceOrder implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param CheckoutSession $checkoutSession
     * @param CheckoutComPlaceOrderService $placeOrderService
     * @param JsonSerializer $jsonSerializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CheckoutComPlaceOrderService $placeOrderService,
        private readonly JsonSerializer $jsonSerializer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute the place order action from cart
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            // Get the payment data from the request
            $methodId = $this->request->getParam('methodId');
            $cardToken = $this->request->getParam('cardToken');
            $source = $this->request->getParam('source');

            // Validate required parameters
            if (empty($methodId) || empty($cardToken) || empty($source)) {
                throw new LocalizedException(__('Missing required payment parameters.'));
            }

            // Parse the card token if it's a JSON string
            $tokenData = is_string($cardToken) ? $this->jsonSerializer->unserialize($cardToken) : $cardToken;
            // Set the payment method on the quote
            $quote = $this->checkoutSession->getQuote();
            $quote->getPayment()->setMethod($methodId);

            // Store the payment token in the session (same as checkout flow)
            /** @noinspection PhpUndefinedMethodInspection */
            $this->checkoutSession->setData(CheckoutComApplePay::PAYMENT_TOKEN, $tokenData);
            /** @noinspection PhpUndefinedMethodInspection */
            $this->checkoutSession->setData(CheckoutComApplePay::PAYMENT_SOURCE, $source);

            // Place the order using the same service as checkout
            $orderId = $this->placeOrderService->placeOrder($quote);

            if ($orderId) {
                return $result->setData([
                    'success' => true,
                    'orderId' => $orderId,
                    'redirectUrl' => $this->placeOrderService->getRedirectUrl($quote, $orderId)
                ]);
            } else {
                throw new LocalizedException(__('Failed to place order.'));
            }
        } catch (\Exception $e) {
            $this->logger->error('Express payment - Exception: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'error' => __('An error occurred while processing your payment.')
            ]);
        }
    }
}
