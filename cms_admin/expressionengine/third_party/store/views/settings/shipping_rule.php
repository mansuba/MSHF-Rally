<?php $form = store_form($shipping_rule); echo $form->open(); ?>

<p class="notice"><?= $this->session->flashdata('store_shipping_rule') ?></p>

<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        array('data' => lang('store.shipping_filters'), 'width' => "40%"),
        array('data' => '<i>'.lang('store.shipping_filters_desc').'</i>'));

    $this->table->add_row(
        $form->label('country_code', 'store.country'),
        $form->select('country_code', $country_options, array('class' => 'store_country_select'))
    );

    $this->table->add_row(
        $form->label('state_code', 'store.state'),
        $form->select('state_code', $state_options, array('class' => 'store_state_select'))
    );

    $this->table->add_row($form->label('postcode'), $form->input('postcode'));
    $this->table->add_row($form->label('min_order_qty'), $form->input('min_order_qty'));
    $this->table->add_row($form->label('max_order_qty'), $form->input('max_order_qty'));
    $this->table->add_row($form->label('min_order_total'), $form->currency('min_order_total'));
    $this->table->add_row($form->label('max_order_total'), $form->currency('max_order_total'));
    $this->table->add_row($form->label('min_weight'), $form->decimal('min_weight'));
    $this->table->add_row($form->label('max_weight'), $form->decimal('max_weight'));

    echo $this->table->generate();

    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        array('data' => lang('store.shipping_charges'), 'width' => "40%"),
        array('data' => ''));

    $this->table->add_row($form->label('base_rate'), $form->currency('base_rate'));
    $this->table->add_row($form->label('per_item_rate'), $form->currency('per_item_rate'));
    $this->table->add_row($form->label('per_weight_rate'), $form->currency('per_weight_rate'));
    $this->table->add_row($form->label('percent_rate'), $form->decimal('percent_rate'));
    $this->table->add_row($form->label('min_rate'), $form->currency('min_rate'));
    $this->table->add_row($form->label('max_rate'), $form->currency('max_rate'));

    echo $this->table->generate();

    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        array('data' => '', 'width' => "40%"),
        array('data' => ''));

    $this->table->add_row($form->label('name', 'description'), $form->input('name'));
    $this->table->add_row($form->label('enabled'), $form->checkbox('enabled'));

    echo $this->table->generate();
?>

<div style="text-align: right;">
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>
