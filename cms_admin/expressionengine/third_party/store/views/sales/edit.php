<?php
    echo $form->open();

    $this->table->clear();
    $this->table->set_template($store_fixed_table_template);
    $this->table->set_caption(lang('store.sale_details'));

    $this->table->add_row(
        $form->label('name', null, array('required' => true)),
        $form->input('name').$form->error('name')
    );

    $this->table->add_row(
        $form->label('start_date_str'),
        $form->input('start_date_str', array('class' => 'store_datetime'))
    );

    $this->table->add_row(
        $form->label('end_date_str'),
        $form->input('end_date_str', array('class' => 'store_datetime'))
    );

    $this->table->add_row(
        $form->label('member_group_ids'),
        $form->select('member_group_ids', $member_groups, array('multiple' => true))
    );

    $this->table->add_row(
        $form->label('entry_ids'),
        $form->select('entry_ids', $product_options, array('multiple' => true))
    );

    $this->table->add_row(
        $form->label('category_ids'),
        $form->select('category_ids', $category_options, array('multiple' => true))
    );

    $this->table->add_row(
        $form->label('per_item_discount'),
        $form->currency('per_item_discount')
    );

    $this->table->add_row(
        $form->label('percent_discount'),
        $form->input('percent_discount')
    );

    $this->table->add_row(
        $form->label('notes'),
        $form->text('notes', array('rows' => 5))
    );

    $this->table->add_row(
        $form->label('enabled'),
        $form->checkbox('enabled')
    );

    echo $this->table->generate();
?>

<div style="clear: left; text-align: right;">
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</div>

<?= $form->close() ?>
