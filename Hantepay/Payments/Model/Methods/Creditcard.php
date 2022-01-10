<?php
namespace Hantepay\Payments\Model\Methods;

class Creditcard extends HantepayPayments {

	protected $_code = 'hantepay_payments_creditcard';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_isGateway = true;

    protected function myvendor(){
    	return "creditcard";
    }

    public function getImageUrl(){
        return "Hantepay_Payments/images/hantepay_creditcard/logo_en_US.png";
    }
}
