<?= form_open($post_url, array('id' => 'store_datatable_search')) ?>
    <fieldset class="store_table_fields">
        <div class="store_datatable_field_long">
            <?= lang('search', 'keywords') ?>
            <?= form_input('keywords', $search['keywords']) ?>
        </div>
        <div class="store_datatable_field">
            <?= lang('category', 'category_id') ?>
            <?= form_dropdown('category_id', $category_options, $search['category_id']) ?>
        </div>
        <div class="store_datatable_field">
            <?= lang('results_per_page', 'per_page') ?>
            <?= form_dropdown('per_page', $per_page_select_options, $pagination['per_page']) ?>
        </div>
    </fieldset>
<?= form_close(); ?>

<div class="container-fluid container-paddingtb">
<?= form_open($post_url, array('id' => 'store_datatable')) ?>
    <?= $table_html ?>
    <?= $pagination_html ?>
<?= form_close() ?>
</div>