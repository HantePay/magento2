<?php
namespace Hantepay\Payments\Model\Methods;

class Unionpay extends HantepayPayments {

	protected $_code = 'hantepay_payments_unionpay';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_isGateway = true;

    protected function myvendor(){
    	return "unionpay";
    }

    public function getImageUrl(){
        return "Hantepay_Payments/images/hantepay_unionpay/logo_en_US.png";
    }
}
