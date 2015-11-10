<div style="text-align: right; margin: 5px 0 15px 0;">
    <a href="<?= $edit_url ?>new" class="submit"><?= lang('store.status_add') ?></a>
</div>

<?= form_open($post_url) ?>

<?php
    $this->table->clear();
    $this->table->set_template($store_sortable_table_template);
    $this->table->set_heading(array(
        array('data' => '', 'width' => '2%'),
        array('data' => lang('name')),
        array('data' => lang('store.status_color')),
        array('data' => lang('store.status_email_ids')),
    ));

    foreach ($statuses as $status) {
        $status_name = $status->is_default ?
             '<strong><a href="'.$edit_url.$status->id.'">'.store_order_status_name($status->name).'</a></strong> ('.lang('store.default').')' :
             '<a href="'.$edit_url.$status->id.'">'.store_order_status_name($status->name).'</a>';

        $this->table->add_row(array(
            '<div class="store_sortable_handle"></div>',
            form_hidden('sorted_ids[]', $status->id).$status_name,
            $status->color ? '<span style="color:'.$status->color.'">'.$status->color.'</span>' : lang('store.default'),
            $status->getEmailNames(),
        ));
    }

    echo $this->table->generate();
?>

<?= form_close() ?>
