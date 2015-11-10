<?= form_open($post_url) ?>
<fieldset style="margin-bottom: 1em">
    <legend><?= lang('store.defaults') ?></legend>
    <table>
        <tr>
            <td>
                <?= lang('store.settings.default_country').':  '?>
            </td>
            <td>
                <select name="default[country_code]" class="store_country_select"><?= $country_options ?></select>
            </td>
        </tr>
        <tr>
            <td>
                <?= lang('store.settings.default_state').':  '?>
            </td>
            <td>
                <select name="default[state_code]" class="store_state_select"><?= $state_options ?></select>
            </td>
        </tr>
    </table>
<?= form_submit(array('name' => 'submit_default', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</fieldset>
<?= form_close() ?>

<?= form_open($post_url) ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        lang('store.country'),
        lang('store.code'),
        lang('store.status'),
        array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
    );

    foreach ($countries as $country) {
        $this->table->add_row(
            '<a href="'.$edit_url.$country->id.'">'.$country->name.'</a>',
            $country->code,
            store_enabled_str($country->enabled),
            form_checkbox('selected[]', $country->id)
        );
    }

    echo $this->table->generate();
?>

<div style="text-align: right;">
    <?= form_dropdown('with_selected', array('enable' => lang('store.enable_selected'), 'disable' => lang('store.disable_selected'))) ?>
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>
