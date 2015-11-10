{if no_items}
    <p>The cart is empty.</p>
{/if}

<style type="text/css">
.store_checkout_debug { margin: 1em 0; padding: 1em; border: 1px solid #ddd; font-size: 12px; text-align: left; }
.store_checkout_debug:before, .store_checkout_debug:after { content: " "; display: table; clear: both; }
.store_checkout_debug_title { margin: -1em -1em 1em -1em; padding: 1em; font-weight: bold; background: #ddd; color: #333; }
.store_checkout_debug table { max-width: 100%; margin: 0.5em; padding: 0; border: 0; border-collapse: collapse; table-layout: fixed; float: left; }
.store_checkout_debug table caption { margin: 0; padding: 0.5em; background: #ddd; color: #333; font-weight: bold; }
.store_checkout_debug table tr { margin: 0; padding: 0; border: 0; }
.store_checkout_debug table td, .store_checkout_debug table th { margin: 0; padding: 0.5em; border: 1px solid #ddd; min-width: 5em; }
.store_checkout_debug table th { font-weight: bold; }
.store_checkout_debug table .store_checkout_debug_noborder { border: 0; }
</style>

<div class="store_checkout_debug">

    <div class="store_checkout_debug_title">store:checkout_debug</div>

    <table style="clear:both">
        <caption>items</caption>
        <tr>
            <th>item:count</th>
            <th>title</th>
            <th>price</th>
            <th>regular_price</th>
            <th>you_save</th>
            <th>on_sale</th>
            <th>item_qty</th>
            <th>item_subtotal</th>
            <th>item_discount</th>
            <th>item_tax</th>
            <th>item_total</th>
        </tr>
        {items}
            <tr>
                <td>{item:count}</td>
                <td>{title}</td>
                <td>{price}</td>
                <td>{regular_price}</td>
                <td>{you_save}</td>
                <td>{if on_sale}&#x2714;{if:else}&#x2718;{/if}</td>
                <td>{item_qty}</td>
                <td>{item_subtotal}</td>
                <td>{item_discount}</td>
                <td>{item_tax}</td>
                <td>{item_total}</td>
            </tr>
        {/items}
    </table>

    <table style="clear:both">
        <caption>adjustments</caption>
        <tr>
            <th>adjustment:count</th>
            <th>adjustment:name</th>
            <th>adjustment:type</th>
            <th>adjustment:rate</th>
            <th>adjustment:percent</th>
            <th>adjustment:amount</th>
            <th>adjustment:taxable</th>
            <th>adjustment:included</th>
        </tr>
        {adjustments}
            <tr>
                <td>{adjustment:count}</td>
                <td>{adjustment:name}</td>
                <td>{adjustment:type}</td>
                <td>{adjustment:rate}</td>
                <td>{adjustment:percent}</td>
                <td>{adjustment:amount}</td>
                <td>{if adjustment:taxable}&#x2714;{if:else}&#x2718;{/if}</td>
                <td>{if adjustment:included}&#x2714;{if:else}&#x2718;{/if}</td>
            </tr>
        {/adjustments}
    </table>

    <table style="clear:both">
        <tr>
            <th>order_shipping</th>
            <th>order_shipping_discount</th>
            <th>order_shipping_tax</th>
            <th>order_shipping_total</th>
        </tr>
        <tr>
            <td>{order_shipping}</td>
            <td>{order_shipping_discount}</td>
            <td>{order_shipping_tax}</td>
            <td>{order_shipping_total}</td>
        </tr>
    </table>

    <table style="clear:both">
        <tr>
            <th>order_handling</th>
            <th>order_handling_tax</th>
            <th>order_handling_total</th>
        </tr>
        <tr>
            <td>{order_handling}</td>
            <td>{order_handling_tax}</td>
            <td>{order_handling_total}</td>
        </tr>
    </table>

    <table style="clear:both">
        <tr>
            <th>order_subtotal</th>
            <td>{order_subtotal}</td>
        </tr>
        <tr>
            <th>order_items_total</th>
            <td>{order_items_total}</td>
        </tr>
        <tr>
            <th>order_discount</th>
            <td>{order_discount}</td>
        </tr>
        <tr>
            <th>order_tax</th>
            <td>{order_tax}</td>
        </tr>
        <tr>
            <th>order_you_save</th>
            <td>{order_you_save}</td>
        </tr>
        <tr>
            <th>order_adjustments_total</th>
            <td>{order_adjustments_total}</td>
        </tr>
        <tr>
            <th>order_total</th>
            <td>{order_total}</td>
        </tr>
    </table>

    <table style="clear:both">
        <tr>
            <th>tax_id</th>
            <td>{tax_id}</td>
        </tr>
        <tr>
            <th>tax_name</th>
            <td>{tax_name}</td>
        </tr>
        <tr>
            <th>tax_rate</th>
            <td>{tax_rate}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>shipping_method_id</th>
            <td>{shipping_method_id}</td>
        </tr>
        <tr>
            <th>shipping_method_name</th>
            <td>{shipping_method_name}</td>
        </tr>
        <tr>
            <th>shipping_method_class</th>
            <td>{shipping_method_class}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>promo_code</th>
            <td>{promo_code}</td>
        </tr>
        <tr>
            <th>discount:id</th>
            <td>{discount:id}</td>
        </tr>
        <tr>
            <th>discount:name</th>
            <td>{discount:name}</td>
        </tr>
        <tr>
            <th>discount:start_date</th>
            <td>{discount:start_date}</td>
        </tr>
        <tr>
            <th>discount:end_date</th>
            <td>{discount:end_date}</td>
        </tr>
        <tr>
            <th>discount:free_shipping</th>
            <td>{if discount:free_shipping}&#x2714;{if:else}&#x2718;{/if}</td>
        </tr>
    </table>

    <table style="clear:both">
        <caption>shipping_methods</caption>
        <tr>
            <th>shipping_method:id</th>
            <th>shipping_method:class</th>
            <th>shipping_method:name</th>
            <th>shipping_method:amount</th>
            <th>shipping_method:days</th>
        </tr>
        {shipping_methods}
            <tr>
                <td>{shipping_method:id}</td>
                <td>{shipping_method:class}</td>
                <td>{shipping_method:name}</td>
                <td>{shipping_method:amount}</td>
                <td>{shipping_method:days}</td>
            </tr>
        {/shipping_methods}
    </table>

    <table style="clear:both">
        <tr>
            <th>billing_same_as_shipping</th>
            <td>{if billing_same_as_shipping}&#x2714;{if:else}&#x2718;{/if}</td>
        </tr>
        <tr>
            <th>billing_first_name</th>
            <td>{billing_first_name}</td>
        </tr>
        <tr>
            <th>billing_last_name</th>
            <td>{billing_last_name}</td>
        </tr>
        <tr>
            <th>billing_name</th>
            <td>{billing_name}</td>
        </tr>
        <tr>
            <th>billing_address1</th>
            <td>{billing_address1}</td>
        </tr>
        <tr>
            <th>billing_address2</th>
            <td>{billing_address2}</td>
        </tr>
        <tr>
            <th>billing_city</th>
            <td>{billing_city}</td>
        </tr>
        <tr>
            <th>billing_postcode</th>
            <td>{billing_postcode}</td>
        </tr>
        <tr>
            <th>billing_state</th>
            <td>{billing_state}</td>
        </tr>
        <tr>
            <th>billing_state_name</th>
            <td>{billing_state_name}</td>
        </tr>
        <tr>
            <th>billing_country</th>
            <td>{billing_country}</td>
        </tr>
        <tr>
            <th>billing_country_name</th>
            <td>{billing_country_name}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>shipping_same_as_billing</th>
            <td>{if shipping_same_as_billing}&#x2714;{if:else}&#x2718;{/if}</td>
        </tr>
        <tr>
            <th>shipping_first_name</th>
            <td>{shipping_first_name}</td>
        </tr>
        <tr>
            <th>shipping_last_name</th>
            <td>{shipping_last_name}</td>
        </tr>
        <tr>
            <th>shipping_name</th>
            <td>{shipping_name}</td>
        </tr>
        <tr>
            <th>shipping_address1</th>
            <td>{shipping_address1}</td>
        </tr>
        <tr>
            <th>shipping_address2</th>
            <td>{shipping_address2}</td>
        </tr>
        <tr>
            <th>shipping_city</th>
            <td>{shipping_city}</td>
        </tr>
        <tr>
            <th>shipping_postcode</th>
            <td>{shipping_postcode}</td>
        </tr>
        <tr>
            <th>shipping_state</th>
            <td>{shipping_state}</td>
        </tr>
        <tr>
            <th>shipping_state_name</th>
            <td>{shipping_state_name}</td>
        </tr>
        <tr>
            <th>shipping_country</th>
            <td>{shipping_country}</td>
        </tr>
        <tr>
            <th>shipping_country_name</th>
            <td>{shipping_country_name}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>order_email</th>
            <td>{order_email}</td>
        </tr>
        <tr>
            <th>order_custom1</th>
            <td>{order_custom1}</td>
        </tr>
        <tr>
            <th>order_custom2</th>
            <td>{order_custom2}</td>
        </tr>
        <tr>
            <th>order_custom3</th>
            <td>{order_custom3}</td>
        </tr>
        <tr>
            <th>order_custom4</th>
            <td>{order_custom4}</td>
        </tr>
        <tr>
            <th>order_custom5</th>
            <td>{order_custom5}</td>
        </tr>
        <tr>
            <th>order_custom6</th>
            <td>{order_custom6}</td>
        </tr>
        <tr>
            <th>order_custom7</th>
            <td>{order_custom7}</td>
        </tr>
        <tr>
            <th>order_custom8</th>
            <td>{order_custom8}</td>
        </tr>
        <tr>
            <th>order_custom9</th>
            <td>{order_custom9}</td>
        </tr>
    </table>

</div>
