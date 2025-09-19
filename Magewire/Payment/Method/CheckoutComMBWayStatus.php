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
use Magebit\CheckoutComPayment\Helper\Config;
use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Model\Concern\Redirect as RedirectTrait;
use Psr\Log\LoggerInterface;

class CheckoutComMBWayStatus extends Component
{
    use RedirectTrait;

    public string $orderStatus = '';
    public string $orderIncrement = '';
    public bool $isPolling = true;

    public int $lastCheckedWebhookId = 0;

    public array $processingStatuses = [
        'payment_approved',
        'payment_captured'
    ];

    public array $failedStatuses = [
        'payment_declined',
        'payment_expired',
        'payment_authentication_failed'
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
    private function ensureOrderIncrement(): void
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

            // Process all new webhooks (since lastCheckedWebhookId) in chronological order
            foreach ($matchingWebhooks as $webhook) {
                $currentId = (int)$webhook['id'];

                // Safety check (query already filters > lastCheckedWebhookId)
                if ($currentId <= $this->lastCheckedWebhookId) {
                    continue;
                }

                try {
                    $eventData = $this->jsonSerializer->unserialize($webhook['event_data']);
                    $eventType = $webhook['event_type'];
                    $this->handleWebhookEvent($eventType, $eventData);

                    // Mark as processed
                    $this->lastCheckedWebhookId = $currentId;

                    // Stop if terminal status reached (redirect/stopPolling was called)
                    if (!$this->isPolling) {
                        break;
                    }
                } catch (Exception $e) {
                    $this->logger->error('Failed to parse webhook event data', [
                        'webhook_id' => $webhook['id'],
                        'error' => $e->getMessage()
                    ]);
                }
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
     * Get webhooks for specific order using direct database query
     *
     * @param string $orderIncrement
     * @return array
     */
    private function getWebhooksForOrder(string $orderIncrement): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('checkoutcom_webhooks');

            // Query to find webhooks where event_data JSON contains the reference
            $select = $connection->select()
                ->from($tableName, ['id', 'event_type', 'event_data', 'received_at', 'order_id'])
                ->where('event_data LIKE ?', '%"reference":"' . $orderIncrement . '"%')
                ->where('id > ?', (int)$this->lastCheckedWebhookId)
                ->order('id ASC'); // process in chronological order

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
     * Handle webhook event based on event type
     *
     * @param string $eventType
     * @param array $eventData
     * @return void
     */
    private function handleWebhookEvent(string $eventType, array $eventData): void
    {
        $responseCode = $eventData['response_code'] ??
            $eventData['data']['response_code'] ??
            null;

        $responseSummary = $eventData['response_summary'] ??
            $eventData['data']['response_summary'] ??
            'Unknown';

        // Check if event is configured as additional success state
        $isAdditionalSuccessState = $this->isEventInAdditionalSuccessStates($eventType);

        // Check response code first - 10000 means approved regardless of event type
        // OR if admin has configured this event type as additional success state
        if ($responseCode === '10000' || $isAdditionalSuccessState) {
            // Payment successful - redirect to success page
            $this->stopPolling();
            $this->redirect('checkout/onepage/success');
            return;
        }

        // Check for failure response codes (20000+)
        if ($responseCode && (int)$responseCode >= 20000) {
            // Payment failed - restore quote and redirect to checkout
            $this->checkoutSession->restoreQuote();

            $this->messageManager->addErrorMessage(
                __('Your payment was declined. Reason: %1', $responseSummary)
            );

            $this->stopPolling();
            $this->redirect('hyva_checkout/index');
            return;
        }

        if (in_array($eventType, $this->processingStatuses)) {
            // Payment successful - redirect to success page
            $this->stopPolling();
            $this->redirect('checkout/onepage/success');
        } elseif (in_array($eventType, $this->failedStatuses)) {
            // Payment failed - restore quote and redirect to the checkout
            $this->checkoutSession->restoreQuote();

            $this->messageManager->addErrorMessage(
                __('Your payment was declined. Reason: %1', $responseSummary)
            );

            $this->stopPolling();
            $this->redirect('hyva_checkout/index');
        } elseif (in_array($eventType, ['payment_pending', 'payment_capture_pending'])) {
            // Continue polling for pending payments (unless we have a definitive response code)
            return;
        } else {
            // Unknown event type - log and stop polling
            $this->stopPolling();
        }
    }

    /**
     * Check if the event type is configured as an additional success state
     *
     * @param string $eventType
     * @return bool
     */
    private function isEventInAdditionalSuccessStates(string $eventType): bool
    {
        $states = $this->config->getMbWayAdditionalSuccessStates();
        return in_array($eventType, $states, true);
    }
}
