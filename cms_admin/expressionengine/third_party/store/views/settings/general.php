<?php $form = store_form(); echo $form->open(); ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        array('data' => lang('preference'), 'style' => "width:40%"),
        array('data' => lang('setting')));

    foreach ($setting_defaults as $key => $default) {
        $label = '<strong>'.lang(preg_replace('/^store_/', 'store.settings.', $key), "settings_$key").'</strong>';

        $subtext_key = preg_replace('/^store_/', 'store.settings.', $key.'_subtext');
        if (store_lang_exists($subtext_key)) {
            $label .= '<div class="subtext">'.lang($subtext_key).'</div>';
        }

        $this->table->add_row($label, store_setting_input($key, $default, $settings[$key]));
    }

    echo $this->table->generate();
?>

<div style="text-align: right;">
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
</div>

<?= $form->close(); ?>
