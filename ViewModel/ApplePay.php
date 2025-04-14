<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\ViewModel;

use Magebit\CheckoutComPayment\Helper\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class ApplePay implements ArgumentInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Get Apple Pay supported networks
     *
     * @return array
     */
    public function getSupportedNetworks(): array
    {
        $networksEnabled = $this->config->getAppleSupportedNetworks();
        return $this->processSupportedNetworks($networksEnabled);
    }

    /**
     * Exclude 'mada' network if store country is not SA
     *
     * @param array $networksEnabled
     * @return array
     */
    public function processSupportedNetworks(array $networksEnabled): array
    {
        $storeCountry = $this->config->getStoreCountry();

        if (in_array('mada', $networksEnabled) && $storeCountry !== 'SA') {
            $key = array_search('mada', $networksEnabled);
            if ($key !== false) {
                unset($networksEnabled[$key]);
                $networksEnabled = array_values($networksEnabled);
            }
        }

        return $networksEnabled;
    }
}
