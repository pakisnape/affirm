<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
	exit;

class Affirm extends PaymentModule
{
	protected $_html = '';
	protected $_postErrors = array();

	public $details;
	public $owner;
	public $address;
	public $extra_mail_vars;
	public function __construct()
	{
		$this->name = 'affirm';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.0';
		$this->author = 'SudoSol';
		$this->controllers = array('payment', 'validation');
		$this->is_eu_compatible = 1;

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		$config = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
		if (!empty($config['AFFIRM_PRODUCTION']))
			$this->live = $config['AFFIRM_PRODUCTION'];
        if (!empty($config['AFFIRM_PUBLIC']))
			$this->publicKey = $config['AFFIRM_PUBLIC'];
		if (!empty($config['AFFIRM_PRIVATE']))
			$this->privateKey = $config['AFFIRM_PRIVATE'];
		if (!empty($config['AFFIRM_PRODUCT']))
			$this->productKey = $config['AFFIRM_PRODUCT'];

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('Affirm');
		$this->description = $this->l('Accept payments for your products via affirm payment gateway.');
		$this->confirmUninstall = $this->l('Are you sure about removing these details?');
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.7.99.99');

		if (!isset($this->publicKey) || !isset($this->privateKey) || !isset($this->productKey))
			$this->warning = $this->l('Public, Private and Product key details must be configured before using this module.');
		if (!count(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module.');

	}

	public function install()
	{
		if (!parent::install() || !$this->registerHook('payment') || ! $this->registerHook('displayPaymentEU') || !$this->registerHook('paymentReturn') || !$this->registerHook('ActionOrderStatusUpdate') || !$this->registerHook('displayProductAdditionalInfo') || !$this->registerHook('displayCheckoutSubtotalDetails') || !$this->registerHook('displayShoppingCartFooter')  || !$this->registerHook('customSuperCheckoutGDPRHook')  || !$this->registerHook('paymentOptions'))
			return false;
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'affirm` (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `order_id` varchar(100) NOT NULL,
        `checkout_token` varchar(200) NOT NULL,
        `charge_id` varchar(150) NOT NULL,
        `affirm_response` Text NOT NULL,
        `captured` int(1) NOT NULL,
        `date_add` Datetime,
        `date_update` Datetime,
        PRIMARY KEY (`id`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
        Db::getInstance()->execute($sql);    
		return true;
	}

	public function uninstall()
	{
        if (!Configuration::deleteByName('AFFIRM_PRODUCTION')
				|| !Configuration::deleteByName('AFFIRM_PUBLIC')
				|| !Configuration::deleteByName('AFFIRM_PRIVATE')
                || !Configuration::deleteByName('AFFIRM_PRODUCT')
                || !$this->removeLogs()
				|| !parent::uninstall())
			return false;
		return true;
	}
    
    public function removeLogs()
    {
        $sql = 'DROP TABLE `'._DB_PREFIX_.'affirm`';
        Db::getInstance()->execute($sql);
        return true;
    }

	protected function _postValidation()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			if (!Tools::getValue('AFFIRM_PUBLIC'))
				$this->_postErrors[] = $this->l('Public Api key is required.');
			elseif (!Tools::getValue('AFFIRM_PRIVATE'))
				$this->_postErrors[] = $this->l('Private Api key is required.');
            elseif (!Tools::getValue('AFFIRM_PRODUCT'))
				$this->_postErrors[] = $this->l('Financial Product Key is required.');    
		}
	}

	protected function _postProcess()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
            Configuration::updateValue('AFFIRM_PRODUCTION', Tools::getValue('AFFIRM_PRODUCTION'));
			Configuration::updateValue('AFFIRM_PUBLIC', Tools::getValue('AFFIRM_PUBLIC'));
			Configuration::updateValue('AFFIRM_PRIVATE', Tools::getValue('AFFIRM_PRIVATE'));
			Configuration::updateValue('AFFIRM_PRODUCT', Tools::getValue('AFFIRM_PRODUCT'));
		}
		$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
	}

	protected function _displayAffirm()
	{
		return $this->display(__FILE__, 'infos.tpl');
	}

	public function getContent()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			$this->_postValidation();
			if (!count($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors as $err)
					$this->_html .= $this->displayError($err);
		}
		else
			$this->_html .= '<br />';

		$this->_html .= $this->_displayAffirm();
		$this->_html .= $this->renderForm();

		return $this->_html;
	}

	public function hookPayment($params)
	{
		if (!$this->active)
			return;
		if (!$this->checkCurrency($params['cart']))
			return;
        
        $cart = $this->context->cart;    
        $affirmDetails = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
        $customer = new Customer($cart->id_customer);
        $deliveryAddress = new Address($cart->id_address_delivery);
        $invoiceAddress = new Address($cart->id_address_invoice);
        $products = $cart->getProducts();
        $mappedProducts = $this->getProductsMapped($products);
        $deliveryState = State::getNameById($deliveryAddress->id_state);
        $invoiceState = State::getNameById($invoiceAddress->id_state);
        if ($affirmDetails['AFFIRM_PRODUCTION'] == 1) {
            $affirmJsUrl = 'https://cdn1.affirm.com/js/v2/affirm.js';
        } else {
            $affirmJsUrl = 'https://cdn1-sandbox.affirm.com/js/v2/affirm.js';
        }    

		$this->smarty->assign(array(
            'id_cart' => $cart->id,
            'customer' => $customer,
            'delivery_address' => $deliveryAddress,
            'invoice_address' => $invoiceAddress,
            'affirmDetails' => $affirmDetails,
            'products' => $products,
            'deliveryState' => $deliveryState,
            'invoiceState' => $invoiceState,
            'mappedProducts' => $mappedProducts,
            'affirmJsUrl' => $affirmJsUrl,
            'shipping' => $cart->getPackageShippingCost(),
			'nbProducts' => $cart->nbProducts(),
			'cust_currency' => $cart->id_currency,
			'currencies' => $this->getCurrency((int)$cart->id_currency),
			'total' => ((int)$cart->getOrderTotal(true, Cart::BOTH)+1)*100,
			'this_path' => $this->_path,
			'this_path_bw' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'payment.tpl');
	}
    
    public function getProductsMapped($products)
    {
        if (!empty($products)) {
            foreach ($products as $product) {
                $imageUrl = $this->context->link->getImageLink($product['link_rewrite'], $product['id_product'], 'home_default');
                $productUrl = $this->context->link->getProductLink($product['id_product']);
                $itemMapped[] = array(
                    'display_name' => $product['name'],
                    'sku' => $product['id_product_attribute'],
                    'unit_price' => $product['price']*100,
                    'qty' => $product['cart_quantity'],
                    'item_image_url' => $imageUrl,
                    'item_url' => $productUrl 
                ); 
            }
            return json_encode($itemMapped);
        }
        
    }
    
    public function hookDisplayProductAdditionalInfo($params)
	{
		$affirmDetails = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
        if ($affirmDetails['AFFIRM_PRODUCTION'] == 1) {
            $affirmJsUrl = 'https://cdn1.affirm.com/js/v2/affirm.js';
        } else {
            $affirmJsUrl = 'https://cdn1-sandbox.affirm.com/js/v2/affirm.js';
        }
        $sessionID = session_id();    
        
        $product = $params['product'];
        if (strpos(_PS_VERSION_, '1.6') !== false) {
            $productPrice = $product->price*100;
        } else {
            $productPrice = $product->price_amount*100;
        }
		$this->smarty->assign(array(
            'procuctPrice' => $productPrice,
            'sessionID' => $sessionID,
            'affirmDetails' => $affirmDetails,
            'affirmJsUrl' => $affirmJsUrl,
			'this_path' => $this->_path,
			'this_path_bw' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'product_section.tpl');
	}
    
    public function hookDisplayCheckoutSubtotalDetails($params)
	{
        $affirmDetails = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
        if ($affirmDetails['AFFIRM_PRODUCTION'] == 1) {
            $affirmJsUrl = 'https://cdn1.affirm.com/js/v2/affirm.js';
        } else {
            $affirmJsUrl = 'https://cdn1-sandbox.affirm.com/js/v2/affirm.js';
        }
        $sessionID = session_id();    
        if (strpos(_PS_VERSION_, '1.6') !== false) {
            $total_price = $params['total_price'];
        } else {
            $cart = $this->context->cart;
            $total_price = $cart->getOrderTotal(true, Cart::BOTH);
        }    
        $this->smarty->assign(array(
            'cartPrice' => $total_price*100,
            'sessionID' => $sessionID,
            'affirmDetails' => $affirmDetails,
            'affirmJsUrl' => $affirmJsUrl,
			'this_path' => $this->_path,
			'this_path_bw' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'checkout_section.tpl');
	}
    
    public function hookDisplayShoppingCartFooter($params)
	{
        $affirmDetails = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
        if ($affirmDetails['AFFIRM_PRODUCTION'] == 1) {
            $affirmJsUrl = 'https://cdn1.affirm.com/js/v2/affirm.js';
        } else {
            $affirmJsUrl = 'https://cdn1-sandbox.affirm.com/js/v2/affirm.js';
        }
        $sessionID = session_id();    
        if (strpos(_PS_VERSION_, '1.6') !== false) {
            $total_price = $params['total_price'];
        } else {
            $cart = $this->context->cart;
            $total_price = $cart->getOrderTotal(true, Cart::BOTH);
        }    
        $this->smarty->assign(array(
            'cartPrice' => $total_price*100,
            'sessionID' => $sessionID,
            'affirmDetails' => $affirmDetails,
            'affirmJsUrl' => $affirmJsUrl,
			'this_path' => $this->_path,
			'this_path_bw' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'checkout_section.tpl');
	}
    
    public function hookCustomSuperCheckoutGDPRHook($params)
	{
        $affirmDetails = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
        if ($affirmDetails['AFFIRM_PRODUCTION'] == 1) {
            $affirmJsUrl = 'https://cdn1.affirm.com/js/v2/affirm.js';
        } else {
            $affirmJsUrl = 'https://cdn1-sandbox.affirm.com/js/v2/affirm.js';
        }
        $sessionID = session_id();    
        if (strpos(_PS_VERSION_, '1.6') !== false) {
            $total_price = $params['total_price'];
        } else {
            $cart = $this->context->cart;
            $total_price = $cart->getOrderTotal(true, Cart::BOTH);
        }    
        $this->smarty->assign(array(
            'cartPrice' => $total_price*100,
            'sessionID' => $sessionID,
            'affirmDetails' => $affirmDetails,
            'affirmJsUrl' => $affirmJsUrl,
			'this_path' => $this->_path,
			'this_path_bw' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'supercheckout_section.tpl');
	}
    
    public function hookOneCheckout($params)
	{
        $affirmDetails = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
        if ($affirmDetails['AFFIRM_PRODUCTION'] == 1) {
            $affirmJsUrl = 'https://cdn1.affirm.com/js/v2/affirm.js';
        } else {
            $affirmJsUrl = 'https://cdn1-sandbox.affirm.com/js/v2/affirm.js';
        }
        $id_product = Tools::getValue('id_product');
        $sessionID = session_id();
        if (!empty($id_product)) {
            $product = new Product($id_product);
            $productPrice = $product->price;
            $this->smarty->assign(array(
                'procuctPrice' => $productPrice*100,
                'sessionID' => $sessionID,
                'affirmDetails' => $affirmDetails,
                'affirmJsUrl' => $affirmJsUrl,
                'this_path' => $this->_path,
                'this_path_bw' => $this->_path,
                'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
            ));
            return $this->display(__FILE__, 'product_section.tpl');
        } else {
            $cart = $this->context->cart;
            $total_price = $cart->getOrderTotal(true, Cart::BOTH);
            $this->smarty->assign(array(
                'cartPrice' => $total_price*100,
                'sessionID' => $sessionID,
                'affirmDetails' => $affirmDetails,
                'affirmJsUrl' => $affirmJsUrl,
                'this_path' => $this->_path,
                'this_path_bw' => $this->_path,
                'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
            ));
            return $this->display(__FILE__, 'checkout_section.tpl');
        }
        
	}

	public function hookPaymentOptions($params)
	{
        if (!$this->active)
			return;

		if (!$this->checkCurrency($params['cart']))
			return;
            
        $payment_option = new PaymentOption();
        $payment_option->setCallToActionText($this->l('Pay by Affirm'))
        ->setModuleName($this->name)
        ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
        ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/affirm.jpg'));
        
        $payment_options = [
            $payment_option,
        ];

		/*$payment_options = array(
			'cta_text' => $this->l('Pay by Affirm'),
			'logo' => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/affirm.jpg'),
			'action' => $this->context->link->getModuleLink($this->name, 'payment', array(), true)
		);*/

		return $payment_options;
	}

	public function hookPaymentReturn($params)
	{
        /*
		if (!$this->active)
			return;

		$state = $params['objOrder']->getCurrentState();
		if (in_array($state, array(Configuration::get('PS_OS_BANKWIRE'), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'))))
		{
			$this->smarty->assign(array(
				'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
				'bankwireDetails' => Tools::nl2br($this->details),
				'bankwireAddress' => Tools::nl2br($this->address),
				'bankwireOwner' => $this->owner,
				'status' => 'ok',
				'id_order' => $params['objOrder']->id
			));
			if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference))
				$this->smarty->assign('reference', $params['objOrder']->reference);
		}
		else
			$this->smarty->assign('status', 'failed');
		return $this->display(__FILE__, 'payment_return.tpl');
        */ 
	}

	public function checkCurrency($cart)
	{
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);

		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module['id_currency'])
					return true;
		return false;
	}

	public function renderForm()
	{
        $fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Affirm details'),
					'icon' => 'icon-envelope'
				),
				'input' => array(
					array(
						'type' => 'switch',
						'label' => $this->l('Production'),
						'name' => 'AFFIRM_PRODUCTION',
                        'values' => array(
                            array(
                                'value' => 1,            
                                'label' => $this->l('yes')            
                            ),
                            array(
                                'value' => 0,
                                'label' => $this->l('No')            
                            )
                        )
					),
                    array(
						'type' => 'text',
						'label' => $this->l('Public API Key'),
						'name' => 'AFFIRM_PUBLIC',
                        'required' => true
					),
                    array(
						'type' => 'text',
						'label' => $this->l('Private API Key'),
						'name' => 'AFFIRM_PRIVATE',
                        'required' => true
					),
                    array(
						'type' => 'text',
						'label' => $this->l('Financial Product Key'),
						'name' => 'AFFIRM_PRODUCT',
                        'required' => true
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnSubmit';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
        return array(
			'AFFIRM_PRODUCTION' => Tools::getValue('AFFIRM_PRODUCTION', Configuration::get('AFFIRM_PRODUCTION')),
			'AFFIRM_PUBLIC' => Tools::getValue('AFFIRM_PUBLIC', Configuration::get('AFFIRM_PUBLIC')),
			'AFFIRM_PRIVATE' => Tools::getValue('AFFIRM_PRIVATE', Configuration::get('AFFIRM_PRIVATE')),
            'AFFIRM_PRODUCT' => Tools::getValue('AFFIRM_PRODUCT', Configuration::get('AFFIRM_PRODUCT')),
		);
	}
    
    public function hookActionOrderStatusUpdate($params)
    {
        if($params['newOrderStatus']->id == 5)
        {
            $order_id = $params['id_order'];
            $affirm_response = $this->getChargeId($order_id);
            $checkout_token = $affirm_response['checkout_token'];
            $charge_id = $affirm_response['charge_id'];
            if(!$this->captureCharge($checkout_token, $charge_id, 'CTM'.$order_id))
                return false;            
        }
        return;
    }
    
    public function getChargeId($order_id)
    {
        $sql = 'select checkout_token, charge_id from `'._DB_PREFIX_.'affirm` where order_id LIKE "CTM'.$order_id.'"';
        $affirm_response = Db::getInstance()->getRow($sql);
        return $affirm_response;
    }
    
    public function setCaptured($order_id)
    {
        $sql = 'update `'._DB_PREFIX_.'affirm` set captured = "1", date_update = "'.date('Y-m-d H:i:s').'" where order_id = "'.$order_id.'"';
        Db::getInstance()->execute($sql);
        return;
    }
    
    public function captureCharge($checkout_token, $charge_id, $order_id)
    {
        $affirmDetails = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
        if ($affirmDetails['AFFIRM_PRODUCTION'] == 1) {
            $endpoint = "https://api.affirm.com/api/v2/charges";
        } else {
            $endpoint = "https://sandbox.affirm.com/api/v2/charges";
        }
        
        $data['checkout_token'] = $checkout_token;
        $data['order_id'] = $order_id;
        $data['shipping_carrier'] = 'USPS';
        $data['shipping_confirmation'] = '1Z23223';
        $data = json_encode($data);
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint.'/'.$charge_id.'/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_USERPWD, $affirmDetails['AFFIRM_PUBLIC'] . ':' . $affirmDetails['AFFIRM_PRIVATE']);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        
        if (!empty($result)) {
            $capturedResponse = json_decode($result, true);
            if ($capturedResponse['type'] == 'capture') {
                $this->setCaptured($order_id);
                return true;
            }
        }
        
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        return false;
    }
    
}
