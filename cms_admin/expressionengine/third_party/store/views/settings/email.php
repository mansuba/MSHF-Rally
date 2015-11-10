<?= form_open($post_url) ?>

<div style="text-align: right; margin: 5px 0 15px 0;">
    <a href="<?= $edit_url ?>new" class="submit"><?= lang('store.new_email_template') ?></a>
</div>

<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        lang('store.email_name'),
        lang('store.email_subject'),
        lang('store.status'),
        array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
    );

    $i = 0;
    foreach ($emails as $email) {
        $this->table->add_row(
            '<a href="'.$edit_url.$email->id.'">'.store_email_template_name($email->name).'</a>',
            $email->subject,
            store_enabled_str($email->enabled),
            form_checkbox('selected[]', $email->id, false));
    }

    echo $this->table->generate();
?>

<div style="text-align: right;">
    <?= form_dropdown('with_selected', array('enable' => lang('store.enable_selected'), 'disable' => lang('store.disable_selected'), 'delete' => lang('store.delete_selected'))) ?>
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>
