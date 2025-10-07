<?php
/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\CheckoutComPayment\Magewire\Payment\Method;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magebit\CheckoutComPayment\ViewModel\MbWay as Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magewirephp\Magewire\Component\Form;
use Rakit\Validation\Validator;

class CheckoutComApm extends Form implements EvaluationInterface
{
    /**
     * @var string
     */
    public string $selectedMethod = '';

    /**
     * @var string
     */
    public string $mbwayCountryCode = '';

    /**
     * @var string
     */
    public string $mbwayPhoneNumber = '';

    /**
     * @var array
     */
    public array $apmData = [];
    public const SELECTED_APM = 'checkoutcom_selected_apm';
    public const APM_DATA = 'checkoutcom_apm_data';

    /**
     * @var string[]
     */
    protected $loader = [
        'selectedMethod' => 'Updating payment method',
        'mbwayCountryCode' => 'Updating country code',
        'mbwayPhoneNumber' => 'Updating phone number',
    ];

    /**
     * @param Validator $validator
     * @param CheckoutSession $checkoutSession
     * @param Config $config
     */
    public function __construct(
        Validator $validator,
        private readonly CheckoutSession $checkoutSession,
        private readonly Config $config,
    ) {
        parent::__construct($validator);
    }

    /**
     * Initialize component with existing session data if available
     *
     * @return void
     */
    public function mount(): void
    {
        $this->selectedMethod = $this->checkoutSession->getData(self::SELECTED_APM) ?: '';
        $apmData = $this->checkoutSession->getData(self::APM_DATA) ?: [];

        if (isset($apmData['mbway'])) {
            $this->mbwayCountryCode = $apmData['mbway']['country_code'] ?? '';
            $this->mbwayPhoneNumber = $apmData['mbway']['number'] ?? '';
        }
    }

    /**
     * When APM method is selected
     *
     * @param string $method
     * @return void
     */
    public function selectMethod(string $method): void
    {
        $this->selectedMethod = $method;
        $this->checkoutSession->setData(self::SELECTED_APM, $method);
    }

    /**
     * Update MB WAY country code in session when changed
     *
     * @param string $value
     * @return string
     */
    public function updatedMbwayCountryCode(string $value): string
    {
        $this->updateApmData('mbway', 'country_code', $value);
        return $value;
    }

    /**
     * Update MB WAY phone number in session when changed
     *
     * @param string $value
     * @return string
     */
    public function updatedMbwayPhoneNumber(string $value): string
    {
        $this->updateApmData('mbway', 'number', $value);
        return $value;
    }

    /**
     * Update APM data in session
     *
     * @param string $method
     * @param string $key
     * @param $value
     * @return void
     */
    public function updateApmData(string $method, string $key, $value): void
    {
        $apmData = $this->checkoutSession->getData(self::APM_DATA) ?: [];

        if (!isset($apmData[$method])) {
            $apmData[$method] = [];
        }

        $apmData[$method][$key] = $value;
        $this->checkoutSession->setData(self::APM_DATA, $apmData);
    }

    /**
     * Get available APM methods
     *
     * @return string[]
     */
    public function getAvailableApmMethods(): array
    {
        return [
            'mbway' => 'MB WAY',
            // other APMs would be listed here
        ];
    }

    /**
     * Check if data is valid
     *
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if (empty($this->selectedMethod)) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('You need to select a payment method.');
        }

        if ($this->selectedMethod === 'mbway') {
            if (empty($this->mbwayCountryCode) || empty($this->mbwayPhoneNumber)) {
                return $resultFactory->createErrorMessageEvent()
                    ->withCustomEvent('payment:method:error')
                    ->withMessage('Please complete all required fields for MB WAY payment.');
            }

            if ($this->config->isPhoneValidationEnabled() && !$this->isNumberValid($this->mbwayPhoneNumber)) {
                return $resultFactory->createErrorMessageEvent()
                    ->withCustomEvent('payment:method:error')
                    ->withMessage('Please enter a valid phone number. It should be 9 digits and start with 9.');
            }
        }

        return $resultFactory->createSuccess();
    }

    /**
     * Validate Portuguese phone number
     *
     * @param $phoneNumber
     * @return bool
     */
    public function isNumberValid($phoneNumber): bool
    {
        return strlen($phoneNumber) === 9 && str_starts_with($phoneNumber, '9');
    }
}
