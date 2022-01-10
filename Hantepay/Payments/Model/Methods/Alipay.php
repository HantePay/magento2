<?php
namespace Hantepay\Payments\Model\Methods;

class Alipay extends HantepayPayments {

	protected $_code = 'hantepay_payments_alipay';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_isGateway = true;

    protected function myvendor(){
    	return "alipay";
    }

    public function getImageUrl(){
        return "Hantepay_Payments/images/hantepay_alipay/logo_en_US.png";
    }
}
