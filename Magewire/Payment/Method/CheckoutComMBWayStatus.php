<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Magewire\Payment\Method;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Model\Concern\Redirect as RedirectTrait;
use Psr\Log\LoggerInterface;

class CheckoutComMBWayStatus extends Component
{
    use RedirectTrait;

    public string $orderStatus = '';
    public string $orderIncrement = '';
    public bool $isPolling = true;

    /**
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger,
        private readonly ManagerInterface $messageManager,
    ) {
    }

    /**
     * Initialize component with order information
     */
    public function mount(): void
    {
        $this->startPolling();
    }

    /**
     * Start polling for order status
     */
    public function startPolling(): void
    {
        $this->isPolling = true;
        $this->checkOrderStatus();
    }

    /**
     * Stop polling
     */
    public function stopPolling(): void
    {
        $this->isPolling = false;
    }

    /**
     * Check the current order status
     *
     * @return void
     */
    public function checkOrderStatus(): void
    {
        try {
            $orderId = $this->checkoutSession->getLastRealOrder()->getIncrementId();
            if (!$orderId) {
                $this->orderIncrement = '';
                $this->stopPolling();
                return;
            }

            $this->orderIncrement = $orderId;

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $orderId)
                ->create();

            $orderList = $this->orderRepository->getList($searchCriteria)->getItems();
            $order = reset($orderList);

            if (!$order) {
                $this->stopPolling();
                return;
            }

            $orderStatus = $order->getStatus();

            if ($orderStatus === 'canceled') {
                $this->checkoutSession->restoreQuote();

                $payment = $order->getPayment();
                $additionalInfo = $payment->getAdditionalInformation();
                $responseSummary = $additionalInfo['response_summary'] ?? 'Rejected';

                $this->messageManager->addErrorMessage(
                    __('Your payment was declined. Reason: %1', $responseSummary)
                );

                $this->stopPolling();
                $this->redirect('hyva_checkout/index');
            } else {
                $this->stopPolling();
                $this->redirect('checkout/onepage/success');
            }
        } catch (Exception $e) {
            $this->stopPolling();
        }
    }
}
