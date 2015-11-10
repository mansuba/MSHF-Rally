<?php $form = store_form($transaction); echo $form->open(); ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_fixed_table_template);

    $this->table->add_row(array(
        $form->label('order_id'),
        $order->id,
    ));

    $this->table->add_row(array(
        $form->label('owing'),
        store_currency($order->order_owing),
    ));

    $this->table->add_row(array(
        $form->label('amount'),
        $form->currency('amount').$form->error('amount'),
    ));

    $this->table->add_row(array(
        $form->label('date'),
        $form->datetime('date').$form->error('date'),
    ));

    $this->table->add_row(array(
        $form->label('message'),
        $form->input('message'),
    ));

    $this->table->add_row(array(
        $form->label('reference'),
        $form->input('reference'),
    ));

    echo $this->table->generate();
?>

<div style="clear: left; text-align: right;">
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
</div>

<?= $form->close() ?>
