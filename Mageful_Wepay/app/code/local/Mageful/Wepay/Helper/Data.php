<?php

class Mageful_Wepay_Helper_Data extends Mage_Core_Helper_Abstract {

	const WEPAY_PLUGIN_API_AUTH = 'wepay_plugin_api_auth';

	public function __() {

		$args = func_get_args();

		if ($args[0] =='{{wepay_admin_comment}}') {
			
			Mage::app()->saveCache(session_id(), self::WEPAY_PLUGIN_API_AUTH);

			$query_str = '<a href="https://stage.wepay.com/v2/plugin/create/?plugin_post_uri='
			. Mage_Adminhtml_Helper_Data::getUrl('wepay/api/admin', array('auth'=>session_id()))
				   . '&plugin_name=Magento%20Plugin&plugin_homepage='.Mage::getUrl()
				   . '&plugin_redirect_uri='.Mage_Adminhtml_Helper_Data::getUrl('adminhtml/system_config/edit', array('section'=>'payment'))
				   . '" target="_blank">Click here to sign up for a WePay account</a>';



			return $query_str;
		}

		$expr = new Mage_Core_Model_Translate_Expr(array_shift($args), $this->_getModuleName());
		array_unshift($args, $expr);
		return Mage::app()->getTranslator()->translate($args);
	}

}
