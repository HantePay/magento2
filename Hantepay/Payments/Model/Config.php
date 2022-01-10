<?php
namespace Hantepay\Payments\Model;

class Config
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfigInterface;
    protected $customerSession;
    protected $storeManager;

    public function __construct(
    \Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
    \Magento\Customer\Model\Session $customerSession,
    \Magento\Backend\Model\Session\Quote $sessionQuote,
    \Magento\Framework\Serialize\Serializer\Json $serialize,
    \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->_scopeConfigInterface = $configInterface;
        $this->customerSession = $customerSession;
        $this->sessionQuote = $sessionQuote;
        $this->serialize = $serialize;
        $this->storeManager = $storeManager;
    }



    public function debugMode($code)
    {
        return !!$this->_scopeConfigInterface->getValue('payment/'. $code .'/debug');
    }


    public function getSitecodes()
    {
        $sitecodeConfig = $this->_scopeConfigInterface->getValue('payment/hantepay_payments_card/sitecodes');
        if ($sitecodeConfig) {
            $siteCodes = $this->serialize->unserialize($sitecodeConfig);
            if (is_array($siteCodes)) {
                return $siteCodes;
            }
        }
        return false;
    }

    /**
     * Get Use RMB Amount
     *
     * @return string
     */
    public function getUseRmbAmount()
    {
        return $this->_scopeConfigInterface->getValue('payment/hantepay_payments_card/use_rmb_amount');
    }

    public function getMerchantNumber()
    {
        return $this->_scopeConfigInterface->getValue('payment/hantepay_payments_card/merchant_number');
    }

    public function getToken()
    {
        return $this->_scopeConfigInterface->getValue('payment/hantepay_payments_card/token');
    }

    public function getStoreNumber()
    {
        return $this->_scopeConfigInterface->getValue('payment/hantepay_payments_card/store_number');
    }

}