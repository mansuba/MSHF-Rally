<div class="container-fluid container-paddingtb">

<div style="text-align: right; margin: 5px 0 15px 0;">
    <a href="<?= $edit_url ?>new" class="submit"><?= lang('store.sale_new') ?></a>
</div>

<?= form_open($post_url) ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_sortable_table_template);
    $this->table->set_heading(
        array('data' => NBS, 'width' => '2%'),
        array('data' => '#', 'width' => '2%'),
        lang('name'),
        lang('store.sale_start_date'),
        lang('store.sale_end_date'),
        lang('store.per_item_discount'),
        lang('store.percent_discount'),
        lang('store.status'),
        array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
    );

    foreach ($sales as $sale) {
        $this->table->add_row(
            '<div class="store_sortable_handle"></div>',
            form_hidden('sorted_ids[]', $sale->id).$sale->id,
            '<a href="'.$edit_url.$sale->id.'">'.$sale->name.'</a>',
            $sale->start_date_str,
            $sale->end_date_str,
            store_currency($sale->per_item_discount),
            $sale->percent_discount ? ((float) $sale->percent_discount).'%' : null,
            store_enabled_str($sale->enabled),
            form_checkbox('selected[]', $sale->id, false)
        );
    }

    if (!count($sales)) {
        $this->table->add_row(array('data' => '<i>'.lang('store.no_sales').'</i>', 'colspan' => 11));
    }

    echo $this->table->generate();
?>

<div style="text-align: right;">
    <?= form_dropdown('with_selected', array('enable' => lang('store.enable_selected'), 'disable' => lang('store.disable_selected'), 'delete' => lang('store.delete_selected'))) ?>
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>

</div>
