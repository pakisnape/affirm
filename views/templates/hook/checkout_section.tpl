{literal}
<script>
    var _affirm_config = {
        public_api_key: "{/literal}{$affirmDetails.AFFIRM_PUBLIC}{literal}",
        script: "{/literal}{$affirmJsUrl}{literal}",
        session_id: "{/literal}{$sessionID}{literal}"
    };
    (function(l,g,m,e,a,f,b){var d,c=l[m]||{},h=document.createElement(f),n=document.getElementsByTagName(f)[0],k=function(a,b,c){return function(){a[b]._.push([c,arguments])}};c[e]=k(c,e,"set");d=c[e];c[a]={};c[a]._=[];d._=[];c[a][b]=k(c,a,b);a=0;for(b="set add save post open empty reset on off trigger ready setProduct".split(" ");a<b.length;a++)d[b[a]]=k(c,e,b[a]);a=0;for(b=["get","token","url","items"];a<b.length;a++)d[b[a]]=function(){};h.async=!0;h.src=g[f];n.parentNode.insertBefore(h,n);delete g[f];d(g);l[m]=c})(window,_affirm_config,"affirm","checkout","ui","script","ready");
    
    function updateAffirmPromos(newPrice){
        document.getElementById('affirmLearnMore').setAttribute('data-amount',newPrice);
       
        //Call affirm.ui.refresh to re-render the promotional messaging componenets
        affirm.ui.ready(function(){
            console.log('updating affirm price');
            affirm.ui.refresh();
        });
    }
    
    jQuery(document).ready(function ($) {
        console.log('removing');
        $('.affirmBox').remove();
        var $affirmBox = '<div class="row affirmBox"><div class="col-xs-12"><p id="affirmLearnMore" class="affirm-as-low-as" data-page-type="cart" data-amount="{/literal}{$cartPrice}{literal}"></p></div></div>';
        console.log('adding');
        /*if ($('.cart-detailed-actions').length > 0) {
            $('.cart-detailed-actions').before($affirmBox);
        }*/
        if ($('#affirmContainer').length > 0) {
            $('#affirmContainer').html($affirmBox);
        }
        /*if (jQuery('#affirmLearnMore').html != '') {
            var thePrice = makePriceFloat(jQuery('#total_price').text());
            $('body').on('DOMSubtreeModified', '#total_price', function(){
                //setTimeout(function(){
                    var newPrice = makePriceFloat(jQuery('#total_price').text());
                    updateAffirmPromos(newPrice*100);
                //}, 6000);
            
            });    
        }*/
    });
    
</script>
<style>
    p.affirm-as-low-as {
        font-size: 15px;
        font-style: normal;
        text-align: center;
        color: #2d3141;
    }
    p.affirm-as-low-as a.affirm-modal-trigger {
        color: #297ea5;
        cursor: pointer;
    }
    p.affirm-as-low-as a.affirm-modal-trigger:hover {
        text-decoration: underline;
    }
</style>    
{/literal}
