<?php
/**
 * @copyright Copyright (c) 2024 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Controller\Details;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Controller\ResultInterface;

class CardName implements HttpGetActionInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param JsonFactory $jsonResultFactory
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly JsonFactory $jsonResultFactory
    ) {
    }

    /**
     * Get the cardholder name from the billing address
     *
     * @return ResultInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(): ResultInterface
    {
        $billingAddress = $this->checkoutSession->getQuote()->getBillingAddress();

        $cardHolderName = $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();

        return $this->jsonResultFactory->create()->setData(['card_holder_name' => $cardHolderName]);
    }
}
