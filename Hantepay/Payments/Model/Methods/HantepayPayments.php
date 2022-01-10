<?php

namespace Hantepay\Payments\Model\Methods;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Exception\LocalizedException;
use Hantepay\Payments\Model\Requestor;
use Hantepay\Payments\Model\Source\SettlementCurrency;

class HantepayPayments extends AbstractMethod
{
    protected $_isInitializeNeeded = true;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $backendAuthSession;
    protected $cart;
    protected $urlBuilder;
    protected $_objectManager;
    protected $invoiceSender;
    protected $transactionFactory;
    protected $customerSession;

    protected $checkoutSession;
    protected $checkoutData;
    protected $quoteRepository;
    protected $quoteManagement;
    protected $orderSender;
    protected $sessionQuote;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \Hantepay\Payments\Model\Config $config,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Helper\Data $checkoutData,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Backend\Model\Session\Quote $sessionQuote,
        \Hantepay\Payments\Logger\Logger $wpLogger,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->urlBuilder = $urlBuilder;
        $this->backendAuthSession = $backendAuthSession;
        $this->config = $config;
        $this->cart = $cart;
        $this->_objectManager = $objectManager;
        $this->invoiceSender = $invoiceSender;
        $this->transactionFactory = $transactionFactory;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->checkoutData = $checkoutData;
        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->orderSender = $orderSender;
        $this->sessionQuote = $sessionQuote;
        $this->logger = $wpLogger;
        $this->order = $order;
    }

    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }

    public function getOrderPlaceRedirectUrl()
    {
        return $this->urlBuilder->getUrl('hantepay/securepay/redirect', ['_secure' => true]);
    }

    public function getAjaxCheckStatus()
    {
        return $this->urlBuilder->getUrl('hantepay/securepay/checkstatus', ['_secure' => true]);
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $_tmpData = $data->_data;
        $infoInstance = $this->getInfoInstance();
        return $this;
    }

    public function createApmOrder($quote, $reference)
    {
        $orderId = $quote->getReservedOrderId();
        $payment = $quote->getPayment();
        $amount = $quote->getGrandTotal();
        $currency_code = $quote->getQuoteCurrencyCode();
        $orderDetails = $this->getSharedOrderDetails($quote, $currency_code);

        try {

            $merchantNo = $this->config->getMerchantNumber();
            $storeNo = $this->config->getStoreNumber();
            $token = $this->config->getToken();
            $nonceStr = md5(uniqid(microtime(true),true));
            $time = time().substr(microtime(),2,3);
            $ipn = $this->urlBuilder->getUrl('hantepay/securepay/ipn', ['_secure' => true]);
            $callback = $this->urlBuilder->getUrl('hantepay/securepay/callback', ['_secure' => true]);

            $vendor = $this->myvendor();

            $params = array(
                "merchant_no"     => $merchantNo,
                "store_no"        => $storeNo,
                "sign_type"       => 'MD5',
                "nonce_str"       => $nonceStr,
                "time"            => $time,
                "out_trade_no"    => $reference,
                "currency"        => "USD",
                "payment_method"  => $vendor,
                "notify_url"      => $ipn,
                "callback_url"    => $callback,
                "terminal"        => $this->ismobile() ? 'WAP' : 'ONLINE',
                "body"            => sprintf('#%s(%s)', $orderId, $orderDetails['shopperEmailAddress']),
                "note"            => $orderDetails['orderDescription'],
            );

            if($orderDetails['currencyCode'] == "CNY"){
                $params['rmb_amount']=intval(strval($amount * 100));
            }else{
                if($orderDetails['currencyCode'] == 'JPY'){
                    $params['amount']=intval(strval($amount));
                }else{
                    $params['amount']=intval(strval($amount * 100));
                }
            }

            $signature =  $this->getSignature($params,$token);

            $params['signature'] = $signature;

            $this->_debug(json_encode($params));

            $requestor = new Requestor();

            $response = $requestor->getSecureForm(json_encode($params));

            $result = json_decode($response);

            if($result->return_code == "ok" && $result->result_code == "SUCCESS"){
                $url = $result->data->pay_url;
                header("Location: $url");
            }else{
                echo $response;
            }
        } catch (\Exception $e) {

            $payment->setStatus(self::STATUS_ERROR);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);
            $this->_debug($e->getMessage());
            throw new \Exception('Payment failed, please try again later ' . $e->getMessage());
        }
    }

    function ismobile()
    {
        $is_mobile = '0';

        if (preg_match('/(android|up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
            $is_mobile = 1;
        }

        if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/vnd.wap.xhtml+xml') !== false) or ((isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))) {
            $is_mobile = 1;
        }

        $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
        $mobile_agents = array('w3c ', 'acs-', 'alav', 'alca', 'amoi', 'andr', 'audi', 'avan', 'benq', 'bird', 'blac', 'blaz', 'brew', 'cell', 'cldc', 'cmd-', 'dang', 'doco', 'eric', 'hipt', 'inno', 'ipaq', 'java', 'jigs', 'kddi', 'keji', 'leno', 'lg-c', 'lg-d', 'lg-g', 'lge-', 'maui', 'maxo', 'midp', 'mits', 'mmef', 'mobi', 'mot-', 'moto', 'mwbp', 'nec-', 'newt', 'noki', 'oper', 'palm', 'pana', 'pant', 'phil', 'play', 'port', 'prox', 'qwap', 'sage', 'sams', 'sany', 'sch-', 'sec-', 'send', 'seri', 'sgh-', 'shar', 'sie-', 'siem', 'smal', 'smar', 'sony', 'sph-', 'symb', 't-mo', 'teli', 'tim-', 'tosh', 'tsm-', 'upg1', 'upsi', 'vk-v', 'voda', 'wap-', 'wapa', 'wapi', 'wapp', 'wapr', 'webc', 'winw', 'winw', 'xda', 'xda-');

        if (in_array($mobile_ua, $mobile_agents)) {
            $is_mobile = 1;
        }

        if (isset($_SERVER['ALL_HTTP'])) {
            if (strpos(strtolower($_SERVER['ALL_HTTP']), 'OperaMini') !== false) {
                $is_mobile = 1;
            }
        }

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false) {
            $is_mobile = 0;
        }

        return $is_mobile;
    }

    protected function myvendor()
    {
        return "";
    }

    public function isTokenAllowed()
    {
        return true;
    }

    public function capture(InfoInterface $payment, $amount)
    {
        return $this;
    }

    public function authorize(InfoInterface $payment, $amount)
    {
        return $this;
    }

    public function refund(InfoInterface $payment, $amount)
    {
        $this->_debug("call refund");

        if ($order = $payment->getOrder()) {

            try {

                $transactionId = $payment->getParentTransactionId();

                $order = $payment->getOrder();

                $merchantNo = $this->config->getMerchantNumber();

                $storeNo = $this->config->getStoreNumber();

                $token = $this->config->getToken();

                $requestor = new Requestor();

                $currencyKey = "refund_amount";

                if ($order->getOrderCurrencyCode() == "CNY") {
                    $currencyKey = "refund_rmb_amount";
                }

                $time = time().substr(microtime(),2,3);

                $nonceStr = md5(uniqid(microtime(true),true));

                $params = array($currencyKey=>$amount*100
                ,'currency'=>'USD'
                ,'merchant_no'=> $merchantNo
                ,'store_no'=> $storeNo
                ,'sign_type'=>'MD5'
                ,'nonce_str' => $nonceStr
                ,'time' =>$time
                ,'transaction_id'=> $transactionId
                ,'refund_no'=>$requestor->getReferenceCode($order->getOrderId())
                ,'refund_desc'=>'Magento refund'
                );

                $signature =  $this->getSignature($params,$token);

                $params['signature'] = $signature;

                $this->_debug(json_encode($params));

                $ret = $requestor->refund(json_encode($params));

                $this->_debug("leave call refund");

                return $this;
            } catch (\Exception $e) {
                $this->_debug("call refund fail");
                $a = $e->getMessage();
                throw new LocalizedException(__('Refund failed ' . $e->getMessage()));
            }
        }
    }

    public function void(InfoInterface $payment)
    {
        return true;
    }

    public function cancel(InfoInterface $payment)
    {
        $this->_debug("call cancel action");
        throw new LocalizedException(__('You cannot cancel an APM order'));
    }

    private function getCheckoutMethod($quote)
    {
        if ($this->customerSession->isLoggedIn()) {
            return \Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER;
        }
        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutData->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_REGISTER);
            }
        }
        return $quote->getCheckoutMethod();
    }

    public function readyMagentoQuote()
    {
        $quote = $this->checkoutSession->getQuote();

        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);
        if ($this->getCheckoutMethod($quote) == \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST) {
            $quote->setCustomerId(null)
                ->setCustomerEmail($quote->getBillingAddress()->getEmail())
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        }

        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$quote->getIsVirtual()) {
            $quote->getShippingAddress()->setShouldIgnoreValidation(true);
            if (!$quote->getBillingAddress()->getEmail()
            ) {
                $quote->getBillingAddress()->setSameAsBilling(1);
            }
        }

        $quote->collectTotals();

        return $quote;
    }

    public function createMagentoOrder($quote)
    {
        try {
            $order = $this->quoteManagement->submit($quote);
            return $order;
        } catch (\Exception $e) {
            $orderId = $quote->getReservedOrderId();
            $payment = $quote->getPayment();
            $token = $payment->getAdditionalInformation('payment_token');
            $amount = $quote->getGrandTotal();
            $payment->setStatus(self::STATUS_ERROR);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);
            $this->_debug($e->getMessage());

            $this->checkoutSession->restoreQuote();

            throw new \Exception($e->getMessage());
        }
    }

    public function sendMagentoOrder($order)
    {
        $this->checkoutSession->start();

        $this->checkoutSession->clearHelperData();

        $this->checkoutSession->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());
    }

    protected function _debug($debugData)
    {
        if ($this->config->debugMode($this->_code)) {
            $this->logger->debug($debugData);
        }
    }

    protected function getSharedOrderDetails($quote, $currencyCode)
    {

        $items = $quote->getAllItems();

        $data = [];
        $data['currencyCode'] = $currencyCode;


        $Product = '';

        foreach ($items as $item) {
            $Product = $item->getName() . '...';
            break;
        }
        $data['orderDescription'] = $Product;


        if ($this->backendAuthSession->isLoggedIn()) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($this->sessionQuote->getCustomerId());
            $data['shopperEmailAddress'] = $customer->getEmail();
        } else {
            $data['shopperEmailAddress'] = $this->customerSession->getCustomer()->getEmail();
        }

        return $data;
    }

    public function getSignature($data,$token) {
        if(array_key_exists('sign_type',$data)){
            unset($data['sign_type']);
        }
        ksort($data);
        $string=$this->formatUrlParams($data).'&'.$token;
        $this->_debug($string);
        $string=md5($string,false);
        return  strtolower($string);
    }

    private function formatUrlParams(array $data) {
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "signature" && $v !== "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

}
