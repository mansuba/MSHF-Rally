<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?= $report_title ?></title>
    <style type="text/css">
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 50%; }
        table { width: 100%; border-collapse: collapse; }
        table td, table th { text-align: left; border: 1px solid black; padding: 0.5em; margin: 0px; }
        table.no_border td { border: none; }
        table td.empty { border: none; background-color: #FFFFFF; }
        table tr.even { background-color: #EBF0F2; }
        table tr.odd { background-color: #F4F6F6; }
        div.header_right { text-align: right; }
    </style>
</head>
<body>
    <?php $table_open = '<table class="mainTable store_table">'; ?>

    <div class="report">

    <h1><?= $report_title ?></h1>
    <div class="header_right"><?= $header_right ?></div>
    <strong><?= lang('store.order_details') ?></strong>
    <br />
    <?= $table_open ?>
        <thead>
        <tr>
            <th style="width: 10%;"><?= lang('store.order_id') ?></th>
            <th style="width: 15%;"><?= lang('store.member') ?></th>
            <th style="width: 15%;"><?= lang('store.order_total') ?></th>
            <th style="width: 15%;"><?= lang('store.payment_method') ?></th>
            <th style="width: 15%;"><?= lang('store.paid?') ?></th>
            <th style="width: 30%;"><?= lang('store.orders.order_status') ?></th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td style="width: 10%;"><?= $order->id ?></td>
            <td style="width: 15%;"><?= $order->screen_name ?></td>
            <td style="width: 15%;"><?= store_currency($order->order_total) ?></td>
            <td style="width: 15%;"><?= $order->payment_method ?></td>
            <td style="width: 15%;"><?= store_order_paid($order) ?></td>
            <td style="width: 30%">
                <?= store_order_status($order) ?>
                <?php if ($order->order_completed_date): ?>
                    (<?= $this->localize->human_time($order->order_status_updated) ?>)
                <?php endif ?>
            </td>
        </tr>
        </tbody>
    </table>
    <table class="no_border">
        <tr>
            <td style="vertical-align:top">
                <table class="mainTable store_table" style="padding-right:10px;">
                    <tr>
                        <td style="width:40%" class="top_td_no_header"><strong><?= lang('store.billing_name') ?></strong></td>
                        <td style="width:60%" class="top_td_no_header"><?= $order->billing_name ?></td>
                    </tr>
                    <tr>
                        <td style="width:40%"><strong><?= lang('store.billing_company') ?></strong></td>
                        <td style="width:60%"><?= $order->billing_company ?></td>
                    </tr>
                    <tr>
                        <td style="width:40%"><strong><?= lang('store.billing_address') ?></strong></td>
                        <td style="width:60%"><?= $order->billing_address_full ?></td>
                    </tr>
                    <tr>
                        <td style="width:40%"><strong><?= lang('store.billing_phone') ?></strong></td>
                        <td style="width:60%"><?= $order->billing_phone ?></td>
                    </tr>
                    <tr>
                        <td style="width:40%"><strong><?= lang('store.order_email') ?></strong></td>
                        <td style="width:60%"><?= $order->order_email ?></td>
                    </tr>
                    <?php foreach ($order_fields as $field_name => $field): ?>
                        <?php if (strpos($field_name, 'order_custom') !== FALSE): ?>
                            <?php if ($field['title'] != '' || $order->$field_name != ''): ?>
                                <tr>
                                    <td style="width:40%"><strong><?= $field_name ?></strong></td>
                                    <td style="width:60%"><?= $order->$field_name ?></td>
                                </tr>
                            <?php endif ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            </td>
            <td style="vertical-align:top">
                <table class="mainTable store_table" style="padding-left:10px;">
                    <tr>
                        <td style="width:40%" class="top_td_no_header"><strong><?= lang('store.shipping_name') ?></strong></td>
                        <td style="width:60%" class="top_td_no_header"><?= $order->shipping_name ?></td>
                    </tr>
                    <tr>
                        <td style="width:40%"><strong><?= lang('store.shipping_company') ?></strong></td>
                        <td style="width:60%"><?= $order->shipping_company ?></td>
                    </tr>
                    <tr>
                        <td style="width:40%"><strong><?= lang('store.shipping_address') ?></strong></td>
                        <td style="width:60%"><?= $order->shipping_address_full ?></td>
                    </tr>
                    <tr>
                        <td style="width:40%"><strong><?= lang('store.shipping_phone') ?></strong></td>
                        <td style="width:60%"><?= $order->shipping_phone ?></td>
                    </tr>
                    <tr>
                        <td style="width:40%"><strong><?= lang('store.shipping_method') ?></strong></td>
                        <td style="width:60%"><?= $order->shipping_method_name ?></td>
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
    <br />
    <strong><?= lang('store.items') ?></strong>
    <br />
    <?= $table_open ?>
        <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:20%"><?= lang('store.product') ?></th>
            <th style="width:13%"><?= lang('store.sku') ?></th>
            <th style="width:32%"><?= lang('store.modifiers') ?></th>
            <th style="width:10%"><?= lang('store.price') ?></th>
            <th style="width:10%"><?= lang('store.quantity') ?></th>
            <th style="width:10%"><?= lang('store.total') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($order->items as $item): ?>
            <tr>
                <td style="width:5%"><?= $item->entry_id ?></td>
                <td style="width:20%"><?= $item->title ?></td>
                <td style="width:13%"><?= $item->sku ?></td>
                <td style="width:32%"><?= $item->modifiers_html ?></td>
                <td style="width:10%"><?= store_currency($item->price) ?></td>
                <td style="width:10%"><?= $item->item_qty ?></td>
                <td style="width:10%"><?= store_currency($item->item_subtotal) ?></td>
            </tr>
        <?php endforeach ?>
        <tr>
            <td colspan="4" class="empty"></td>
            <td colspan="2"><?= lang('store.order_subtotal') ?></td>
            <td><?= store_currency($order->order_subtotal) ?></td>
        </tr>
        <tr>
            <td colspan="4" class="empty"></td>
            <td colspan="2"><?= lang('store.order_discount') ?></td>
            <td><?= store_currency($order->order_discount) ?></td>
        </tr>
        <tr>
            <td colspan="4" class="empty"></td>
            <td colspan="2"><?= lang('store.order_shipping').' ('.$order->shipping_method_name.')' ?></td>
            <td><?= store_currency($order->order_shipping) ?></td>
        </tr>
        <tr>
            <td colspan="4" class="empty"></td>
            <td colspan="2"><?= empty($order->tax_name) ? lang('store.order_tax') : lang('store.order_tax').' ('.$order->tax_name.' @ '.((double) $order->tax_rate*100).'%)' ?></td>
            <td><?= store_currency($order->order_tax) ?></td>
        </tr>
        <tr>
            <td colspan="4" class="empty"></td>
            <td colspan="2" style="font-weight:bold"><?= lang('store.order_total') ?></td>
            <td style="font-weight:bold"><?= store_currency($order->order_total) ?></td>
        </tr>
        <tr>
            <td colspan="4" class="empty"></td>
            <td colspan="2"><?= lang('store.paid') ?></td>
            <td><?= store_currency($order->order_paid) ?></td>
        </tr>
        <tr>
            <td colspan="4" class="empty"></td>
            <td colspan="2" style="font-weight:bold"><?= lang('store.balance_due') ?></td>
            <td style="font-weight:bold"><?= store_currency($order->order_owing) ?></td>
        </tr>
        </tbody>
    </table>
    <br />
    <strong><?= lang('store.payments') ?></strong>
    <br />
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
        ));

        foreach ($transactions as $transaction) {
            $this->table->add_row(array(
                $transaction->id,
                $this->localize->human_time($transaction->date),
                $transaction->payment_method,
                lang('store.transaction_'.$transaction->type),
                store_currency($transaction->amount),
                lang('store.transaction_'.$transaction->status),
                $transaction->reference,
                $transaction->message,
                store_member_link($transaction->member, lang('store.system')),
            ));
        }

        if (count($transactions) == 0) {
            $this->table->add_row(array('data' => '<i>'.lang('store.no_transactions').'</i>', 'colspan' => 10));
        }

        echo $this->table->generate();
    ?>

    <div><?= $footer ?></div>

    </div>
</body>
</html>
