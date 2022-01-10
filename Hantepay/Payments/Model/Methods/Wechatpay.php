<?php

namespace Hantepay\Payments\Model\Methods;



class Wechatpay extends HantepayPayments {

	protected $_code = 'hantepay_payments_wechatpay';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_isGateway = true;

    protected function myvendor(){
    	return "wechatpay";
    }

    public function getImageUrl(){
        return "Hantepay_Payments/images/hantepay_wechatpay/logo_en_US.png";
    }
}
