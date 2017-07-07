<?php

class Pdt__123pay_Block_Failure extends Mage_Core_Block_Template {
	protected function _construct() {
		parent::_construct();
		$this->setTemplate( '_123pay/failure.phtml' );
	}

	public function getContinueShoppingUrl() {
		return Mage::getUrl( 'checkout/cart' );
	}
}