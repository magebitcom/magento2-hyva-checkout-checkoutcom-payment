<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Controller\Klarna;

use CheckoutCom\Magento2\Gateway\Config\Config;
use CheckoutCom\Magento2\Helper\Logger;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\HTTP\Client\Curl;
use \Exception;

class Status implements HttpPostActionInterface
{
    /**
     * @param JsonFactory $resultJsonFactory
     * @param Config $config
     * @param RequestInterface $request
     * @param SerializerInterface $serializer
     * @param Curl $curl
     * @param Logger $logger
     */
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly Config $config,
        private readonly RequestInterface $request,
        private readonly SerializerInterface $serializer,
        private readonly Curl $curl,
        private readonly Logger $logger
    ) {
    }


    /**
     * Check payment context status
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $status = $this->getPaymentContextStatus();

            return $resultJson->setData(['success' => true, 'status' => $status]);
        } catch (Exception $e) {
            $this->logger->write('Klarna Status Error: ' . $e->getMessage());

            return $resultJson->setData(['success' => false, 'errorMessage' => __('Unable to check payment status. Please try again later.')]);
        }
    }


    /**
     * Get payment context status from CheckoutCom API
     *
     * @return string
     * @throws Exception
     */
    private function getPaymentContextStatus(): string
    {
        $contextId = $this->getContextIdFromRequest();
        if (empty($contextId)) {
            throw new Exception('Context ID is required');
        }

        $secretKey = $this->getSecretKey();
        if (empty($secretKey)) {
            throw new Exception('CheckoutCom API configuration not found');
        }

        $apiUrl = $this->getApiUrl();

        $this->curl->addHeader('Authorization', 'Bearer ' . $secretKey);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->get($apiUrl . '/payment-contexts/' . $contextId);

        $response = $this->curl->getBody();
        $statusCode = $this->curl->getStatus();

        if ($statusCode !== 200) {
            throw new Exception("API request failed with status code: {$statusCode}, Response: {$response}");
        }

        if (empty($response)) {
            throw new Exception('Empty response from CheckoutCom API');
        }

        $responseData = $this->serializer->unserialize($response);

        if (!isset($responseData['status'])) {
            throw new Exception('Status field missing from API response: ' . $response);
        }

        return $responseData['status'];
    }


    /**
     * Get CheckoutCom API secret key
     *
     * @return string|null
     */
    private function getSecretKey(): ?string
    {
        $accountKeys = $this->config->getAccountKeys();
        return $accountKeys['secret_key'] ?? '';
    }


    /**
     * Get CheckoutCom API URL based on environment
     *
     * @return string
     */
    private function getApiUrl(): string
    {
        $environment = $this->config->getValue('environment');

        return ($environment === '1')
            ? 'https://api.sandbox.checkout.com'
            : 'https://api.checkout.com';
    }


    /**
     * Extract context ID from request
     *
     * @return string|null
     */
    private function getContextIdFromRequest(): ?string
    {
        /** @var Http $request */
        $request = $this->request;
        $requestData = $this->serializer->unserialize($request->getContent() ?: '{}');
        return $requestData['context_id'] ?? '';
    }
}
