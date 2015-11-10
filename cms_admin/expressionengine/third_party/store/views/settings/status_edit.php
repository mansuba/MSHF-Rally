<?php if ($locked): ?>
    <p style="margin-bottom: 1em;"><strong class="notice"><?= lang('store.status_locked') ?></strong></p>
<?php endif; ?>

<?php
    echo $form->open();

    $this->table->set_template($store_fixed_table_template);
    $this->table->set_heading(array(
        lang('store.status_field_name'),
        lang('store.status_field_value'),
    ));

    $this->table->add_row(array(
        $form->label('name', 'name', array('required' => true)),
        $form->input('name', array('disabled' => $locked)).$form->error('name')
    ));

    $this->table->add_row(array(
        $form->label('color'),
        $form->input('color', array('placeholder' => lang('store.default'))).BR.
        '<a href="#" class="store_colorswatch"></a>'.
        '<a href="#" class="store_colorswatch" data-color="#e7174b" style="background-color: #e7174b"></a>'.
        '<a href="#" class="store_colorswatch" data-color="#f77400" style="background-color: #f77400"></a>'.
        '<a href="#" class="store_colorswatch" data-color="#009933" style="background-color: #009933"></a>'.
        '<a href="#" class="store_colorswatch" data-color="#02d7e1" style="background-color: #02d7e1"></a>'.
        '<a href="#" class="store_colorswatch" data-color="#0b02e1" style="background-color: #0b02e1"></a>'.
        '<a href="#" class="store_colorswatch" data-color="#e102d8" style="background-color: #e102d8"></a>'
    ));

    $this->table->add_row(array(
        $form->label('email_ids'),
        $form->select('email_ids', $emails, array('multiple' => true)),
    ));

    $this->table->add_row(array(
        $form->label('is_default'),
        $form->checkbox('is_default'),
    ));

    echo $this->table->generate();
?>

<div class="store_actions">
    <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
</div>

<?php if (!$locked): ?>
    <div class="store_actions_left">
        <?= form_submit(array('name' => 'delete', 'value' => lang('store.delete'), 'class' => 'submit')); ?>
    </div>
<?php endif ?>

<?= $form->close(); ?>
