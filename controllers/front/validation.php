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
class AffirmValidationModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
        $cart = $this->context->cart;
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'affirm')
			{
				$authorized = true;
				break;
			}
		if (!$authorized)
			die($this->module->l('This payment method is not available.', 'validation'));

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			Tools::redirect('index.php?controller=order&step=1');
        
        $order_id = 'CTM'.$cart->id;
        $checkout_token = Tools::getValue('checkout_token');   
        $this->authorizeCharge($checkout_token, $order_id);    
            
        $currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
		$mailVars = array(
			'{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
			'{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
			'{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
		);

		$this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
		Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
	}
    
    public function authorizeCharge($checkout_token, $order_id)
    {
        $affirmDetails = Configuration::getMultiple(array('AFFIRM_PRODUCTION', 'AFFIRM_PUBLIC', 'AFFIRM_PRIVATE', 'AFFIRM_PRODUCT'));
        if ($affirmDetails['AFFIRM_PRODUCTION'] == 1) {
            $endpoint = "https://api.affirm.com/api/v2/charges";
        } else {
            $endpoint = "https://sandbox.affirm.com/api/v2/charges";
        }
        
        $data['checkout_token'] = $checkout_token;
        $data['order_id'] = $order_id;
        $data = json_encode($data);
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_USERPWD, $affirmDetails['AFFIRM_PUBLIC'] . ':' . $affirmDetails['AFFIRM_PRIVATE']);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        
        if (!empty($result)) {
            $affirmResponse = json_decode($result, true);
            $charge_id = $affirmResponse['id'];
            $curTime = date('Y-m-d H:i:s');
            $sql = 'insert into `'._DB_PREFIX_.'affirm` (order_id, checkout_token, charge_id, affirm_response, date_add, date_update)values("'.$order_id.'", "'.$checkout_token.'", "'.$charge_id.'", "'.addslashes($result).'", "'.$curTime.'", "'.$curTime.'")';
            Db::getInstance()->execute($sql);
            $this->module->captureCharge($checkout_token, $charge_id, $order_id);
        }
        
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
    }
    
}
