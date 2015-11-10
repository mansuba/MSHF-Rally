<?= form_open($post_url) ?>
    <fieldset class="store_table_fields">
        <div class="store_datatable_field store_datatable_field_right">
            <a href="<?= e($export_url.'&print=1') ?>" class="submit"><?= lang('store.print') ?></a>
            <a href="<?= e($export_url.'&csv=1') ?>" class="submit"><?= lang('store.export_csv') ?></a>
        </div>
        <?php foreach ($report->options()->all() as $key => $value): ?>
            <div class="store_datatable_field">
                <?= lang("store.reports.$key", $key) ?>
                <?= $report->options()->input($key) ?>
            </div>
        <?php endforeach ?>
        <div class="store_datatable_field">
            <input type="submit" class="submit" value="Update" />
        </div>
    </fieldset>
<?= form_close() ?>

<div class="container-fluid container-paddingtb">

<div class="store_report_html">
<?php $report->run(); ?>
</div>

<?php if (ee()->store->reports->timezone() !== ee()->session->userdata('timezone')): ?>
    <p class="store_report_note"><?= sprintf(lang('store.reports.timezone_note'), '<strong>'.str_replace('_', ' ', ee()->store->reports->timezone()).'</strong>') ?></p>
<?php endif ?>

</div>