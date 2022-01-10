define(
    [
        'jquery',
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function ($,
              Component,
              rendererList) {
        'use strict';

        var apmComponent = 'Hantepay_Payments/js/view/payment/method-renderer/apm';
     

        var methods = [
             {type: 'hantepay_payments_alipay', component: apmComponent},
             {type: 'hantepay_payments_wechatpay', component: apmComponent},
             {type: 'hantepay_payments_unionpay', component: apmComponent},
             {type: 'hantepay_payments_creditcard', component: apmComponent},
        ];
        $.each(methods, function (k, method) {
            rendererList.push(method);
        });

        return Component.extend({

        });
    }
);