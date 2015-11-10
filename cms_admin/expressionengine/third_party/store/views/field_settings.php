<?php

$this->table->clear();
$this->table->set_template($cp_table_template);
$this->table->set_heading(array(
    array('data' => lang('field_options'), 'style' => 'width:40%'),
    array('data' => ''),
));

$this->table->add_row(array(
    lang('store.enable_custom_prices', 'enable_custom_prices').BR.lang('store.enable_custom_prices_subtext'),
    '<p>'.form_radio('store[enable_custom_prices]', 1, $settings['enable_custom_prices'], 'id="enable_custom_prices_y"').NBS.
    lang('yes', 'enable_custom_prices_y').NBS.NBS.NBS.NBS.NBS.
    form_radio('store[enable_custom_prices]', 0, !$settings['enable_custom_prices'], 'id="enable_custom_prices_n"').NBS.
    lang('no', 'enable_custom_prices_n').'</p>'.
    lang('store.enable_custom_prices_warning'),
));

$this->table->add_row(array(
    lang('store.enable_custom_weights', 'enable_custom_weights').BR.lang('store.enable_custom_weights_subtext'),
    '<p>'.form_radio('store[enable_custom_weights]', 1, $settings['enable_custom_weights'], 'id="enable_custom_weights_y"').NBS.
    lang('yes', 'enable_custom_weights_y').NBS.NBS.NBS.NBS.NBS.
    form_radio('store[enable_custom_weights]', 0, !$settings['enable_custom_weights'], 'id="enable_custom_weights_n"').NBS.
    lang('no', 'enable_custom_weights_n').'</p>',
));

echo $this->table->generate();
