<div class="container-fluid container-paddingtb">
<?php
    $this->table->clear();
    $this->table->set_template($store_table_template);
    $this->table->set_heading(
        array('data' => lang('store.report')),
        array('data' => lang('store.description')));

    foreach ($reports as $report => $class) {
        $name = '<a href="'.store_cp_url('reports', 'show', array('report' => $report)).
            '">'.lang("store.reports.$report").'</a>';

        $this->table->add_row($name, lang('store.reports.'.$report.'_desc'));
    }

    echo $this->table->generate();
?>
</div>