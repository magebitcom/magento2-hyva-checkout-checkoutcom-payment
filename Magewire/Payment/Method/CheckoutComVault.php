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

class CheckoutComVault extends Component
{
    public const string PUBLIC_HASH = 'checkout_com_public_hash';

    /**
     * @var string|null
     */
    public ?string $publicHash = null;

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * Set the public hash in the session
     *
     * @param string $publicHash
     * @return void
     */
    public function setPublicHash(string $publicHash): void
    {
        $this->checkoutSession->setData(self::PUBLIC_HASH, $publicHash);
        $this->publicHash = $publicHash;
    }
}
