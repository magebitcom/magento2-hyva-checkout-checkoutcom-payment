<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Model\Config\Backend\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AdditionalSuccessStates implements OptionSourceInterface
{

    /**
     * Just in case merchant considers additional events as success states
     *
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'payment_pending', 'label' => __('Payment Pending')],
            ['value' => 'payment_capture_pending', 'label' => __('Payment Capture Pending')],
        ];
    }
}


