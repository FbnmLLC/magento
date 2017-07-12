<?php

class Pdt_ir123pay_Block_Info extends Mage_Payment_Block_Info {
	protected function _construct() {
		parent::_construct();
		$this->setTemplate( 'ir123pay/info.phtml' );
	}

	public function getMethodCode() {
		return $this->getInfo()->getMethodInstance()->getCode();
	}

	public function toPdf() {
		$this->setTemplate( 'ir123pay/pdf/info.phtml' );

		return $this->toHtml();
	}
}