<?php
/**
 * @copyright Copyright (c) 2024 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Magewire\Payment\Method;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magewirephp\Magewire\Component;

class CheckoutComCard extends Component
{
    public const PAYMENT_TOKEN = 'checkout_com_payment_token';
    public const PAYMENT_CARD_BIN = 'checkout_com_payment_card_bin';
    public const PAYMENT_PREFERRED_SCHEME = 'checkout_com_payment_preferred_scheme';
    public const SAVE_CARD = 'checkout_com_save_card';

    /**
     * @var bool|null
     */
    public ?bool $saveCard = null;

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * Set the card payment token with related data
     *
     * @param string $token
     * @param string $bin
     * @param string $preferredScheme
     * @return void
     */
    public function setPaymentToken(string $token, string $bin, string $preferredScheme): void
    {
        $this->checkoutSession->setData(self::PAYMENT_TOKEN, $token);
        $this->checkoutSession->setData(self::PAYMENT_CARD_BIN, $bin);
        $this->checkoutSession->setData(self::PAYMENT_PREFERRED_SCHEME, $preferredScheme);

        $this->setSaveCard($this->saveCard);
    }

    /**
     * Set the scheme of the card
     *
     * @param string $preferredScheme
     * @return void
     */
    public function setScheme(string $preferredScheme): void
    {
        $this->checkoutSession->setData(self::PAYMENT_PREFERRED_SCHEME, $preferredScheme);
    }

    /**
     * Set the save card option
     *
     * @param null|bool $saveCard
     * @return void
     */
    public function setSaveCard(null|bool $saveCard): void
    {
        $this->checkoutSession->setData(self::SAVE_CARD, $saveCard ? 'true' : 'false');
    }
}
