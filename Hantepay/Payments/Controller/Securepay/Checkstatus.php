<?php

namespace Hantepay\Payments\Controller\Securepay;

use Magento\Customer\Model\Session;
use Magento\Framework\Controller\ResultFactory;
use Magento\Braintree\Model\PaymentMethod\PayPal;

class Checkstatus extends Apm
{
    public function execute() {
        
         return $result->setData([
            'success' => true
        ]);

    }
}
