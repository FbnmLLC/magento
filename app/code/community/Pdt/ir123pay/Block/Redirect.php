<?php

class Pdt_ir123pay_Block_Redirect extends Mage_Core_Block_Template {
	protected function _getCheckout() {
		return Mage::getSingleton( 'checkout/session' );
	}

	protected function _getOrder() {
		if ( $this->getOrder() ) {
			return $this->getOrder();
		} elseif ( $orderIncrementId = $this->_getCheckout()->getLastRealOrderId() ) {
			return Mage::getModel( 'sales/order' )->loadByIncrementId( $orderIncrementId );
		} else {
			return null;
		}
	}

	public function getFormData() {
		$order = $this->_getOrder()->_data;
		$array = $this->_getOrder()->getPayment()->getMethodInstance()->getFormFields();
		$price = $array["price"];

		$merchant_id = $this->_getOrder()->getPayment()->getMethodInstance()->getConfigData( 'merchant_id' );

		$len   = strlen( $price );
		$len   -= 2;
		$price = substr( $price, 0, $len );

		$params = array(
			'pin'       => $merchant_id,
			'amount'    => $price,
			'orderId'   => $order["entity_id"],
			'authority' => 0,
			'status'    => 1
		);


		return $params;
	}

	public function getFormAction() {
		$order = $this->_getOrder()->_data;
		$array = $this->_getOrder()->getPayment()->getMethodInstance()->getFormFields();
		$price = $array["price"];

		$merchant_id = $this->_getOrder()->getPayment()->getMethodInstance()->getConfigData( 'merchant_id' );

		$price        = round( $order["grand_total"], 0 );
		$amount       = $price;
		$callBackUrl  = Mage::getBaseUrl( Mage_Core_Model_Store::URL_TYPE_LINK );
		$callBackUrl  .= "ir123pay/processing/response/";
		$callback_url = urlencode( $callBackUrl );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/create/payment' );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&amount=$amount&callback_url=$callback_url" );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$response = curl_exec( $ch );
		curl_close( $ch );

		$result = json_decode( $response );

		if ( $result->status ) {
			@session_start();
			$_SESSION['RefNum']   = $result->RefNum;
			$_SESSION['order_id'] = $order["entity_id"];
			$return               = $result->payment_url;
		} else {
			Mage::log( 'ir123pay ERR: ' . $result->message );
			echo $result->message;
		}

		return $return;
	}
}