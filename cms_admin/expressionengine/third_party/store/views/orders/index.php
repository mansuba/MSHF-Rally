<?= form_open($post_url, array('id' => 'store_datatable_search')) ?>
    <fieldset class="store_table_fields">
        <div class="store_datatable_field_long">
            <?= lang('store.search', 'keywords') ?>
            <?= form_input('keywords', $search['keywords']) ?>
        </div>
        <div class="store_datatable_field">
            <?= lang('store.orders.order_status', 'order_status') ?>
            <?= form_dropdown('order_status', $order_status_select_options, $search['order_status']) ?>
        </div>
        <div class="store_datatable_field">
            <?= lang('store.orders.paid_status', 'order_paid_status') ?>
            <?= form_dropdown('order_paid_status', $order_paid_select_options, $search['order_paid_status']) ?>
        </div>
        <div class="store_datatable_field">
            <?= lang('results_per_page', 'per_page') ?>
            <?= form_dropdown('per_page', $per_page_select_options, $pagination['per_page']) ?>
        </div>
    </fieldset>
<?= form_close() ?>

<div class="container-fluid container-paddingtb">

<?= form_open($post_url, array('id' => 'store_datatable')) ?>
    <?= $table_html ?>
    <div class="tableSubmit">
        <?= lang('store.with_selected') ?>
        <?= form_dropdown('with_selected', $with_selected_options) ?>
        <?= form_submit(array('name' => 'update', 'value' => lang('store.submit'), 'class' => 'submit')) ?>
    </div>
    <?= $pagination_html ?>
<?= form_close() ?>

</div>