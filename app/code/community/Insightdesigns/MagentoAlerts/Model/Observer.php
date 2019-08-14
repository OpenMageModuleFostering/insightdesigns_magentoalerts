<?php
class Insightdesigns_MagentoAlerts_Model_Observer
{
	private function storeConfigParams($data)
	{
		$json = json_encode($data);
		$client = new Zend_Http_Client('https://api.insightdesigns.com/magento/1.0/save/configuration/');
		$response = $client->setRawData($json, 'application/json')->request('POST');
		if ($response->getStatus() == '200') {
			Mage::getSingleton('core/session')->addSuccess($response->getBody());
		} else {
			Mage::getSingleton('core/session')->addError('Error syncing Magento Alerts configuration.');
		}
	}
	
	private function storeOrder($data)
	{
		$json = json_encode($data);
		$client = new Zend_Http_Client('https://api.insightdesigns.com/magento/1.0/save/order/');
		$response = $client->setRawData($json, 'application/json')->request('POST');
	}
	
    public function adminSystemConfigSave(Varien_Event_Observer $observer)
    {
	    
	    $config = $observer->getConfig();
	    $params = Mage::app()->getRequest()->getPost();

	    $website_id = 0;
	    $store_id = 0;
		if (strlen($store_code = Mage::getSingleton('adminhtml/config_data')->getStore())) {
		    $store_id = Mage::getModel('core/store')->load($store_code)->getId();
		}
		if (strlen($website_code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) {
		    $website_id = Mage::getModel('core/website')->load($website_code)->getId();
		    $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
		}
		
		$form_key = $params['form_key'];
		$guid = $params['groups']['settings']['fields']['guid']['value'];
		$enabled = $params['groups']['settings']['fields']['enabled']['value'];
		$raw_device_uuids = $params['groups']['settings']['fields']['device_uuids']['value'];
		$device_uuids = explode(PHP_EOL, $raw_device_uuids);

		$data = array();
	    if ($form_key != '') {
			$data = array(
				'website_id' => $website_id,
				'store_id' => $store_id,
				'enabled' => $enabled,
				'device_uuids' => $device_uuids,
				'guid' => $guid,
				'currency' => Mage::app()->getStore($store_id)->getCurrentCurrencyCode(),
				'logo' => Mage::getModel('core/design_package')->getSkinUrl(Mage::getStoreConfig('design/header/logo_src', $store_id), array('_secure'=>true)),
				'language' => Mage::app()->getLocale()->getLocaleCode(),
				'name' => Mage::getStoreConfig('general/store_information/name', $store_id),
				'country' => Mage::getStoreConfig('general/store_information/merchant_country', $store_id),
				'timezone' => Mage::getStoreConfig('general/locale/timezone', $store_id),
				'unsecure_url' => Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_UNSECURE_BASE_URL, $store_id),
				'secure_url' => Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL, $store_id),
				'version' => Mage::getVersion()
			);
		}
		
		if (count($data) > 0) {
			$this->storeConfigParams($data);
		}
    }
    
    public function afterOrderSync(Varien_Event_Observer $observer)
    {
	    
		    $order_ids = $observer->getEvent()->getOrderIds();
	        if (empty($order_ids) || !is_array($order_ids)) {
	            return;
	        }
	        foreach ($order_ids as $order_id) {

		        $order = Mage::getModel('sales/order')->load($order_id);
	
		        if (is_object($order)) {
					// ENABLED?
			        if (Mage::getStoreConfigFlag('insightdesigns_magentoalerts/settings/enabled', $order->getStoreId())) {
				        // GUID SET?
						if (Mage::getStoreConfig('insightdesigns_magentoalerts/settings/guid', $order->getStoreId()) != '') {
					        $order_items = $order->getAllItems();
					        $order_data = array(
						        'sale_total' => number_format($order->getGrandTotal(), 2, '.', ''),
						        'sale_increment_id' => $order->getIncrementId(),
						        'sale_shipping_method' => $order->getShippingDescription(),
						        'sale_date' => $order->getCreatedAt(),
						        'sale_ip_address' => $order->getRemoteIp(),
						        'sale_store_id' => $order->getStoreId(),
						        'sale_website_id' => Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId(),
						        'guid' => Mage::getStoreConfig('insightdesigns_magentoalerts/settings/guid', $order->getStoreId()),
						        'items' => array()
					        );
						    foreach ($order_items as $order_item) {
							    $order_item_product = Mage::getModel('catalog/product')->load($order_item->getProductId());
							    $order_data['items'][] = array(
								    'name' => $order_item->getName(),
								    'sku' => $order_item->getSku(),
								    'qty' => round($order_item->getQtyOrdered(), 1),
								    'image' => (string)Mage::helper('catalog/image')->init($order_item_product, 'thumbnail')->resize(150)
							    );
							}
							
							if (count($order_data) > 0) {
								$this->storeOrder($order_data);
							}
						}
					}
		        }
	        }
    }
}