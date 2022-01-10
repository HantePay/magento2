<?php

namespace Hantepay\Payments\Controller\Securepay;
use Magento\Payment\Helper\Data as PaymentHelper;

abstract class Apm extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    protected $resultJsonFactory;

    protected $orderFactory;
    protected $logger;
    protected $methods = [];

    protected $wordpayPaymentsCard;

    protected $methodCodes = [
        'hantepay_payments_alipay','hantepay_payments_wechatpay','hantepay_payments_unionpay','hantepay_payments_creditcard'
    ];

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        PaymentHelper $paymentHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Helper\Data $checkoutData,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Hantepay\Payments\Logger\Logger $wpLogger,
        \Hantepay\Payments\Model\Config $config,
        $params = []
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->orderSender = $orderSender;
        
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->resultJsonFactory = $resultJsonFactory;
         $this->logger = $wpLogger;
         $this->config = $config;
        parent::__construct($context);
    }

    protected function _debug($debugData)
    {   
         $this->logger->debug($debugData);
    }
}
