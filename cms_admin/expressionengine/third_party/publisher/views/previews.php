<?php if ( !$this->publisher_setting->draft_previews()) : ?>

<p>It looks like you need to enable <b>Draft Previews</b> on the <a href="<?php echo $this->publisher_helper_cp->mod_link('settings'); ?>">settings page</a> first.</p>

<?php else: ?>

<script>
$(function(){
    Publisher.preview_templates();
});
</script>

<p>If you are <em>not</em> using the Structure module to manage your pages you will need to select a preview template to use when an entry is saved in each channel.</p>

<?php
echo form_open($save_url, array('id' => 'templates'), $hidden);

$override_tip = '<br/><small style="font-weight: normal;">If entered, the Preview Template setting will be ignored. {url_title} and {entry_id} can be used. E.g. <code>/path/to/{url_title}</small>';
$template_tip = '<br/><small style="font-weight: normal;">Select the template to use for entry previews followed by its url_title or entry_id.</small>';

$this->table->set_heading(
    array('data' => 'Channel', 'style' => 'width: 12%; vertical-align: top;'),
    array('data' => 'Preview Template'.$template_tip, 'style' => 'width: 50%; vertical-align: top;'),
    array('data' => 'Preview URI'.$override_tip, 'style' => 'width: 38%; vertical-align: top;')
);

$prev_group = '';

foreach($channels as $channel_id => $channel)
{
    $template_value = isset($data[$channel_id]['template_id']) ? $data[$channel_id]['template_id'] : '';
    $append_value = isset($data[$channel_id]['append']) ? $data[$channel_id]['append'] : '';
    $custom_value = isset($data[$channel_id]['custom']) ? $data[$channel_id]['custom'] : '';
    $override_value = isset($data[$channel_id]['override']) ? $data[$channel_id]['override'] : '';

    $options = form_dropdown('previews['. $channel_id .']', $templates, $template_value);
    $options .= ' + '. form_dropdown('append['. $channel_id .']',
                            array('' => '- Select -', 'url_title' => 'url_title', 'entry_id' => 'entry_id', 'custom' => 'custom'),
                            $append_value,
                            'class="preview_template_append"'
                        );

    $options .= form_input('custom['. $channel_id .']', $custom_value, 'class="preview_template_custom"');
    $override = form_input('override['. $channel_id .']', $override_value);

    $this->table->add_row(
        array('data' => $channel['channel_title']),
        array('data' => $options),
        array('data' => $override)
    );
}

echo $this->table->generate();
$this->table->clear();
?>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang('publisher_save'), 'class' => 'submit'))?></p>

<?php endif; ?>