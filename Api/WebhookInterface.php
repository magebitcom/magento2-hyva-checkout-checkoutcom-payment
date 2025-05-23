<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Api;

use Magento\Framework\Exception\LocalizedException;

interface WebhookInterface
{
    /**
     * Process webhook data
     *
     * @return void
     * @throws LocalizedException
     */
    public function process(): void;
}
