<?php
/**
 * @copyright Copyright (c) 2024 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Model\Magewire\Payment;

use CheckoutCom\Magento2\Gateway\Config\Config;
use CheckoutCom\Magento2\Helper\Logger;
use CheckoutCom\Magento2\Helper\Utilities;
use CheckoutCom\Magento2\Model\Service\ApiHandlerService;
use CheckoutCom\Magento2\Model\Service\MethodHandlerService;
use CheckoutCom\Magento2\Model\Service\OrderHandlerService;
use CheckoutCom\Magento2\Model\Service\OrderStatusHandlerService;
use CheckoutCom\Magento2\Model\Service\PaymentErrorHandlerService;
use CheckoutCom\Magento2\Model\Service\QuoteHandlerService;
use Exception;
use Magebit\CheckoutComPayment\Magewire\Payment\Method\CheckoutComCard;
use Magebit\CheckoutComPayment\Magewire\Payment\Method\CheckoutComVault;
use Hyva\Checkout\Model\Magewire\Payment\AbstractOrderData;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutComPlaceOrderService extends AbstractPlaceOrderService
{
    /**
     * @var string
     */
    private string $urlRedirect = parent::REDIRECT_PATH;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param QuoteHandlerService $quoteHandler
     * @param OrderHandlerService $orderHandler
     * @param OrderStatusHandlerService $orderStatusHandler
     * @param MethodHandlerService $methodHandler
     * @param ApiHandlerService $apiHandler
     * @param PaymentErrorHandlerService $paymentErrorHandler
     * @param Utilities $utilities
     * @param Logger $logger
     * @param Session $session
     * @param OrderRepositoryInterface $orderRepository
     * @param JsonSerializer $json
     * @param Config $config
     * @param MessageManagerInterface $messageManager
     * @param CartManagementInterface $cartManagement
     * @param AbstractOrderData|null $orderData
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly QuoteHandlerService $quoteHandler,
        private readonly OrderHandlerService $orderHandler,
        private readonly OrderStatusHandlerService $orderStatusHandler,
        private readonly MethodHandlerService $methodHandler,
        private readonly ApiHandlerService $apiHandler,
        private readonly PaymentErrorHandlerService $paymentErrorHandler,
        private readonly Utilities $utilities,
        private readonly Logger $logger,
        private readonly Session $session,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly JsonSerializer $json,
        private readonly Config $config,
        private readonly MessageManagerInterface $messageManager,
        CartManagementInterface $cartManagement,
        AbstractOrderData $orderData = null
    ) {
        parent::__construct($cartManagement, $orderData);
    }

    /**
     * Place order taking data from the session
     *
     * The logic is based on \Magebit\CheckoutCom\Controller\Payment\PlaceOrder::execute
     *
     * @param Quote $quote
     * @return int
     */
    public function placeOrder(Quote $quote): int
    {
        try {
            $url = '';
            $message = '';
            $debugMessage = '';
            $success = false;
            $log = true;

            $method = $quote->getPayment()->getMethod();

            $data = match ($method) {
                'checkoutcom_card_payment' => [
                    'cardToken' => $this->session->getData(CheckoutComCard::PAYMENT_TOKEN),
                    'cardBin' => $this->session->getData(CheckoutComCard::PAYMENT_CARD_BIN),
                    'preferredScheme' => $this->session->getData(CheckoutComCard::PAYMENT_PREFERRED_SCHEME),
                    'saveCard' => $this->session->getData(CheckoutComCard::SAVE_CARD),
                ],
                'checkoutcom_vault' => [
                    'publicHash' => $this->session->getData(CheckoutComVault::PUBLIC_HASH)
                ],
            };

            $data['methodId'] = $method;

            if (isset($data['methodId']) && !$this->isEmptyCardToken($data)) {
                // Reserved an order
                /** @var string $reservedOrderId */
                $reservedOrderId = $this->config->isPaymentWithPaymentFirst() ? $this->quoteHandler->getReference($quote) : null;

                //Create order if it is needed before payment
                $order = $this->config->isPaymentWithOrderFirst() ? $this->orderHandler->setMethodId($data['methodId'])->handleOrder($quote) : null;

                // Process the payment
                if (($this->config->isPaymentWithPaymentFirst() && $this->quoteHandler->isQuote($quote) && $reservedOrderId !== null)
                    || ($this->config->isPaymentWithOrderFirst() && $this->orderHandler->isOrder($order))
                ) {
                    $log = false;
                    // Get the debug config value
                    $debug = $this->scopeConfig->getValue(
                        'settings/checkoutcom_configuration/debug',
                        ScopeInterface::SCOPE_STORE
                    );

                    // Get the gateway response config value
                    $gatewayResponses = $this->scopeConfig->getValue(
                        'settings/checkoutcom_configuration/gateway_responses',
                        ScopeInterface::SCOPE_STORE
                    );

                    //Init values to request payment
                    $amount = (float)$this->config->isPaymentWithPaymentFirst() ? $quote->getGrandTotal() : $order->getGrandTotal();
                    $currency = (string)$this->config->isPaymentWithPaymentFirst() ? $quote->getQuoteCurrencyCode() : $order->getOrderCurrencyCode();
                    $reference = (string)$this->config->isPaymentWithPaymentFirst() ? $reservedOrderId : $order->getIncrementId();

                    // Get response and success
                    $response = $this->requestPayment($quote, $data, $amount, $currency, $reference);

                    // Logging
                    $this->logger->display($response);

                    // Get the store code
                    $storeCode = $this->storeManager->getStore()->getCode();

                    // Process the response
                    $api = $this->apiHandler->init($storeCode, ScopeInterface::SCOPE_STORE);

                    $isValidResponse = $api->isValidResponse($response);

                    if ($isValidResponse) {
                        // Create an order if processing is payment first
                        $order = $order === null ? $this->orderHandler->setMethodId($data['methodId'])->handleOrder($quote) : $order;

                        // Add the payment info to the order
                        $order = $this->utilities->setPaymentData($order, $response, $data);

                        // set order status to pending payment
                        // $order->setStatus(Order::STATE_PENDING_PAYMENT);
                        $order->setStatus('awaiting_payment');

                        // check for redirection
                        if (isset($response['_links']['redirect']['href'])) {
                            $url = $response['_links']['redirect']['href'];
                        }

                        // Save the order
                        $this->orderRepository->save($order);
                        // Update the response parameters
                        $success = $isValidResponse;
                    } else {
                        // Payment failed
                        if (isset($response['response_code'])) {
                            $message = $this->paymentErrorHandler->getErrorMessage($response['response_code']);
                        } else {
                            $message = __('The transaction could not be processed.');
                            if ($debug && $gatewayResponses) {
                                $debugMessage = $this->json->serialize($response);
                            }
                        }

                        // Restore the quote
                        $this->session->restoreQuote();

                        // Handle order on failed payment
                        if ($this->config->isPaymentWithOrderFirst()) {
                            $this->orderStatusHandler->handleFailedPayment($order);
                        }
                    }
                } else {
                    // Payment failed
                    $message = __('The order could not be processed.');
                }
            } else {
                // No token found
                $message = __('Please enter valid card details.');
            }
        } catch (Exception $e) {
            $success = false;
            $this->logger->write($e->getMessage());
        } finally {
            if ($log) {
                $this->logger->write($message);
            }

            if ($success) {
                if ($url) {
                    $this->setUrlRedirect($url);
                }

                return 1;
            }

            $this->messageManager->addErrorMessage(__($message ?: __('An error has occurred, please select another payment method')));

            if ($debugMessage) {
                $this->logger->write($debugMessage);
            }

            $this->setUrlRedirect('checkout/cart');

            return 1;
        }
    }

    /**
     * Check if the card token is empty and the payment method is card
     *
     * @param array $paymentData
     *
     * @return bool
     */
    public function isEmptyCardToken(array $paymentData): bool
    {
        if ($paymentData['methodId'] === 'checkoutcom_card_payment') {
            if (empty($paymentData['cardToken'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Request payment to API handler
     *
     * @param CartInterface $quote
     * @param array $data
     * @param float $amount
     * @param string $currencyCode
     * @param string $reference
     *
     * @return array|null
     * @throws LocalizedException
     */
    protected function requestPayment(
        CartInterface $quote,
        array $data,
        float $amount,
        string $currencyCode,
        string $reference
    ): ?array {
        if ($quote->getPayment()->getMethod() === null) {
            $paymentMethod = $data['methodId'];
            $quote->setPaymentMethod($paymentMethod); //payment method
            $quote->getPayment()->importData(['method' => $paymentMethod]);
        }

        // Get the method id
        $methodId = $quote->getPayment()->getMethodInstance()->getCode();

        // Send the charge request
        return $this->methodHandler->get($methodId)->sendPaymentRequest($data, $amount, $currencyCode, $reference);
    }

    /**
     * Set redirect URL for payment method
     *
     * @param string $url
     * @return void
     */
    public function setUrlRedirect(string $url): void
    {
        $this->urlRedirect = $url;
    }

    /**
     * Get redirect URL for payment method
     *
     * @param Quote $quote
     * @param int|null $orderId
     * @return string
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        return $this->urlRedirect;
    }
}
