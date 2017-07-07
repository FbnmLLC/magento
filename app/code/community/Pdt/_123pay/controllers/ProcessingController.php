<?php

class Pdt__123pay_ProcessingController extends Mage_Core_Controller_Front_Action {
	protected $_successBlockType = '_123pay/success';
	protected $_failureBlockType = '_123pay/failure';

	protected $_order = null;
	protected $_paymentInst = null;

	protected function _getCheckout() {
		return Mage::getSingleton( 'checkout/session' );
	}

	public function mkzero( $z ) {
		$str = "1";
		while ( $z > 0 ) {
			$str .= "0";
			$z   -= 1;
		}

		return $str;
	}

	public function redirectAction() {
		try {
			$session = $this->_getCheckout();

			$order = Mage::getModel( 'sales/order' );
			$order->loadByIncrementId( $session->getLastRealOrderId() );
			if ( ! $order->getId() ) {
				Mage::throwException( 'No order for processing found' );
			}
			if ( $order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT ) {
				$order->setState(
					Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
					$this->_getPendingPaymentStatus(),
					Mage::helper( '_123pay' )->__( 'Customer was redirected to 123pay gateway.' )
				)->save();
			}

			if ( $session->getQuoteId() && $session->getLastSuccessQuoteId() ) {
				$session->setClickandbuyQuoteId( $session->getQuoteId() );
				$session->setClickandbuySuccessQuoteId( $session->getLastSuccessQuoteId() );
				$session->setClickandbuyRealOrderId( $session->getLastRealOrderId() );
				$session->getQuote()->setIsActive( false )->save();
				$session->clear();
			}

			$this->loadLayout();
			$this->renderLayout();

			return;
		} catch ( Mage_Core_Exception $e ) {
			$this->_getCheckout()->addError( $e->getMessage() );
		} catch ( Exception $e ) {
			Mage::logException( $e );
		}

	}

	public function responseAction() {
		$State  = $_REQUEST['State'];
		$RefNum = $_REQUEST['RefNum'];
		@session_start();
		if ( $State == 'OK' && $RefNum > 0 && $_SESSION['RefNum'] == $RefNum ) {

			$session = $this->_getCheckout();

			$orderid = $session->getClickandbuyRealOrderId();

			$this->_order       = Mage::getModel( 'sales/order' )->loadByIncrementId( $orderid );
			$this->_paymentInst = $this->_order->getPayment()->getMethodInstance();

			$quote = $orderid = $this->_order->getData();
			$price = $quote["grand_total"];

			$merchant_id = $this->_paymentInst->getConfigData( 'merchant_id' );
			$ch          = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/verify/payment' );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&RefNum=$RefNum" );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			$response = curl_exec( $ch );
			curl_close( $ch );

			$result = json_decode( $response );
			if ( $result->status ) {

				$this->_order->getPayment()->setTransactionId( $RefNum );
				$this->_order->getPayment()->setLastTransId( $RefNum );

				// create invoice
				if ( $this->_order->canInvoice() ) {
					$invoice = $this->_order->prepareInvoice();
					$invoice->register()->capture();
					Mage::getModel( 'core/resource_transaction' )
					    ->addObject( $invoice )
					    ->addObject( $invoice->getOrder() )
					    ->save();
				}

				$this->_order->addStatusToHistory( $this->_paymentInst->getConfigData( 'order_status' ), Mage::helper( '_123pay' )->__( 'Payment complete' ) );

				$this->_order->sendNewOrderEmail();
				$this->_order->setEmailSent( true );

				$this->_order->save();

				$this->getResponse()->setBody(
					$this->getLayout()
					     ->createBlock( $this->_successBlockType )
					     ->setOrder( $this->_order )
					     ->toHtml() );

			} else {
				$this->_redirect( '_123pay/processing/caberror' );
			}
		} else {
			$this->_redirect( '_123pay/processing/caberror' );
		}
	}

	public function successAction() {
		try {
			$session = $this->_getCheckout();
			$session->unsClickandbuyRealOrderId();
			$session->setQuoteId( $session->getClickandbuyQuoteId( true ) );
			$session->setLastSuccessQuoteId( $session->getClickandbuySuccessQuoteId( true ) );
			$this->_redirect( 'checkout/onepage/success' );

			return;
		} catch ( Mage_Core_Exception $e ) {
			$this->_getCheckout()->addError( $e->getMessage() );
		} catch ( Exception $e ) {
			Mage::logException( $e );
		}
		$this->_redirect( 'checkout/cart' );
	}

	public function caberrorAction() {
		$session = $this->_getCheckout();
		if ( $quoteId = $session->getClickandbuyQuoteId() ) {
			$quote = Mage::getModel( 'sales/quote' )->load( $quoteId );
			if ( $quote->getId() ) {
				$quote->setIsActive( true )->save();
				$session->setQuoteId( $quoteId );
			}
		}

		$this->getResponse()->setBody(
			$this->getLayout()
			     ->createBlock( $this->_failureBlockType )
			     ->setOrder( $this->_order )
			     ->toHtml()
		);
	}

	public function cabsuccessAction() {
		$this->caberrorAction();

	}


	protected function _checkReturnedParams() {
		$externalBDRID = $this->getRequest()->getParam( 'externalBDRID' );
		$request       = $this->getRequest()->getServer();

		if ( ! isset( $request['HTTP_X_USERID'] ) || ! isset( $request['HTTP_X_PRICE'] ) || ! isset( $request['HTTP_X_CURRENCY'] ) || ! isset( $request['HTTP_X_TRANSACTION'] ) || ! isset( $request['HTTP_X_CONTENTID'] ) || ! isset( $request['HTTP_X_USERIP'] ) ) {
			throw new Exception( 'Request doesn\'t contain all required C&B elements.', 10 );
		}

		$helper = Mage::helper( 'core/http' );
		if ( method_exists( $helper, 'getRemoteAddr' ) ) {
			$remoteAddr = $helper->getRemoteAddr();
		} else {
			$request    = $this->getRequest()->getServer();
			$remoteAddr = $request['REMOTE_ADDR'];
		}
		if ( substr( $remoteAddr, 0, 11 ) != '217.22.128.' ) {
			throw new Exception( 'IP can\'t be validated as ClickandBuy-IP.', 20 );
		}

		if ( empty( $request['HTTP_X_USERID'] ) || is_nan( $request['HTTP_X_USERID'] ) ) {
			throw new Exception( 'Invalid ClickandBuy-UID.', 30 );
		}

		list( $orderId ) = explode( '-', $externalBDRID, 2 );
		if ( empty( $orderId ) || strlen( $orderId ) > 50 ) {
			throw new Exception( 'Missing or invalid order ID', 30 );
		}

		$this->_order = Mage::getModel( 'sales/order' )->loadByIncrementId( $orderId );
		if ( ! $this->_order->getId() ) {
			throw new Exception( 'Order ID not found.', 35 );
		}

		if ( $this->_order->getPayment()->getMethodInstance()->getConfigData( 'use_store_currency' ) ) {
			$price    = number_format( $this->_order->getGrandTotal() * 100, 0, '.', '' );
			$currency = $this->_order->getOrderCurrencyCode();
		} else {
			$price    = number_format( $this->_order->getBaseGrandTotal() * 100, 0, '.', '' );
			$currency = $this->_order->getBaseCurrencyCode();
		}

		if ( intval( $price ) != intval( $request['HTTP_X_PRICE'] / 1000 ) ) {
			throw new Exception( 'Transaction amount doesn\'t match.', 40 );
		}
		if ( $currency != $request['HTTP_X_CURRENCY'] ) {
			throw new Exception( 'Transaction currency doesn\'t match.', 50 );
		}

		return $externalBDRID;
	}

	protected function _getPendingPaymentStatus() {
		return Mage::helper( '_123pay' )->getPendingPaymentStatus();
	}
}
