<?php

/**
 * Helper function to create product modifier row
 */
function store_product_modifier($mod_key, $modifier)
{
    // don't display deleted rows
    if (!isset($modifier['mod_type'])) {
        return;
    }

    // always ensure there is at least one option (even on text inputs it doesn't matter)
    if (empty($modifier['options'])) {
        $modifier['options'] = array(array('opt_name' => null, 'opt_price_mod' => null, 'opt_order' => null));
    }

    $mod_prefix = "store_product_field[modifiers][{$mod_key}]";
    $hide_options = in_array($modifier['mod_type'], array('var', 'var_single_sku')) ? '' : 'style="display: none"';
    ?>
    <tbody class="store_product_modifier">
        <tr>
            <th>
                <input type="hidden" name="<?= $mod_prefix ?>[mod_order]" value="<?= $modifier['mod_order'] ?>" class="store_modifiers_sort" />
                <div class="store_sortable_handle store_modifier_handle"></div>
            </th>
            <td><?= form_dropdown($mod_prefix.'[mod_type]', array('var' => lang('store.variation'), 'var_single_sku' => lang('store.variation_single_sku'), 'text' => lang('store.text_input')), $modifier['mod_type'], 'class="store_select_mod_type"') ?></td>
            <td class="store_ft_text"><?= form_input($mod_prefix.'[mod_name]', $modifier['mod_name'], 'class="store_input_mod_name" autocomplete="off" required').form_error($mod_prefix.'[mod_name]') ?></td>
            <td class="store_ft_text"><?= form_input($mod_prefix.'[mod_instructions]', $modifier['mod_instructions'], 'autocomplete="off"') ?></td>
            <td>
                <div style="margin:0;padding:0;display:inline;"><!-- ie7 spacer --></div>
                <div class="store_product_options_wrap" <?= $hide_options ?>>
                    <table class="store_ft store_product_options_table">
                        <thead>
                            <tr>
                                <th style="width:2%">&nbsp;</th>
                                <th style="width:48%"><?= lang('store.option') ?></th>
                                <th style="width:48%"><?= lang('store.price_modifier') ?></th>
                                <th style="width:2%">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php /* print any new or existing variation options */ ?>
                            <?php foreach ($modifier['options'] as $opt_key => $option): ?>
                                <?php if (array_key_exists('opt_name', $option)): ?>
                                    <?php $opt_prefix = $mod_prefix."[options][{$opt_key}]"; ?>
                                    <tr class="store_product_option_row">
                                        <th>
                                            <input type="hidden" name="<?= $opt_prefix ?>[opt_order]" value="<?= $option['opt_order'] ?>" class="store_modifiers_sort" />
                                            <div class="store_sortable_handle store_option_handle"></div>
                                        </th>
                                        <td class="store_ft_text"><?= form_input($opt_prefix.'[opt_name]', $option['opt_name'], 'class="store_input_opt_name" autocomplete="off"') ?></td>
                                        <td class="store_ft_text"><?= form_input($opt_prefix.'[opt_price_mod]', store_currency_cp($option['opt_price_mod'], true), 'placeholder="'.lang('store.none').'" autocomplete="off"') ?></td>
                                        <td><a href="#" class="store_product_option_remove"><?= lang('store.remove') ?></a></td>
                                    </tr>
                                <?php endif ?>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                    <div class="store_ft_add"><a href="#" class="store_product_option_add" data-mod-key="<?= $mod_key ?>"><i class="store_icon_add"></i><?= lang('store.add_new_option') ?></a></div>
                </div>
            </td>
            <td><a href="#" class="store_product_modifier_remove"><?= lang('store.remove') ?></a></td>
        </tr>
    </tbody>
    <?php
}
?>

<?= form_hidden($field_name, 'store'); ?>
<?php $form = store_form($product, 'store_product_field'); ?>
<div id="store_product_field">

<div class="store_field_pane">

    <table class="store_ft">
        <thead>
            <tr>
                <th>
                    <?php if ($field_required): ?><em class="required">*</em><?php endif ?>
                    <?= lang('store.price') ?>
                </th>
                <th><?= lang('store.length_with_units') ?></th>
                <th><?= lang('store.width_with_units') ?></th>
                <th><?= lang('store.height_with_units') ?></th>
                <th><?= lang('store.weight_with_units') ?></th>
                <th><?= lang('store.handling') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="store_ft_text">
                    <?= $form->currency('price', array('autocomplete' => 'off', 'required' => $field_required)) ?>
                    <?= $form->error('price') ?>
                </td>
                <td class="store_ft_text"><?= $form->decimal('length', array('autocomplete' => 'off')) ?></td>
                <td class="store_ft_text"><?= $form->decimal('width', array('autocomplete' => 'off')) ?></td>
                <td class="store_ft_text"><?= $form->decimal('height', array('autocomplete' => 'off')) ?></td>
                <td class="store_ft_text"><?= $form->decimal('weight', array('autocomplete' => 'off')) ?></td>
                <td class="store_ft_text"><?= $form->currency('handling', array('autocomplete' => 'off', 'placeholder' => lang('store.none'))) ?></td>
                <td><label for="store_product_field_free_shipping"><?= $form->checkbox('free_shipping') ?> <?= lang('store.free_shipping') ?></label></td>
            </tr>
        </tbody>
    </table>

</div>

<label class="store_field_title"><?= lang('store.product_modifiers') ?></label>
<div class="store_field_pane">

<div style="margin:0;padding:0;display:inline;">
    <?php
        // output all existing modifier and option ids so we can match them up with submitted rows
        foreach ($modifiers as $mod_key => $modifier) {
            if (!empty($modifier['product_mod_id'])) {
                echo form_hidden("store_product_field[modifiers][{$mod_key}][product_mod_id]", $modifier['product_mod_id']);
                if (!empty($modifier['options'])) {
                    foreach ($modifier['options'] as $opt_key => $option) {
                        if (!empty($option['product_opt_id'])) {
                            echo form_hidden("store_product_field[modifiers][{$mod_key}][options][{$opt_key}][product_opt_id]", $option['product_opt_id']);
                        }
                    }
                }
            }
        }
    ?>
</div>

<table id="store_product_modifiers_table" cellspacing="0" cellpadding="0" border="0" class="store_ft">
    <thead>
        <tr>
            <th style="width:2%">&nbsp;</th>
            <th style="width:18%"><?= lang('store.mod_type') ?></th>
            <th style="width:15%"><em class="required">*</em> <?= lang('name') ?></th>
            <th style="width:25%"><?= lang('store.mod_instructions') ?></th>
            <th><?= lang('options') ?></th>
            <th style="width:2%">&nbsp;</th>
        </tr>
    </thead>
    <tbody id="store_product_modifier_empty" <?php if (!empty($modifiers)): ?>style="display: none"<?php endif ?> >
        <tr><td colspan="6"><?= lang('store.no_product_modifiers_defined') ?></td></tr>
    </tbody>
    <?php
        // print any new or existing product modifiers
        foreach ($modifiers as $mod_key => $modifier) {
            store_product_modifier($mod_key, $modifier);
        }
    ?>
    <script type="text/html" id="store_product_modifier_template">
        <?php store_product_modifier('{{mod_key}}', array('mod_type' => 'var', 'mod_name' => null, 'mod_order' => null, 'mod_instructions' => null)) ?>
    </script>
</table>
<div class="store_ft_add"><a href="#" id="store_product_modifiers_add"><i class="store_icon_add"></i><?= lang('store.add_product_modifier') ?></a></div>
</div>

<label class="store_field_title"><?= lang('store.stock') ?></label>
<div class="store_field_pane">
    <div id="store_product_stock">
        <table class="store_ft">
            <thead>
                <tr>
                    <th class="store_stock_opt_header"></th>
                    <th><?= lang('store.sku') ?></th>
                    <th>
                        <?= form_checkbox(array('value' => 'true', 'checked' => false, 'class' => 'checkall_stock_publish')) ?>
                        <?= lang('store.limit_stock') ?>
                    </th>
                    <th><?= lang('store.min_order_qty') ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <script type="text/html" id="store_stock_row_template">
            <?php $prefix = 'store_product_field[stock][{{stock_key}}]'; ?>
            <tr>
                <td class="store_ft_text">
                    <?= form_hidden($prefix.'[id]', '{{stock.id}}') ?>
                    <?= form_input($prefix.'[sku]', '{{stock.sku}}', 'autocomplete="off"') ?>
                </td>
                <td class="store_ft_text">
                    <div class="store_track_stock"><?= store_form_checkbox($prefix.'[track_stock]', false) ?></div>
                    <div class="store_stock_level"><?= form_input($prefix.'[stock_level]', '{{stock.stock_level}}', 'placeholder="'.lang('store.none').'" autocomplete="off"') ?></div>
                </td>
                <td class="store_ft_text"><?= form_input($prefix.'[min_order_qty]', '{{stock.min_order_qty}}', 'placeholder="'.lang('store.none').'" autocomplete="off"') ?></td>
            </tr>
        </script>
        <script type="text/html" id="store_stock_row_th_template">
            <th>
                <?= form_hidden('store_product_field[stock][{{stock_key}}][stock_options][{{stock_opt_key}}][product_mod_id]', '{{stock_opt.product_mod_id}}') ?>
                <?= form_hidden('store_product_field[stock][{{stock_key}}][stock_options][{{stock_opt_key}}][product_opt_id]', '{{stock_opt.product_opt_id}}') ?>
                {{stock_opt.opt_name}}
            </th>
        </script>
    </div>
</div>
