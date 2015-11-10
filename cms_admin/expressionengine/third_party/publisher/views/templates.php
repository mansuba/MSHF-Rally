<?php if ( !$this->publisher_setting->url_translations()) : ?>

    <div class="ootb-message">
        It looks like you need to enable <b>URL Translations</b> on the <a href="<?php echo $this->publisher_helper_cp->mod_link('settings'); ?>">settings page</a> first.
    </div>

<?php else: ?>

    <div class="ootb-message">
        Enter the translated versions of each template group and template name. You will then be able to link your pages based on the translated versions below.
    </div>

<?php
echo form_open($save_url, array('id' => 'templates'), $hidden);

$this->table->set_heading(
    array('data' => 'Template', 'style' => 'width: 15%'),
    array('data' => 'Translation', 'style' => 'width: 85%')
);

$prev_group = '';

foreach($templates as $group_name => $group_templates)
{
    if ($prev_group != $group_name)
    {
        $group_id = $this->publisher_template->get_group_id($group_name);

        $this->table->add_row(
            array('data' => $group_name),
            array('data' => $this->publisher_template->create_template_fields($group_name, $group_id, 'group'))
        );
    }

    foreach ($group_templates as $template_id => $template)
    {
        $this->table->add_row(
            array('data' => '<img src="'. $this->publisher_helper->get_theme_url(FALSE).'cp_global_images/cat_marker.gif" /> '. $template),
            array('data' => $this->publisher_template->create_template_fields($template, $template_id, 'template', $group_id))
        );
    }

    $prev_group = $group_name;
}

echo $this->table->generate();
$this->table->clear();
?>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang('publisher_save'), 'class' => 'submit'))?></p>

<?php endif; ?>