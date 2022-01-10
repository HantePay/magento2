<?php

namespace Hantepay\Payments\Controller\Securepay;

use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;


class Callback extends Apm
{
    public function execute()
    {
    	$this->_debug("enter callback");
        $url = $_SERVER['QUERY_STRING'];
        parse_str(urldecode($url),$query_arr);
        if(isset($query_arr['trade_status']) && $query_arr['trade_status']=='success'){
        	$this->_debug("Transaction successful");
            $incrementId = $this->checkoutSession->getLastRealOrderId();
            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            $quoteId = $order->getQuoteId();
            $this->checkoutSession->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);
    	    $this->_redirect('checkout/onepage/success');
        }
    }
}
