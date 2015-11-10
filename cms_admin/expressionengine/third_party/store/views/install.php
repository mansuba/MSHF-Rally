<p><?= sprintf(lang('store.not_yet_installed'), "<b>$site_name</b>") ?></p>

<?php if ($is_super_admin): ?>

    <?= form_open($post_url) ?>
        <p>
            <?= store_form_checkbox('install_example_templates', true) ?>
            <?= form_label(lang('store.install_example_templates'), 'install_example_templates') ?>
        </p>
        <p><?= form_submit(array('name' => 'submit', 'value' => lang('store.install_now'), 'class' => 'submit')) ?></p>
    <?= form_close() ?>

<?php else: ?>

    <p><?= lang('store.install_store_super_admin') ?></p>

<?php endif ?>
