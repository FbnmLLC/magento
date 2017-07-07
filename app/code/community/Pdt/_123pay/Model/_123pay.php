<?php

/**
 * Magento
 * @category   Payment
 * @package    Pdt__123pay
 * @copyright  Copyright (c) 2013 123pay Development Team
 * @see http://123pay.ir
 */
class Pdt__123pay_Model__123pay extends Mage_Payment_Model_Method_Abstract {

	protected $_code = '_123pay';

	protected $_formBlockType = '_123pay/form';
	protected $_infoBlockType = '_123pay/info';

	protected $_isGateway = false;
	protected $_canAuthorize = false;
	protected $_canCapture = false;
	protected $_canCapturePartial = false;
	protected $_canRefund = false;
	protected $_canVoid = false;
	protected $_canUseInternal = false;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = false;

	protected $_order;

	public function getOrder() {
		if ( ! $this->_order ) {
			$paymentInfo  = $this->getInfoInstance();
			$this->_order = Mage::getModel( 'sales/order' )
			                    ->loadByIncrementId( $paymentInfo->getOrder()->getRealOrderId() );
		}

		return $this->_order;
	}

	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl( '_123pay/processing/redirect', array( '_secure' => true ) );
	}

	public function capture( Varien_Object $payment, $amount ) {
		$payment->setStatus( self::STATUS_APPROVED )
		        ->setLastTransId( $this->getTransactionId() );

		return $this;
	}

	public function getPaymentMethodType() {
		return $this->_paymentMethod;
	}

	public function getUrl() {
		$premiumLink = $this->getConfigData( 'premium_link' );
		if ( empty( $premiumLink ) ) {
			return '';
		}

		if ( substr( $premiumLink, - 1 ) != '/' ) {
			$premiumLink .= '/';
		}
		$pStartPos   = strpos( $premiumLink, '://premium' );
		$pEndPos     = strpos( $premiumLink, '/', $pStartPos + 3 );
		$premiumPart = substr( $premiumLink, 0, $pEndPos + 1 );

		preg_match( '/^http[s]?:\/\/[a-z0-9._-]*\/(.*)$/i', Mage::getUrl( '_123pay/processing/response', array( '_secure' => true ) ), $matches );
		$url = $premiumPart . $matches[1];

		return $url;
	}

	public function getFormFields() {
		if ( $this->getConfigData( 'use_store_currency' ) ) {
			$price    = number_format( $this->getOrder()->getGrandTotal() * 100, 0, '.', '' );
			$currency = $this->getOrder()->getOrderCurrencyCode();
		} else {
			$price    = number_format( $this->getOrder()->getBaseGrandTotal() * 100, 0, '.', '' );
			$currency = $this->getOrder()->getBaseCurrencyCode();
		}

		$params = array(
			'price'               => $price,
			'cb_currency'         => $currency,
			'cb_content_name_utf' => Mage::helper( '_123pay' )->__( 'Your purchase at' ) . ' ' . Mage::app()->getStore()->getName(),
			'externalBDRID'       => $this->getOrder()->getRealOrderId() . '-' . $this->getOrder()->getQuoteId(),
		);

		return $params;
	}

}