<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Magewire\Payment\Method;

use Magebit\CheckoutComPayment\Model\Magewire\Payment\CheckoutComPlaceOrderService;
use Magebit\CheckoutComPayment\ViewModel\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magewirephp\Magewire\Component;

class CheckoutComApplePay extends Component
{
    /**
     * Define component's listeners for checkout events
     *
     * @var array
     */
    protected $listeners = [
        'shipping_method_selected' => 'refresh',
        'coupon_code_applied' => 'refresh',
        'coupon_code_revoked' => 'refresh',
    ];

    /**
     * @var float
     */
    public float $amount = 0.0;

    /**
     * Apple Pay session storage constants
     */
    public const PAYMENT_TOKEN = 'checkout_com_apple_pay_token';
    public const PAYMENT_SOURCE = 'checkout_com_apple_pay_source';

    /**
     * @param CheckoutSession $checkoutSession
     * @param CheckoutComPlaceOrderService $placeOrderService
     * @param Data $checkoutViewModel
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CheckoutComPlaceOrderService $placeOrderService,
        private readonly Data $checkoutViewModel
    ) {
    }

    /**
     * Initialize component with cart details
     *
     * @return void
     */
    public function boot(): void
    {
        $this->setCartDetails();
    }

    /**
     * Set all cart details needed for placing order
     *
     * @return void
     */
    protected function setCartDetails(): void
    {
        $this->amount = $this->checkoutViewModel->getTotal();
    }

    /**
     * Set the Apple Pay token with related data
     *
     * @param array $tokenData
     * @return void
     */
    public function setPaymentToken(array $tokenData): void
    {
        $this->checkoutSession->setData(self::PAYMENT_TOKEN, $tokenData);
        $this->checkoutSession->setData(self::PAYMENT_SOURCE, 'checkoutcom_apple_pay');
    }

    /**
     * Place order and handle redirection
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @return void
     */
    public function placeOrder(): void
    {
        $quote = $this->checkoutSession->getQuote();
        $orderId = $this->placeOrderService->placeOrder($quote);

        if ($orderId) {
            $redirectUrl = $this->placeOrderService->getRedirectUrl($quote, $orderId);
            $this->redirect($redirectUrl, null, true);
        }
    }
}
