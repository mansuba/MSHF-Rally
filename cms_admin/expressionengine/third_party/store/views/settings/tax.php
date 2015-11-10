<div style="text-align: right; margin: 5px 0 15px 0;">
    <a href="<?= $edit_link ?>new" class="submit"><?= lang('store.tax_rate_add') ?></a>
</div>

<?= form_open($post_url) ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_sortable_table_template);
    $this->table->set_heading(
        array('data' => '', 'width' => '2%'),
        array('data' => lang('store.tax_name'), 'width' => '20%'),
        array('data' => lang('store.country'), 'width' => '20%'),
        array('data' => lang('store.state'), 'width' => '20%'),
        array('data' => lang('store.tax_rate_percent'), 'width' => '15%'),
        lang('store.status'),
        array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
    );

    foreach ($tax_rates as $tax) {
        $this->table->add_row(
            '<div class="store_sortable_handle"></div>',
            form_hidden('sorted_ids[]', $tax->id).
            '<a href="'.$edit_link.$tax->id.'">'.$tax->name.'</a>',
            $tax->country_name,
            $tax->state_name,
            $tax->rate_percent,
            store_enabled_str($tax->enabled),
            form_checkbox("selected[]", $tax->id)
        );
    }

    if (empty($tax_rates)) {
        $this->table->add_row(array(
            'colspan' => 5,
            'style' => 'font-style:italic',
            'data' => lang('store.no_tax_rates'),
        ));
    }

    echo $this->table->generate();
?>

<div style="text-align: right;">
    <?= form_dropdown('with_selected', array('enable' => lang('store.enable_selected'), 'disable' => lang('store.disable_selected'), 'delete' => lang('store.delete_selected'))) ?>
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
</div>

<?= form_close() ?>
