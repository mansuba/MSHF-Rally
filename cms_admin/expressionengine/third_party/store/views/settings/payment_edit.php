<?= form_open($post_url); ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        array('data' => '', 'style' => 'width:40%'),
        array('data' => ''));

    $this->table->add_row(
        lang('store.payment_method', 'payment_method'),
        $title
    );

    $this->table->add_row(
        lang('store.short_name', 'short_name'),
        $short_name
    );

    foreach ($default_settings as $key => $default) {
        $this->table->add_row(
            '<strong>'.lang(snake_case("store.payment.$key"), "settings_$key").'</strong>',
            store_setting_input($key, $default, $settings[$key])
        );
    }

    $this->table->add_row(
        lang('store.enabled', 'payment_method_enabled'),
        store_form_checkbox('enabled', $enabled)
    );

    echo $this->table->generate();
?>

<p><strong class="notice">*</strong> <?= lang('required_fields') ?></p>
<div style="text-align: right;">
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
</div>

<?= form_close(); ?>
