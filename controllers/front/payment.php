<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class AffirmPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	public $display_column_left = false;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
        parent::initContent();
        
        $cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart))
			Tools::redirect('index.php?controller=order');
            
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
        
		$this->context->smarty->assign(array(
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
			'currencies' => $this->module->getCurrency((int)$cart->id_currency),
			'total' => ((int)$cart->getOrderTotal(true, Cart::BOTH)+1)*100,
			'this_path' => $this->module->getPathUri(),
			'this_path_bw' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));
        
		$this->setTemplate('module:affirm/views/templates/front/payment_execution.tpl');
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
}
