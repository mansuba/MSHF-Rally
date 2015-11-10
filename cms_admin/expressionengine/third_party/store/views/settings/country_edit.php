<?= form_open($post_url) ?>

<div id="store_edit_country_form">
    <?php
        $this->table->clear();
        $this->table->set_template($store_table_template);
        $this->table->set_heading(
            lang('store.state'),
            array('data' => lang('store.code'), 'width' => '20%'),
            array('data' => lang('store.delete'), 'width' => '5%')
        );

        foreach ($states as $key => $state) {
            $this->table->add_row(
                form_hidden("states[{$key}][id]", $state->id).
                form_input("states[{$key}][name]", $state->name).
                form_error("states[{$key}][name]"),
                form_input("states[{$key}][code]", $state->code).
                form_error("states[{$key}][code]"),
                store_form_checkbox("states[{$key}][delete]", $state->delete)
            );
        }

        $this->table->add_row(array(
            'data' => '<a id="store_settings_add_region" href="#">'.lang('store.add_state').'</a>',
            'colspan' => 3
        ));

        echo $this->table->generate();
    ?>

    <div style="clear: left; text-align: right;">
        <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
    </div>

</div>

<?= form_close() ?>

<script type="text/javascript">
$('#store_settings_add_region').click(function() {
    elemTr = $(document.createElement('tr'));
    var new_region_id = $('#store_edit_country_form tr').size();
    elemTr.append('<td><input type="text" name="states['+new_region_id+'][name]" /></td>');
    elemTr.append('<td><input type="text" name="states['+new_region_id+'][code]" /></td>');
    elemTr.append('<td><input type="checkbox" name="states['+new_region_id+'][delete]" value="1" /></td>');

    $('#store_settings_add_region').closest('tr').before(elemTr);

    return false;
});
</script>
