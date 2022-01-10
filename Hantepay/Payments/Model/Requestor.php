<?php
namespace Hantepay\Payments\Model;

use Hantepay\Payments\Model\Methods\HantepayPayments;

class Requestor
{

	public function __construct(){

	}


	public function refund($params){


		$httpClient = CurlClient::instance();

		$url = "https://gateway.hantepay.com/v2/gateway/refund";

        $headers = array(
            "Accept: application/json",
            "Content-Type:application/json"
        );


		list($rbody, $rcode, $rheaders) = $httpClient->request("post",$url,$headers,$params,false);

		$resp = $this->_interpretResponse($rbody, $rcode, $rheaders,$params);

		return $resp;

	}

	private function _interpretResponse($rbody, $rcode, $rheaders,$params)
    {
        try {
            $resp = json_decode($rbody, true);
        } catch (Exception $e) {
            $msg = "Invalid response body from API: $rbody "
              . "(HTTP response code was $rcode)";
            // throw new ErrorApi($msg, $rcode, $rbody);
            throw new \Exception($msg);
        }

        if ($rcode < 200 || $rcode >= 300) {
            $this->handleApiError($rbody, $rcode, $rheaders, $resp,$params);
        }
        return $resp;
    }

    public function handleApiError($rbody, $rcode, $rheaders, $resp,$param)
    {
        if (!is_array($resp) || !isset($resp['error'])) {
            $msg = "Invalid response object from API: $rbody "
              . "(HTTP response code was $rcode)";
        }

        $error = isset($resp['error']) ? $resp['error']:$rcode ;
        $msg = isset($resp['message']) ? $resp['message'] : null;
        $code = isset($error['code']) ? $error['code'] : null;

        // throw new ErrorApi($msg,$param, $rcode, $rbody, $resp, $rheaders);

        throw new \Exception($msg);

    }

	protected function log($msg)
    {
        // Mage::log("Requestor - ".$msg);
    }

    public function getSecureForm($params){

		$httpClient = CurlClient::instance();
		$url = "https://gateway.hantepay.com/v2/gateway/securepay";

		$headers = array(
            "Accept: application/json",
            "Content-Type:application/json"
        );

		list($rbody, $rcode, $rheaders) = $httpClient->request("post",$url,$headers,$params,false);

		$resp = $this->_interpretResponse($rbody, $rcode, $rheaders,$params);

		return $rbody;
    }

    function getReferenceCode($order_id){

    	$tmstemp = time();
        return $order_id . 'at' . $tmstemp;
    }

}
