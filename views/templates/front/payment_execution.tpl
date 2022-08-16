{*
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
*
{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}
*}
{if $nbProducts <= 0}
	<p class="warning">{l s='Your shopping cart is empty.' mod='affirm'}</p>
{else}
<p>Redirecting to Affirm Website...</p>
{literal}
<script>
 _affirm_config = {
   public_api_key:  "{/literal}{$affirmDetails.AFFIRM_PUBLIC}{literal}",
   script:          "{/literal}{$affirmJsUrl}{literal}"
 };
(function(m,g,n,d,a,e,h,c){var b=m[n]||{},k=document.createElement(e),p=document.getElementsByTagName(e)[0],l=function(a,b,c){return function(){a[b]._.push([c,arguments])}};b[d]=l(b,d,"set");var f=b[d];b[a]={};b[a]._=[];f._=[];b._=[];b[a][h]=l(b,a,h);b[c]=function(){b._.push([h,arguments])};a=0;for(c="set add save post open empty reset on off trigger ready setProduct".split(" ");a<c.length;a++)f[c[a]]=l(b,d,c[a]);a=0;for(c=["get","token","url","items"];a<c.length;a++)f[c[a]]=function(){};k.async=
!0;k.src=g[e];p.parentNode.insertBefore(k,p);delete g[e];f(g);m[n]=b})(window,_affirm_config,"affirm","checkout","ui","script","ready","jsReady");

var affirmData = {
   "merchant":{
      "user_confirmation_url":"{/literal}{$link->getModuleLink('affirm', 'validation')|escape:'html'}{literal}",
      "user_cancel_url":"{/literal}{$link->getPageLink('order', true, NULL, 'step=3')|escape:'html'}{literal}",
      "user_confirmation_url_action":"POST",
      "use_vcn": false,
      "name":"Citimarine LLC"
   },
   "shipping":{
      "name":{
         "full":"{/literal}{$customer->firstname} {$customer->lastname}{literal}"
      },
      "address":{
         "line1":"{/literal}{$delivery_address->address1} {$delivery_address->address2}{literal}",
         "city":"{/literal}{$delivery_address->city}{literal}",
         "state":"{/literal}{$deliveryState}{literal}",
         "zipcode":"{/literal}{$delivery_address->postcode}{literal}",
         "country":"US"
      },
      "phone_number":"{/literal}{$delivery_address->phone}{literal}",
      "email":"{/literal}{$customer->email}{literal}"
   },
   "billing":{
      "name":{
         "full":"{/literal}{$customer->firstname} {$customer->lastname}{literal}"
      },
      "address":{
         "line1":"{/literal}{$invoice_address->address1} {$invoice_address->address2}{literal}",
         "city":"{/literal}{$invoice_address->city}{literal}",
         "state":"{/literal}{$invoiceState}{literal}",
         "zipcode":"{/literal}{$invoice_address->postcode}{literal}",
         "country":"US"
      },
      "phone_number":"{/literal}{$invoice_address->phone}{literal}",
      "email":"{/literal}{$customer->email}{literal}"
   },
   "items": {/literal}{$mappedProducts nofilter}{literal},
   "metadata":{
      "shipping_type":"UPS Ground",
      "mode":"redirect"
   },
   "order_id":"CTM{/literal}{$id_cart}{literal}",
   "currency":"USD",  
   "financing_program":"{/literal}{$affirmDetails.AFFIRM_PRODUCT}{literal}",
   "shipping_amount":{/literal}{$shipping*100}{literal},
   "tax_amount":0,
   "total": {/literal}{$total}{literal}
};
//jQuery(document).ready(function ( $ ) {
    affirm.ui.ready(
        function() {
            //affirm.checkout.open(affirmData);
            affirm.checkout(affirmData);
            affirm.checkout.open();
            affirm.ui.error.on("close", function(){
                alert("Please check your contact information for accuracy.");
            });
        }
    );
//});        
//affirm.checkout.open(checkoutObj);
</script>
{/literal}
{/if}
