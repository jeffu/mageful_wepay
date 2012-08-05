<?php

require_once Mage::getBaseDir('lib') . DS . 'wepay' . DS . 'wepay.php';

class Mageful_Wepay_ApiController extends Mage_Core_Controller_Front_Action {

	protected $_wepay = null;

	public function startAction() {

		$this->initWepay();

		try {
			$wepay = new Wepay($this->getConfig('access_token'));

			$quote = $this->getSession()->getQuote();
			$payment = $quote->getPayment();
			$shipping = $quote->getShippingAddress();
			$billing = $quote->getBillingAddress();

			$quote->reserveOrderId()->save();

			$region = Mage::getSingleton('directory/region');

			$state = $region->loadByName($billing->getRegion(), $billing->getCountryId())->getCode();
			$addr_obj = new stdClass();
			$addr_obj->name = $billing->getName();
			$addr_obj->email = $quote->getCustomerEmail();
			$addr_obj->phone_number = $billing->getTelephone();
			$addr_obj->address = $billing->getSteetFull();
			$addr_obj->city = $billing->getCity();
			$addr_obj->state = $state;
			$addr_obj->zip = $billing->getPostcode();


			$amount = floatval(number_format($quote->getBaseGrandTotal(), 2, '.', ''));

			$checkout = $wepay->request('/checkout/create', array(
			    'account_id' => $this->getConfig('account_id'),
			    'amount' => $amount,
			    'short_description' => "Order Number: " . $quote->getReservedOrderId(),
			    'type' => "GOODS",
			    'mode' => "regular",
			    'reference_id' => $quote->getId(),
			    'fee_payer' => 'Payee',
			    'redirect_uri' => Mage::getUrl('wepay/api/return'),
			    'auto_capture' => 0,
			    //'shipping_fee' => number_format($shipping->getBaseShippingAmount(), 2, '.', ''),
			    'prefill_info' => $addr_obj,
				   )
			);

			$payment->setAdditionalInformation(Mageful_Wepay_Model_Wepay::ADD_INFO_CHECKOUT_ID_KEY, $checkout->checkout_id)->save();

			$this->getResponse()->setRedirect($checkout->checkout_uri);
			return;
		} catch (Exception $e) {
			Mage::getSingleton('checkout/session')->addError($e->getMessage());
		}

		$this->_redirect('checkout/cart');
	}

	public function returnAction() {

		$this->initWepay();

		$checkout_id = $this->getRequest()->getParam('checkout_id');

		$quote = $this->getSession()->getQuote();

		$quote->collectTotals();
		$payment = $quote->getPayment();

		// check returned checkout id with session's saved checkout id
		if ($checkout_id != $payment->getAdditionalInformation(Mageful_Wepay_Model_Wepay::ADD_INFO_CHECKOUT_ID_KEY)) {
			Mage::getSingleton('checkout/session')->addError('An error occurred while connecting to your wepay payment. (1)');
			Mage::log('Incorrect wepay callback. Checkout ID mismatch: quote_id: ' . $quote->getId() . ' payment_id:  ' . $payment->getId() . 'param: ' . $checkout_id . ' quote: ' . $payment->getAdditionalInformation(Mageful_Wepay_Model_Wepay::ADD_INFO_CHECKOUT_ID_KEY));
			$this->_redirect('checkout/cart');
			return;
		}

		try {
			// get wepay info to confirm
			$wepay = new Wepay($this->getConfig('access_token'));
			$info = $wepay->request('checkout', array('checkout_id' => $checkout_id));

			if ($info->reference_id != $quote->getId()) {
				Mage::getSingleton('checkout/session')->addError('An error occurred while connecting to your wepay payment. (2)');
				$this->_redirect('checkout/cart');
				return;
			}


			$service = Mage::getModel('sales/service_quote', $quote);
			$service->submitAll();
			$quote->save();

			$order = $service->getOrder();
			if (!$order) {
				Mage::throwException('An error occurred while connecting to your wepay payment. (3)');
			}

			$this->getSession()->setLastQuoteId($quote->getId())
				   ->setLastSuccessQuoteId($quote->getId())
				   ->setLastOrderId($order->getId());
			
			switch ($order->getState()) {
				// even after placement paypal can disallow to authorize/capture, but will wait until bank transfers money
				case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT:
					// TODO
					break;
				// regular placement, when everything is ok
				case Mage_Sales_Model_Order::STATE_PROCESSING:
				case Mage_Sales_Model_Order::STATE_COMPLETE:
				case Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW:
					$order->sendNewOrderEmail();
					break;
			}

			$this->getResponse()->setRedirect(Mage::getUrl('checkout/onepage/success'));
			return;
		} catch (Exception $e) {
			Mage::getSingleton('checkout/session')->addError($e->getMessage());
			Mage::logException($e);
		}

		$this->_redirect('checkout/cart');
	}

	protected function initWepay() {

		if ($this->getConfig('testmode') == 1) {
			Wepay::useStaging($this->getConfig('client_id'), $this->getConfig('password'));
		} else {
			Wepay::useProduction($this->getConfig('client_id'), $this->getConfig('password'));
		}
	}

	protected function getSession() {
		return Mage::getSingleton('checkout/session');
	}

	protected function getOnePage() {
		return Mage::getSingleton('checkout/type_onepage');
	}

	protected function getConfig($path, $storeId = null) {
		$path = 'payment/wepay/' . $path;
		return Mage::getStoreConfig($path, $storeId);
	}

	public function getCheckoutMethod() {
		if (Mage::getSingleton('customer/session')->isLoggedIn()) {
			return Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER;
		}
		if (!$this->_quote->getCheckoutMethod()) {
			if (Mage::helper('checkout')->isAllowedGuestCheckout($this->_quote)) {
				$this->_quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
			} else {
				$this->_quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
			}
		}
		return $this->_quote->getCheckoutMethod();
	}

}