<?php

class Pdt_ir123pay_Block_Form extends Mage_Payment_Block_Form {
	protected function _construct() {
		parent::_construct();
		$this->setTemplate( 'ir123pay/form.phtml' );
	}

	public function getPaymentImageSrc() {
		$locale = strtolower( Mage::app()->getLocale()->getLocaleCode() );
		$imgSrc = $this->getSkinUrl( 'images/ir123pay/' . $locale . '_outl.gif' );

		if ( ! file_exists( Mage::getDesign()->getSkinBaseDir() . '/images/ir123pay/' . $locale . '_outl.gif' ) ) {
			$imgSrc = $this->getSkinUrl( 'images/ir123pay/intl_outl.gif' );
		}

		return $imgSrc;
	}
}