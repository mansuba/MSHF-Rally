<?php
    echo $form->open();

    $this->table->clear();
    $this->table->set_template($store_fixed_table_template);
    $this->table->set_caption(lang('store.discount_details'));

    $this->table->add_row(
        $form->label('name', null, array('required' => true)),
        $form->input('name').$form->error('name')
    );

    $this->table->add_row(
        $form->label('code', 'store.promo_code'),
        $form->input('code')
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
        $form->label('exclude_on_sale'),
        $form->checkbox('exclude_on_sale')
    );

    $this->table->add_row(
        $form->label('type'),
        $form->select('type', array('items' => lang('store.discount_items'), 'bulk' => lang('store.discount_bulk')))
    );

    $this->table->add_row(
        $form->label('purchase_qty'),
        $form->input('purchase_qty')
    );

    $this->table->add_row(
        $form->label('purchase_total'),
        $form->currency('purchase_total')
    );

    $this->table->add_row(
        $form->label('step_qty'),
        $form->input('step_qty')
    );

    $this->table->add_row(
        $form->label('discount_qty'),
        $form->input('discount_qty')
    );

    $this->table->add_row(
        $form->label('base_discount'),
        $form->currency('base_discount')
    );

    $this->table->add_row(
        $form->label('per_item_discount'),
        $form->currency('per_item_discount')
    );

    $this->table->add_row(
        $form->label('percent_discount'),
        $form->percent('percent_discount')
    );

    $this->table->add_row(
        $form->label('free_shipping'),
        $form->checkbox('free_shipping')
    );

    $this->table->add_row(
        $form->label('per_user_limit'),
        $form->input('per_user_limit')
    );

    $this->table->add_row(
        $form->label('total_use_limit'),
        $form->input('total_use_limit')
    );

    if ($form->model->exists) {
        $this->table->add_row(
            $form->label('total_use_count'),
            $form->model->total_use_count
        );
    }

    $this->table->add_row(
        $form->label('break'),
        $form->checkbox('break')
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
