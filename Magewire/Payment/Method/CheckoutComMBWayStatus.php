<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Magewire\Payment\Method;

use Magento\Framework\App\ResourceConnection;
use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magebit\CheckoutComPayment\ViewModel\MbWay as Config;
use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Model\Concern\Redirect as RedirectTrait;
use Psr\Log\LoggerInterface;

class CheckoutComMBWayStatus extends Component
{
    use RedirectTrait;

    public string $orderStatus = '';
    public string $orderIncrement = '';
    public bool $isPolling = true;
    public int $successCode = 10000;
    public array $failedStatuses = [
        'payment_declined',
        'payment_expired',
        'payment_authentication_failed'
    ];

    public array $failedCodes = [
        20000,
        20017,
        20003,
        20120,
        20019
    ];

    /**
     * @param CheckoutSession $checkoutSession
     * @param JsonSerializer $jsonSerializer
     * @param LoggerInterface $logger
     * @param ManagerInterface $messageManager
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly JsonSerializer $jsonSerializer,
        private readonly LoggerInterface $logger,
        private readonly ManagerInterface $messageManager,
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
    ) {
    }

    /**
     * Lifecycle hook called after hydration on subsequent requests
     *
     * @return void
     */
    public function booted(): void
    {
        if ($this->isPolling && empty($this->orderIncrement)) {
            $this->ensureOrderIncrement();
        }
    }

    /**
     * Stop polling
     *
     * @return void
     */
    public function stopPolling(): void
    {
        $this->isPolling = false;
    }

    /**
     * Ensure order increment is set by retrieving it if needed
     *
     * @return void
     */
    public function ensureOrderIncrement(): void
    {
        if (empty($this->orderIncrement)) {
            try {
                $lastOrder = $this->checkoutSession->getLastRealOrder();
                $this->orderIncrement = $lastOrder->getIncrementId() ?? '';
            } catch (\Exception $e) {
                $this->orderIncrement = '';
            }
        }
    }

    /**
     * Check the current order status through webhook data
     *
     * @return void
     */
    public function checkOrderStatus(): void
    {
        if (!$this->isPolling) {
            return;
        }

        try {
            $this->ensureOrderIncrement();

            if (!$this->orderIncrement) {
                $this->stopPolling();
                return;
            }

            $matchingWebhooks = $this->getWebhooksForOrder($this->orderIncrement);

            if (empty($matchingWebhooks)) {
                return;
            }

            $allEvents = [];

            foreach ($matchingWebhooks as $webhook) {
                try {
                    $eventData = $this->jsonSerializer->unserialize($webhook['event_data']);
                    $eventType = $webhook['event_type'];

                    $allEvents[] = [
                        'id' => $webhook['id'],
                        'type' => $eventType,
                        'data' => $eventData
                    ];
                } catch (Exception $e) {
                    $this->logger->error('Failed to parse webhook event data', [
                        'webhook_id' => $webhook['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Determine the most definitive status from all events
            if (!empty($allEvents)) {
                $this->handleMultipleWebhookEvents($allEvents);
            }
        } catch (Exception $e) {
            $this->logger->error('Error checking order status via webhooks', [
                'order_increment' => $this->orderIncrement ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->stopPolling();
        }
    }

    /**
     * Get all webhooks for specific order using direct database query
     *
     * @param string $orderIncrement
     * @return array
     */
    public function getWebhooksForOrder(string $orderIncrement): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('checkoutcom_webhooks');

            // Query to find all webhooks where event_data JSON contains the reference
            $select = $connection->select()
                ->from($tableName, ['id', 'event_type', 'event_data', 'received_at', 'order_id'])
                ->where('event_data LIKE ?', '%"reference":"' . $orderIncrement . '"%')
                ->order('id ASC');

            $webhooks = $connection->fetchAll($select);

            $verifiedWebhooks = [];
            foreach ($webhooks as $webhook) {
                try {
                    $eventData = $this->jsonSerializer->unserialize($webhook['event_data']);
                    $webhookReference = $eventData['data']['reference'] ?? null;

                    if ($webhookReference === $orderIncrement) {
                        $verifiedWebhooks[] = $webhook;
                    }
                } catch (Exception $e) {
                    $this->logger->error('Failed to parse webhook during verification', [
                        'webhook_id' => $webhook['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $verifiedWebhooks;
        } catch (Exception $e) {
            $this->logger->error('Failed to execute direct webhook query', [
                'order_increment' => $orderIncrement,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Handle multiple webhook events and determine the most definitive status
     *
     * @param array $allEvents
     * @return void
     */
    public function handleMultipleWebhookEvents(array $allEvents): void
    {
        $hasSuccessCode = false;
        $hasFailureCode = false;
        $hasAdditionalSuccessEvent = false;
        $lastResponseSummary = 'Unknown';

        // Analyze all events to find the most definitive status
        foreach ($allEvents as $event) {
            $eventType = $event['type'];
            $eventData = $event['data'];

            $responseCode = $eventData['response_code'] ?? $eventData['data']['response_code'] ?? null;
            $responseSummary = $eventData['response_summary'] ?? $eventData['data']['response_summary'] ?? 'Unknown';

            $lastResponseSummary = $responseSummary;

            // Check for definitive success (response code 10000)
            if ((int)$responseCode === $this->successCode) {
                $hasSuccessCode = true;
            }

            // Check for definitive failure (response code 20000+ or in failed codes list)
            if ((int)$responseCode >= 20000 || in_array((int)$responseCode, $this->failedCodes)) {
                $hasFailureCode = true;
            }

            // Check for admin-configured additional success events
            if ($this->isEventInAdditionalSuccessStates($eventType)) {
                $hasAdditionalSuccessEvent = true;
            }
        }

        if ($hasSuccessCode || $hasAdditionalSuccessEvent) {
            $this->stopPolling();
            $this->redirect('checkout/onepage/success');
            return;
        }

        if ($hasFailureCode) {
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage(
                __('Your payment was declined. Reason: %1', $lastResponseSummary)
            );
            $this->stopPolling();
            $this->redirect('hyva_checkout/index');
            return;
        }
        // If we reach here, all events are pending or unknown - continue polling
    }

    /**
     * Check if the event type is configured as an additional success state
     *
     * @param string $eventType
     * @return bool
     */
    public function isEventInAdditionalSuccessStates(string $eventType): bool
    {
        $states = $this->config->getMbWayAdditionalSuccessStates();
        return in_array($eventType, $states, true);
    }
}
