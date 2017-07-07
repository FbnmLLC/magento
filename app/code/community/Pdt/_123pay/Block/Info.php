<?php

class Pdt__123pay_Block_Info extends Mage_Payment_Block_Info {
	protected function _construct() {
		parent::_construct();
		$this->setTemplate( '_123pay/info.phtml' );
	}

	public function getMethodCode() {
		return $this->getInfo()->getMethodInstance()->getCode();
	}

	public function toPdf() {
		$this->setTemplate( '_123pay/pdf/info.phtml' );

		return $this->toHtml();
	}
}