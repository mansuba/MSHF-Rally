<div class="container-fluid container-paddingtb">

<?= form_open($post_url) ?>

<p><b><?= lang('store.delete_orders_question') ?></b></p>

<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(array(
        array('data' => '#', 'width' => "2%"),
        lang('store.member'),
        lang('store.billing_name'),
        lang('store.order_date'),
        lang('store.total'),
        lang('store.paid?'),
        lang('store.status'),
    ));

    foreach ($orders as $order) {
        $this->table->add_row(array(
            form_hidden('selected[]', $order->id).$order->id,
            $order->member ? $order->member->screen_name : null,
            $order->billing_name,
            $this->localize->human_time($order->order_date),
            store_currency($order->order_total),
            store_order_paid($order),
            $order->order_status_name,
        ));
    }

    echo $this->table->generate();
?>

<div class="store_actions">
    <p class="notice"><?= lang('store.delete_warning') ?></p>
    <p><?= form_submit(array('name' => 'submit', 'value' => lang('store.delete'), 'class' => 'submit')) ?></p>
</div>

<?= form_close() ?>

</div>