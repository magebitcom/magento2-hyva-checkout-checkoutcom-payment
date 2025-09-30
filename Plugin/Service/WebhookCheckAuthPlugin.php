<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Plugin\Service;

use CheckoutCom\Magento2\Model\Service\WebhookHandlerService;
use Magento\Sales\Api\Data\OrderInterface;

class WebhookCheckAuthPlugin
{

    /**
     * Add a small delay for payment_captured events to allow other events to be processed first
     *
     * @param WebhookHandlerService $subject
     * @param OrderInterface $order
     * @param array $payload
     * @return array
     */
    public function beforeCheckAuth(
        WebhookHandlerService $subject,
        OrderInterface $order,
        array $payload
    ): array {
        $eventType = $payload['type'] ?? 'unknown';

        // Add a small delay for payment_captured events to allow payment_approved/payment_capture_pending to be processed first
        if ($eventType === 'payment_captured') {
            usleep(500000); // 500 ms delay
        }

        return [$order, $payload];
    }
}
