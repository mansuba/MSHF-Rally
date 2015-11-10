<?php $form = store_form($tax); echo $form->open(); ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        array('data' => '', 'width' => "40%"),
        array('data' => ''));

    $this->table->add_row(array(
        $form->label('name', null, array('required' => true)),
        $form->input('name').$form->error('name'),
    ));

    $this->table->add_row(array(
        $form->label('rate_percent'),
        $form->decimal('rate_percent').$form->error('rate_percent'),
    ));

    $this->table->add_row(array(
        $form->label('country_code', 'store.country'),
        $form->select('country_code', $country_options, array('class' => 'store_country_select')),
    ));

    $this->table->add_row(array(
        $form->label('state_code', 'store.state'),
        $form->select('state_code', $state_options, array('class' => 'store_state_select')),
    ));

    $this->table->add_row(array(
        $form->label('category_ids'),
        $form->select('category_ids', $category_options, array('multiple' => true)),
    ));

    $this->table->add_row(array(
        $form->label('apply_to_shipping'),
        $form->checkbox('apply_to_shipping'),
    ));

    $this->table->add_row(array(
        $form->label('included'),
        $form->checkbox('included'),
    ));

    $this->table->add_row(array(
        $form->label('enabled'),
        $form->checkbox('enabled'),
    ));

    echo $this->table->generate();
?>

<div style="text-align: right;">
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</div>

<?= $form->close() ?>
