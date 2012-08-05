<?php

class Mageful_Wepay_Block_Form_Wepay extends Mage_Payment_Block_Form {

	protected function _toHtml() {
		return parent::_toHtml() . '<ul id="payment_form_wepay" class="form-list" style="display:none;">
<li class="form-alt">You will be redirected to WePay to complete the transaction.</li>
</ul>';
	}

}

