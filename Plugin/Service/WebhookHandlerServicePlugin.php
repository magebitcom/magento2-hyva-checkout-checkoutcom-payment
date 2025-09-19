<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Plugin\Service;

use CheckoutCom\Magento2\Model\Service\WebhookHandlerService;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class WebhookHandlerServicePlugin
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Ensure an action_id exists on webhook payloads that omit it.
     *
     * Some webhook event types (e.g. payment_pending) may not include
     * data.action_id. The upstream service expects this field. When missing, we synthesize it
     * using the webhook event id to keep records unique and avoid errors.
     *
     * @param WebhookHandlerService $subject
     * @param OrderInterface $order
     * @param array $payload
     * @return array
     */
    public function beforeProcessSingleWebhook(
        WebhookHandlerService $subject,
        OrderInterface $order,
        array $payload
    ): array {
        if (!isset($payload['data']) || !is_array($payload['data'])) {
            $payload['data'] = [];
        }

        // Handle missing action_id
        if (!isset($payload['data']['action_id']) || $payload['data']['action_id'] === ''
        ) {
            try {
                // Prefer the webhook event id as a stable surrogate.
                $eventId = (string)($payload['id'] ?? '');
                $payload['data']['action_id'] = $eventId;

                $this->logger->info('Synthesized missing action_id for Checkout.com webhook', [
                    'order_id' => $order->getEntityId(),
                    'event_id' => $eventId,
                    'payment_id' => $payload['data']['id'] ?? 'unknown',
                    'type' => $payload['type'] ?? 'unknown',
                    'synthetic_action_id' => $eventId
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to synthesize action_id for webhook.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [$order, $payload];
    }
}
