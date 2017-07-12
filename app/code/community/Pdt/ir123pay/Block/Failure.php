<?php

class Pdt_ir123pay_Block_Failure extends Mage_Core_Block_Template {
	protected function _construct() {
		parent::_construct();
		$this->setTemplate( 'ir123pay/failure.phtml' );
	}

	public function getContinueShoppingUrl() {
		return Mage::getUrl( 'checkout/cart' );
	}
}