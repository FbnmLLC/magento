<?php

/**
 * Magento
 * @category   Payment
 * @package    Pdt_ir123pay
 * @copyright  Copyright (c) 2013 123pay Development Team
 * @see http://123pay.ir
 */
class Pdt_ir123pay_Model_Config_Backend_Sellerid extends Mage_Core_Model_Config_Data {
	protected function _beforeSave() {
		try {
			if ( $this->getValue() ) {
				$client = new Varien_Http_Client();
				$client->setUri( (string) Mage::getConfig()->getNode( 'pdt/ir123pay/verify_url' ) )
				       ->setConfig( array( 'timeout' => 10, ) )
				       ->setHeaders( 'accept-encoding', '' )
				       ->setParameterPost( 'merchant_id', $this->getValue() )
				       ->setMethod( Zend_Http_Client::POST );
				$response = $client->request();
			}
		} catch ( Exception $e ) {
			//
		}

		return $this;
	}
}
