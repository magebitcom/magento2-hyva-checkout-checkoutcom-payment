<?php
/**
 * @copyright Copyright (c) 2024 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Block\Payment;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;
use CheckoutCom\Magento2\Gateway\Config\Config;
use CheckoutCom\Magento2\Model\Service\VaultHandlerService;
use Magento\Framework\View\Result\PageFactory;
use Magento\Vault\Model\PaymentToken;

class Vault extends Template
{
    /**
     * @param Context $context
     * @param Config $config
     * @param VaultHandlerService $vaultHandler
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly VaultHandlerService $vaultHandler,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Get the user cards from the vault
     *
     * @return string
     */
    public function getCards(): string
    {
        $html = '';
        // Check if vault is enabled
        $vaultEnabled = $this->config->getValue('active', 'checkoutcom_vault');

        // Load block data for vault
        if ($vaultEnabled) {
            // Get the uer cards
            $cards = $this->vaultHandler->getUserCards();
            foreach ($cards as $card) {
                $html .= $this->loadBlock($card);
            }
        }

        return $html;
    }

    /**
     * Load the card block
     *
     * @param PaymentToken $card
     * @return string
     */
    public function loadBlock(PaymentToken $card): string
    {
        return $this->pageFactory->create()
            ->getLayout()
            ->createBlock('CheckoutCom\Magento2\Block\Vault\Form')
            ->setTemplate('Magebit_CheckoutComPayment::payment/vault/card.phtml')
            ->setData('card', $card)
            ->toHtml();
    }
}
