<?php

require_once Mage::getBaseDir('lib') . DS . 'wepay' . DS . 'wepay.php';

class Mageful_Wepay_Model_Wepay extends Mage_Payment_Model_Method_Abstract {
	const ADD_INFO_CHECKOUT_ID_KEY = 'wepay_checkout_id';

	const STATE_AUTHORIZED = 'authorized';
	const STATE_CAPTURED = 'captured';
	const STATE_REFUNDED = 'refunded';
	const STATE_CANCELLED = 'cancelled';

	protected $_formBlockType = 'wepay/form_wepay';
	protected $_code = 'wepay';

	/**
	 * Availability options
	 */
	protected $_isGateway = true;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = false;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;
	protected $_canUseInternal = true;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = false;
	protected $_canSaveCc = false;

	public function getCheckoutRedirectUrl() {

		return Mage::getUrl('wepay/api/start');
	}

	// authorization should have already happened at redirect time
	// we will just verify that the current amount is the authorized amount
	public function authorize(Varien_Object $payment, $amount) {

		$error = false;

		if ($amount > 0) {

			$checkout_id = $payment->getAdditionalInformation(self::ADD_INFO_CHECKOUT_ID_KEY);

			try {

				$this->initWepay();
				$wepay = new Wepay($this->getConfigData('access_token'));
				$info = $wepay->request('checkout', array('checkout_id' => $checkout_id));

				if (floatval($info->gross) != floatval($amount)) {
					$error = 'An error occurred while processing wepay payment (m1)';
				} elseif ($info->state != self::STATE_AUTHORIZED) {
					$error = 'An error occurred while processing wepay payment (m2)';
				} else {

					$payment->setTransactionId($info->checkout_id)
						   ->setIsTransactionClosed(false)
						   ->setLastTransId($info->checkout_id);
				}
			} catch (Excaption $e) {
				$error = 'An error occurred while processing wepay payment (m3)';
				Mage::logException($e);
			}
		} else {
			$error = Mage::helper('paygate')->__('Invalid amount for authorization.');
		}

		if ($error !== false) {
			Mage::throwException($error);
		}

		return $this;
	}

	public function capture(Varien_Object $payment, $amount) {

		$error = false;

		$checkout_id = $payment->getAdditionalInformation(self::ADD_INFO_CHECKOUT_ID_KEY);

		try {

			$this->initWepay();
			$wepay = new Wepay($this->getConfigData('access_token'));
			$info = $wepay->request('checkout/capture', array('checkout_id' => $checkout_id));

			if ($info->state != self::STATE_CAPTURED) {
				$error = 'An error occurred while processing wepay capture.';
			}
		} catch (Excaption $e) {
			$error = $e->getMessage();
		}

		if ($error !== false) {
			Mage::throwException($error);
		}

		return $this;
	}

	public function refund(Varien_Object $payment, $amount) {

		$error = false;

		$checkout_id = $payment->getAdditionalInformation(self::ADD_INFO_CHECKOUT_ID_KEY);

		try {

			$this->initWepay();
			$wepay = new Wepay($this->getConfigData('access_token'));
			// cannot send amount if the refund is for the full amount
			if ($payment->getAmountAuthorized() == $amount) {
				$request = array('checkout_id' => $checkout_id,
				    'refund_reason' => 'A credit memo was created by magento.',
					   );
			} else {
				$request = array('checkout_id' => $checkout_id,
				    'refund_reason' => 'A credit memo was created by magento.',
				    'amount' => $amount,
					   );
			}

			$info = $wepay->request('checkout/refund', $request);

			// disabled status check -- partial refunds cause state to stay captured, not refunded
//			if ($info->state != self::STATE_REFUNDED) {
//				$error = 'An error occurred while processing wepay refund.';
//			}
		} catch (Excaption $e) {
			$error = $e->getMessage();
		}

		if ($error !== false) {
			Mage::throwException($error);
		}

		return $this;
	}

	public function void(Varien_Object $payment) {

		$error = false;

		$checkout_id = $payment->getAdditionalInformation(self::ADD_INFO_CHECKOUT_ID_KEY);

		try {

			$this->initWepay();
			$wepay = new Wepay($this->getConfigData('access_token'));
			$info = $wepay->request('checkout/cancel', array('checkout_id' => $checkout_id,
			    'cancel_reason' => 'A void was created by magento.',
				   ));

			if ($info->state != self::STATE_CANCELLED) {
				$error = 'An error occurred while processing wepay cancellation.';
			}
		} catch (Excaption $e) {
			$error = $e->getMessage();
		}

		if ($error !== false) {
			Mage::throwException($error);
		}

		return $this;
	}

	protected function initWepay() {

		if ($this->getConfigData('testmode') == 1) {
			Wepay::useStaging($this->getConfigData('client_id'), $this->getConfigData('password'));
		} else {
			Wepay::useProduction($this->getConfigData('client_id'), $this->getConfigData('password'));
		}
	}

}
