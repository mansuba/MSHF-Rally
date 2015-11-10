<?php $form = store_form(null, 'settings'); echo $form->open(); ?>
<fieldset style="margin-bottom: 1em">
    <legend><?= lang('store.defaults') ?></legend>
    <?= lang('store.settings.default_shipping_method') ?> &nbsp;
    <?= $form->select(
        'store_default_shipping_method_id',
        $shipping_method_options,
        array('selected' => $default_shipping_method_id)
    ); ?> &nbsp;
    <?= form_submit(array('name' => 'submit_default', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</fieldset>
<?= $form->close() ?>

<div style="text-align: right; margin: 5px 0 15px 0;">
    <a href="<?= $edit_url ?>new" class="submit"><?= lang('store.shipping_method_add') ?></a>
</div>

<?= form_open($post_url) ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_sortable_table_template);
    $this->table->set_heading(
        array('data' => '&nbsp;', 'width' => '2%'),
        array('data' => '#', 'width' => '2%'),
        array('data' => lang('store.shipping_method'), 'width' => '60%'),
        array('data' => lang('store.status'), 'width' => '20%'),
        array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
    );

    foreach ($shipping_methods as $method) {
        $this->table->add_row(
            '<div class="store_sortable_handle"></div>',
            $method->id,
            form_hidden('sorted_ids[]', $method->id).
            '<a href="'.$edit_url.$method->id.'">'.$method->name.'</a>',
            store_enabled_str($method->enabled),
            form_checkbox("selected[]", $method->id)
        );
    }

    echo $this->table->generate();
?>

<div style="text-align: right;">
    <?= form_dropdown('with_selected', array('enable' => lang('store.enable_selected'), 'disable' => lang('store.disable_selected'), 'delete' => lang('store.delete_selected'))) ?>
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
</div>

<?= form_close() ?>
