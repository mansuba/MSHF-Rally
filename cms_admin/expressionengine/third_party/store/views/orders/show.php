<div class="container-fluid container-paddingtb">

<div class= "store_invoice_options" style='float: right;'>
    <?php if ($invoice_link): ?>
        <a href="<?= $invoice_link; ?>" class="submit" target="_blank"><?= lang('store.show_invoice'); ?></a>
    <?php endif; ?>
    <a href="<?= $print_url; ?>" class="submit"><?= lang('store.print') ?></a>
</div>

<label class="store_hide_field">
    <img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" width="10" height="13" alt="" />
    <?= lang('store.order_details') ?>
</label>
<div class="store_field_pane">
<table cellspacing="0" cellpadding="0" border="0" class="mainTable store_table">
    <tr>
        <th style="width: 10%;"><?= lang('store.order_id') ?></th>
        <th style="width: 15%;"><?= lang('store.member') ?></th>
        <th style="width: 15%;"><?= lang('store.order_total') ?></th>
        <th style="width: 15%;"><?= lang('store.payment_method') ?></th>
        <th style="width: 15%;"><?= lang('store.paid?') ?></th>
        <th style="width: 30%;"><?= lang('store.orders.order_status') ?></th>
    </tr>
    <tr>
        <td><?= $order->id ?></td>
        <td><?= store_member_link($order->member) ?></td>
        <td><?= store_currency($order->order_total) ?></td>
        <td><?= $order->payment_method ?></td>
        <td><?= store_order_paid($order) ?></td>
        <td>
            <?= store_order_status($order) ?>
            <?php if ($order->order_completed_date): ?>
                (<?= $this->localize->human_time($order->order_status_updated) ?>)
                <a id="store_status_edit" href="#"><?= lang('store.edit_status') ?></a>

                <div id="store_status_form" style="display: none;">
                    <br />
                    <?= form_open($update_status_url).
                        lang('store.orders.order_status').': '.form_dropdown('status_id', $status_select_options).BR.BR.
                        lang('store.message').': '.form_input('message','','width=10%, style=width:50%').BR.BR.
                        form_hidden('order_id', (int) $order->id) ?>
                        <div style="text-align:right">
                            <a id="store_status_cancel" href="#"><?= lang('cancel') ?></a> &nbsp;&nbsp;
                            <?= form_submit(array('name' => 'action_submit', 'value' => lang('store.submit'), 'class' => 'submit', 'id' => 'status_submit')) ?>
                        </div>
                    <?= form_close(); ?>
                </div>
            <?php endif ?>
        </td>
    </tr>
</table>

<table style="width:100%;" rules="none" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td style="vertical-align: top; width: 50%; padding-right: 0.5em;">
            <table class="mainTable store_table" width="100%" cellspacing="0" cellpadding="10" border="0" style="padding-right:10px;">
                <tr>
                    <td class="top_td_no_header"><strong><?= lang('store.billing_name') ?></strong></td>
                    <td class="top_td_no_header"><?= $order->billing_name ?></td>
                </tr>
                <tr>
                    <td><strong><?= lang('store.billing_company') ?></strong></td>
                    <td><?= $order->billing_company ?></td>
                </tr>
                <tr>
                    <td><strong><?= lang('store.billing_address') ?></strong></td>
                    <td><?= $order->billing_address_full ?></td>
                </tr>
                <tr>
                    <td><strong><?= lang('store.billing_phone') ?></strong></td>
                    <td><?= $order->billing_phone ?></td>
                </tr>
                <tr>
                    <td><strong><?= lang('store.order_email') ?></strong></td>
                    <td><?= $order->order_email ?></td>
                </tr>
                <?php foreach ($order_fields as $field_name => $field): ?>
                    <?php if (strpos($field_name, 'order_custom') !== FALSE): ?>
                        <?php if ($field['title'] != '' || $order->$field_name != ''): ?>
                            <tr>
                                <td><strong><?= $field['title'] == '' ? $field_name : $field['title'] ?></strong></td>
                                <td><?= $order->$field_name ?></td>
                            </tr>
                        <?php endif ?>
                    <?php endif ?>
                <?php endforeach ?>
            </table>
        </td>
        <td style="vertical-align: top; width: 50%; padding-left: 0.5em;">
            <table class="mainTable store_table" width="100%" cellspacing="0" cellpadding="10" border="0" style="padding-left:10px;">
                <tr>
                    <td class="top_td_no_header"><strong><?= lang('store.shipping_name') ?></strong></td>
                    <td class="top_td_no_header"><?= $order->shipping_name ?></td>
                </tr>
                <tr>
                    <td><strong><?= lang('store.shipping_company') ?></strong></td>
                    <td><?= $order->shipping_company ?></td>
                </tr>
                <tr>
                    <td><strong><?= lang('store.shipping_address') ?></strong></td>
                    <td><?= $order->shipping_address_full ?></td>
                </tr>
                <tr>
                    <td><strong><?= lang('store.shipping_phone') ?></strong></td>
                    <td><?= $order->shipping_phone ?></td>
                </tr>
                <tr>
                    <td><strong><?= lang('store.shipping_method') ?></strong></td>
                    <td><?= $order->shipping_method_name ?></td>
                </tr>
                <?php if ($order->promo_code): ?>
                    <tr>
                        <td><strong><?= lang('store.promo_code') ?></strong></td>
                        <td><?= $order->promo_code ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table>
</div>

<label class="store_hide_field">
    <img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" width="10" height="13" alt="" />
    <?= lang('store.items') ?>
</label>
<div class="store_field_pane">
<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        lang('store.#'),
        lang('store.product'),
        lang('store.sku'),
        lang('store.modifiers'),
        lang('store.price'),
        array('data' => lang('store.quantity'), 'style'=>'width:15%'),
        lang('store.total')
    );

    foreach ($order->items as $item) {
        $this->table->add_row(
            $item->entry_id,
            $item->title,
            $item->sku,
            $item->modifiers_html,
            store_currency($item->price),
            $item->item_qty,
            store_currency($item->item_subtotal)
        );
    }

    $this->table->add_row(
        array('data' => '', 'colspan' => 4),
        array('data' => lang('store.order_subtotal'), 'colspan' => 2, 'style' => 'font-weight:bold'),
        array('data' => store_currency($order->order_subtotal), 'style' => 'font-weight:bold')
    );

    if (count($order->adjustments) > 0) {
        foreach ($order->adjustments as $adjustment) {
            $this->table->add_row(
                array('data' => '', 'colspan' => 4),
                array('data' => $adjustment->label, 'colspan' => 2),
                store_currency($adjustment->amount)
            );
        }
    } elseif ($order->order_discount > 0 || $order->order_shipping > 0 || $order->order_tax > 0) {
        // legacy attributes for orders without adjustments
        $this->table->add_row(array('data' => '', 'colspan' => 4),
            array('data' => lang('store.order_discount'), 'colspan' => 2),
            store_currency(-$order->order_discount));

        $this->table->add_row(array('data' => '', 'colspan' => 4),
            array('data' => lang('store.order_shipping').' ('.$order->shipping_method_name.')', 'colspan' => 2),
            store_currency($order->order_shipping));

        $this->table->add_row(array('data' => '', 'colspan' => 4),
            array('data' => empty($order->tax_name) ? lang('store.order_tax') : $order->tax_name.' ('.((double) $order->tax_rate*100).'%)', 'colspan' => 2),
            store_currency($order->order_tax));
    }

    $this->table->add_row(array('data' => '', 'colspan' => 4),
        array('data' => lang('store.order_total'), 'colspan' => 2, 'style' => 'font-weight:bold'),
        array('data' => store_currency($order->order_total), 'style' => 'font-weight:bold'));

    $this->table->add_row(array('data' => '', 'colspan' => 4),
        array('data' => lang('store.paid'), 'colspan' => 2),
        store_currency($order->order_paid));

    $this->table->add_row(array('data' => '', 'colspan' => 4),
        array('data' => lang('store.balance_due'), 'colspan' => 2, 'style' => 'font-weight:bold'),
        array('data' => store_currency($order->order_owing), 'style' => 'font-weight:bold'));

    echo $this->table->generate();
?>
</div>

<label class="store_hide_field">
    <img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" width="10" height="13" alt="" />
    <?= lang('store.transactions') ?>
</label>
<div class="store_field_pane">
<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(array(
        '#',
        lang('date'),
        lang('store.payment_method'),
        lang('type'),
        lang('store.amount'),
        lang('store.status'),
        lang('store.reference'),
        lang('store.message'),
        lang('store.recorded_by'),
        array('data' => lang('store.actions'), 'class' => 'store_transaction_actions'),
    ));

    foreach ($transactions as $transaction) {
        $this->table->add_row(array(
            $transaction->id,
            $this->localize->human_time($transaction->date),
            $transaction->payment_method,
            store_transaction_status($transaction->type),
            store_currency($transaction->amount),
            store_transaction_status($transaction->status),
            $transaction->reference,
            $transaction->message,
            store_member_link($transaction->member, lang('store.system')),
            array('data' => store_transaction_actions($transaction), 'class' => 'store_transaction_actions'),
        ));
    }

    if (count($transactions) == 0) {
        $this->table->add_row(array('data' => '<i>'.lang('store.no_transactions').'</i>', 'colspan' => 10));
    }

    echo $this->table->generate();
?>

<?php if ($can_add_payments): ?>
    <div class="store_new_payment_link" style="clear: left;">
        <a href="<?= $new_payment_url ?>" class="submit"><?= lang('store.new_payment') ?></a>
    </div>
<?php endif ?>

</div>

<label class="store_hide_field">
    <img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" width="10" height="13" alt="" />
    <?= lang('store.order_status_history') ?>
</label>
<div class="store_field_pane">
<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        lang('store.status'),
        lang('store.order_status_updated'),
        lang('store.updated_by'),
        lang('store.message')
    );

    foreach ($history as $item) {
        $this->table->add_row(
            store_order_status_name($item->order_status_name),
            $this->localize->human_time($item->order_status_updated),
            store_member_link($item->member, lang('store.system')),
            $item->order_status_message
        );
    }

    echo $this->table->generate();
?>
</div>


</div> <!-- .container -->