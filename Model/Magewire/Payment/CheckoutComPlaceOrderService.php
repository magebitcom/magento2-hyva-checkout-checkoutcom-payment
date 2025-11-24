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
use Psr\Log\LoggerInterface;
use CheckoutCom\Magento2\Helper\Utilities;
use CheckoutCom\Magento2\Model\Service\ApiHandlerService;
use CheckoutCom\Magento2\Model\Service\MethodHandlerService;
use CheckoutCom\Magento2\Model\Service\OrderHandlerService;
use CheckoutCom\Magento2\Model\Service\OrderStatusHandlerService;
use CheckoutCom\Magento2\Model\Service\PaymentErrorHandlerService;
use CheckoutCom\Magento2\Model\Service\QuoteHandlerService;
use Exception;
use Hyva\Checkout\Model\Magewire\Payment\AbstractOrderData;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magebit\CheckoutComPayment\Magewire\Payment\Method\CheckoutComApm;
use Magebit\CheckoutComPayment\Magewire\Payment\Method\CheckoutComApplePay;
use Magebit\CheckoutComPayment\Magewire\Payment\Method\CheckoutComCard;
use Magebit\CheckoutComPayment\Magewire\Payment\Method\CheckoutComGooglePay;
use Magebit\CheckoutComPayment\Magewire\Payment\Method\CheckoutComVault;
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
     * @var string|null
     */
    private ?string $errorMessage = null;

    /**
     * @var bool
     */
    private bool $disableRedirect = false;

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
     * @param LoggerInterface $psrLogger
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
        private readonly LoggerInterface $psrLogger,
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
                'checkoutcom_google_pay' => [
                    'methodId' => 'checkoutcom_google_pay',
                    'cardToken' => $this->session->getData(CheckoutComGooglePay::PAYMENT_TOKEN),
                    'source' => $this->session->getData(CheckoutComGooglePay::PAYMENT_SOURCE)
                ],
                'checkoutcom_apple_pay' => [
                    'methodId' => 'checkoutcom_apple_pay',
                    'cardToken' => $this->session->getData(CheckoutComApplePay::PAYMENT_TOKEN),
                    'source' => $this->session->getData(CheckoutComApplePay::PAYMENT_SOURCE)
                ],
                'checkoutcom_apm' => $this->getApmData(),
                default => []
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

                    // Check if response is declined immediately
                    $isDeclined = isset($response['status']) && $response['status'] === 'Declined';

                    // Check if it's MB WAY payment
                    $isMbWay = isset($data['source']) && $data['source'] === 'mbway';

                    // Standard API response validation
                    $isValidResponse = $api->isValidResponse($response);

                    if ($isValidResponse && !$isDeclined) {
                        // Create an order if processing is payment first
                        $order = $order === null ? $this->orderHandler->setMethodId($data['methodId'])->handleOrder($quote) : $order;

                        // Add the payment info to the order
                        $order = $this->utilities->setPaymentData($order, $response, $data);

                        // Set order status to pending payment
                        $order->setStatus('awaiting_payment');

                        // Check for redirection
                        if (isset($response['_links']['redirect']['href'])) {
                            $url = $response['_links']['redirect']['href'];
                        }

                        // Special handling for MB WAY
                        if ($isMbWay) {
                            // Redirect to waiting page - order increment will be retrieved from getLastRealOrder()
                            $this->setUrlRedirect('checkout_com/onepage/status');
                        }

                        // Save the order
                        $this->orderRepository->save($order);
                        // Update the response parameters
                        $success = true;
                    } else {
                        // Payment failed or declined
                        if ($isDeclined) {
                            // Handle declined payment specifically
                            if (isset($response['response_summary'])) {
                                $message = __('Your payment was declined. Reason: %1', $response['response_summary']);
                            } else {
                                $message = __('Your payment was declined.');
                            }
                        } elseif (isset($response['response_code'])) {
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

            $this->errorMessage = $message ? (string)$message: 'An error has occurred, please select another payment method';
            $this->messageManager->addErrorMessage(__($this->errorMessage));

            if ($debugMessage) {
                $this->logger->write($debugMessage);
            }

            $this->setUrlRedirect('hyva_checkout/index');

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
            $quote->setPaymentMethod($paymentMethod);
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

    /**
     * Get error message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get alternative payment method data
     *
     * @return array
     */
    public function getApmData(): array
    {
        $selectedApm = $this->session->getData(CheckoutComApm::SELECTED_APM);
        $apmData = $this->session->getData(CheckoutComApm::APM_DATA) ?: [];

        $data = [
            'methodId' => 'checkoutcom_apm',
            'source' => $selectedApm
        ];

        if (isset($apmData[$selectedApm])) {
            $data = array_merge($data, $apmData[$selectedApm]);
        }

        return $data;
    }
}
