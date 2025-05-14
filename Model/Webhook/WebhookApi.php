<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Model\Webhook;

use CheckoutCom\Magento2\Model\Service\OrderStatusHandlerService;
use Magebit\CheckoutComPayment\Api\WebhookInterface;
use Magebit\CheckoutComPayment\Helper\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use CheckoutCom\Magento2\Gateway\Config\Config as GatewayConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class WebhookApi implements WebhookInterface
{
    /**
     * @param Json $jsonSerializer
     * @param OrderStatusHandlerService $orderStatusHandler
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param GatewayConfig $gatewayConfig
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Json $jsonSerializer,
        private readonly OrderStatusHandlerService $orderStatusHandler,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly GatewayConfig $gatewayConfig,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process webhook payload
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function process(): void
    {
        $payload = file_get_contents('php://input');
        $headers = getallheaders();
        $data = $this->jsonSerializer->unserialize($payload);

        $this->verifyWebhook($payload, $headers);

        $paymentData = $this->extractWebhookData($data);
        if (!$paymentData) {
            return;
        }

        $this->processPayment($paymentData);
    }

    /**
     * Verify webhook authenticity
     *
     * @param string $payload
     * @param array $headers
     * @throws LocalizedException
     */
    private function verifyWebhook(string $payload, array $headers): void
    {
        $authorization = $headers['Authorization'] ?? '';
        $isAuthValid = $authorization === $this->config->getAuthHeaderKey();

        if (!$isAuthValid) {
            throw new LocalizedException(__('Invalid webhook authentication'));
        }
    }

    /**
     * Extract payment data from webhook payload
     *
     * @param array $data
     * @return array|null
     */
    private function extractWebhookData(array $data): ?array
    {
        $sourceData = $data['data'] ?? $data;

        if (empty($sourceData['reference'])) {
            return null;
        }

        return [
            'reference' => $sourceData['reference'],
            'response_code' => $sourceData['response_code'] ?? null,
            'response_summary' => $sourceData['response_summary'] ?? null,
            'methodId' => $sourceData['metadata']['methodId'] ?? null
        ];
    }

    /**
     * Process the payment data
     *
     * @param array $paymentData
     * @return void
     */
    private function processPayment(array $paymentData): void
    {
        $reference = $paymentData['reference'];
        $responseCode = $paymentData['response_code'] ?? '';
        $methodId = $paymentData['methodId'] ?? null;

        // Skip processing if no response code, process only apm
        if (empty($responseCode) || $methodId !== 'checkoutcom_apm') {
            return;
        }

        try {
            $order = $this->getOrderByIncrementId($reference);
            if (!$order) {
                return;
            }

            $webhookData = [
                'event_type' => $this->mapResponseToEventType($responseCode),
            ];

            if ($responseCode === '10000') {
                // Successful payment - set order status to processing
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus(Order::STATE_PROCESSING);
                $this->orderRepository->save($order);
            } else {
                // All other response codes indicate failed payment
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('response_summary', $paymentData['response_summary']);
                $this->logger->info('Updated payment additional information', [
                    'order_increment' => $order->getIncrementId(),
                    'response_summary' => $paymentData['response_summary']
                ]);
                $this->orderRepository->save($order);
                if ($this->gatewayConfig->isPaymentWithOrderFirst()) {
                    $this->orderStatusHandler->handleFailedPayment($order, $webhookData['event_type']);
                }
            }

        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Get order by increment ID
     *
     * @param string $incrementId
     * @return OrderInterface|null
     */
    private function getOrderByIncrementId(string $incrementId): ?OrderInterface
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->create();

            $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

            if (empty($orderList)) {
                return null;
            }

            return reset($orderList);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Map response code to event type for OrderStatusHandlerService
     *
     * @param string $responseCode
     * @return string
     */
    private function mapResponseToEventType(string $responseCode): string
    {
        return match ($responseCode) {
            '10000' => 'payment_approved',
            '20000' => 'payment_declined',
            '20017' => 'payment_declined', // Customer cancellation
            '20019' => 'payment_expired',  // Transaction expired
            '20003', '20120' => 'payment_authentication_failed', // Authentication errors
            default => 'payment_declined'
        };
    }
}
