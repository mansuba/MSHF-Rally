<div id="store_report_list">

<div class="store_report_box">
<?= form_open($post_url.AMP.'report=orders') ?>

    <h3><?= lang('store.orders_report') ?></h3>
    <?= lang('store.orders_report_list_all') ?>
    <?= form_dropdown('orders_report_date', $date_options, '0', 'class="store_date_range_select"') ?>
    <span style="display: none;" class="custom_date_range">
        <?= lang('store.starting_from') ?>
        <?= form_input(array('class' => 'store_date', 'name' => 'start_date', 'value' => $start_date, 'style' => 'width: 125px')); ?>
        <?= lang('store.through'); ?>
        <?= form_input(array('class' => 'store_date', 'name' => 'end_date', 'value' => $end_date, 'style' => 'width: 125px')); ?>
    </span>
    <?= lang('store.orders_report_with_status') ?>
    <?= form_dropdown('orders_report_status', $status_options) ?>

    <div class="store_report_box_submit">
        <?= form_submit(array('name' => 'submit', 'value' => lang('store.view_report'), 'class' => 'submit')) ?>
        <?= form_submit(array('name' => 'csv', 'value' => lang('store.export_csv'), 'class' => 'submit')) ?>
        <?= form_submit(array('name' => 'pdf', 'value' => lang('store.export_pdf'), 'class' => 'submit')) ?>
    </div>

<?= form_close() ?>
</div>

<div class="store_report_box">
<?= form_open($post_url.AMP.'report=sales_by_date') ?>

    <h3><?= lang('store.total_sales') ?></h3>

    <?=lang('store.total_sales_report_desc')?>
    <?= form_dropdown('sales_report_options', $date_options, '0', 'class="store_date_range_select"') ?>
    <span style="display: none;" class="custom_date_range">
        <?= lang('store.starting_from') ?>
        <?= form_input(array('class' => 'store_date', 'name' => 'sales_start_date', 'value' => $start_date, 'style' => 'width: 125px')); ?>
        <?= lang('store.through'); ?>
        <?= form_input(array('class' => 'store_date', 'name' => 'sales_end_date', 'value' => $end_date, 'style' => 'width: 125px')); ?>
    </span>

    <div class="store_report_box_submit">
        <?= form_submit(array('name' => 'submit', 'value' => lang('store.view_report'), 'class' => 'submit')) ?>
        <?= form_submit(array('name' => 'csv', 'value' => lang('store.export_csv'), 'class' => 'submit')) ?>
        <?= form_submit(array('name' => 'pdf', 'value' => lang('store.export_pdf'), 'class' => 'submit')) ?>
    </div>

<?= form_close() ?>
</div>

<div class="store_report_box">
    <?= form_open($post_url.AMP.'report=stock_products'); ?>

    <h3><?= lang('store.sales_report2') ?></h3>

    <?=lang('store.stock_products_report_desc')?>
    <?= form_dropdown('stock_report_options', $date_options, '0', 'class="store_date_range_select"') ?>
    <span style="display: none;" class="custom_date_range">
        <?= lang('store.starting_from') ?>
        <?= form_input(array('class' => 'store_date', 'name' => 'stock_start_date', 'value' => $start_date, 'style' => 'width: 125px')); ?>
        <?= lang('store.through'); ?>
        <?= form_input(array('class' => 'store_date', 'name' => 'stock_end_date', 'value' => $end_date, 'style' => 'width: 125px')); ?>
    </span>
    <?= lang('store.ordered_by').' '?>
    <?= form_dropdown('stock_orderby_options', $stock_products_options, 'sku') ?>

    <div class="store_report_box_submit">
        <?= form_submit(array('name' => 'submit', 'value' => lang('store.view_report'), 'class' => 'submit')) ?>
        <?= form_submit(array('name' => 'csv', 'value' => lang('store.export_csv'), 'class' => 'submit')) ?>
        <?= form_submit(array('name' => 'pdf', 'value' => lang('store.export_pdf'), 'class' => 'submit')) ?>
    </div>

    <?= form_close() ?>
</div>

<div class="store_report_box">
    <?= form_open($post_url.AMP.'report=stock_value'); ?>

    <h3><?= lang('store.inventory') ?></h3>

    <?=lang('store.stock_inventory_report_desc').' '.lang('store.sorted_by')?>
    <?= form_dropdown('stock_inventory_options', $stock_inventory_options) ?>

    <div class="store_report_box_submit">
        <?= form_submit(array('name' => 'submit', 'value' => lang('store.view_report'), 'class' => 'submit')) ?>
        <?= form_submit(array('name' => 'csv', 'value' => lang('store.export_csv'), 'class' => 'submit')) ?>
        <?= form_submit(array('name' => 'pdf', 'value' => lang('store.export_pdf'), 'class' => 'submit')) ?>
    </div>

    <?= form_close() ?>
</div>

</div>
