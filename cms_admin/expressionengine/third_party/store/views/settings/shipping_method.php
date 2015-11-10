<?= form_open($post_url) ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        array('data' => lang('preference'), 'width' => "40%"),
        array('data' => lang('setting')));

    $this->table->add_row(
        lang('name', 'method[name]'),
        form_input('method[name]', $method->name).
        form_error('method[name]')
    );

    $this->table->add_row(
        lang('store.enabled', 'method[enabled]'),
        store_form_checkbox('method[enabled]', $method->enabled)
    );

    echo $this->table->generate();

?>

<div style="text-align: right;">
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>

<?php if ($method->exists): ?>

    <h3 style="padding: 0.5em 0">Shipping Rules</h3>

    <div style="float: right; padding: 1em 0;">
        <a href="<?= $edit_url ?>new" class="submit"><?= lang('store.shipping_rule_add') ?></a>
    </div>
    <ul class="bulleted" style="padding: 0 1em 1em 1em; font-style: italic;">
        <?= lang('store.shipping_default_help') ?>
    </ul>

    <?= form_open($post_url) ?>

    <?php
        $this->table->clear();
        $this->table->set_template($store_sortable_table_template);
        $this->table->set_heading(
            array('data' => '', 'width' => '2%'),
            array('data' => lang('store.country'), 'width' => '10%'),
            array('data' => lang('store.state'), 'width' => '10%'),
            array('data' => lang('store.postcode'), 'width' => '7%'),
            array('data' => lang('store.items'), 'width' => '7%'),
            array('data' => lang('store.order_total'), 'width' => '10%'),
            array('data' => lang('store.weight_with_units'), 'width' => '10%'),
            array('data' => lang('store.shipping_charges'), 'width' => ''),
            array('data' => lang('store.enabled'), 'width' => '5%'),
            array('data' => lang('store.options'), 'width' => '5%'),
            array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%'));

        $counter = 0;
        foreach ($rules as $rule) {
            $counter++;
            $this->table->add_row(array(
                '<div class="store_sortable_handle"></div>',
                form_hidden('sorted_ids[]', $rule->id).
                $rule->country_name,
                $rule->state_name,
                $rule->postcode,
                $rule->order_qty_text,
                $rule->order_total_text,
                $rule->weight_text,
                $rule->rate_text,
                store_enabled_str($rule->enabled),
                '<a href="'.$edit_url.$rule->id.'">'.lang('edit').'</a>',
                form_checkbox('selected[]', $rule->id),
            ));
        }

        echo $this->table->generate();
    ?>

    <div style="text-align: right;">
        <?= form_dropdown('with_selected', array('enable' => lang('store.enable_selected'), 'disable' => lang('store.disable_selected'), 'delete' => lang('store.delete_selected'))) ?>
        <?= form_submit(array('name' => 'submit_selected', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
    </div>

    <?= form_close() ?>

<?php endif ?>
