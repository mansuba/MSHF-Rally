<?php echo form_open($save_url, array('id' => 'phrase_manage'), $hidden); ?>

<?php echo ($validation_errors ? '<div class="ootb-message">'. $validation_errors .'</div>' : '') ?>

<table class="mainTable padTable" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <th>Preference</th>
        <th>Value</th>
    </tr>
    <tr>
        <td width="40%">
            Language Name
        </td>
        <td>
            <?php echo form_input('long_name', (isset($language->long_name) ? $language->long_name : '')); ?>
        </td>
    </tr>
    <tr>
        <td width="40%">
            Language Code
            <p><small>Single word, no spaces. Underscores allowed. Example: <code>en</code>, <code>es</code>, <code>es_ES</code>, <code>fr_CA</code></small></p>
        </td>
        <td>
            <?php echo form_input('short_name', (isset($language->short_name) ? $language->short_name : '')); ?>
        </td>
    </tr>
    <tr>
        <td>
            Default Language
        </td>
        <td>
            <p><?php echo form_checkbox('is_default', 'y', (isset($language->is_default) AND $language->is_default == 'y' ? TRUE : FALSE)); ?> Yes</p>
        </td>
    </tr>
    <tr>
        <td>
            Enabled
        </td>
        <td>
            <p><?php echo form_checkbox('is_enabled', 'y', (isset($language->is_enabled) AND $language->is_enabled == 'y' ? TRUE : FALSE)); ?> Yes</p>
        </td>
    </tr>
    <tr>
        <td>
            Text Direction
        </td>
        <td>
            <p><?php echo form_dropdown('direction', array('ltr' => 'Left to Right', 'rtl' => 'Right to Left'), (isset($language->direction) ? $language->direction : '')); ?></p>
        </td>
    </tr>
    <tr>
        <td>
            Language Pack
            <p><small>Choose the language pack to be used on the front end for this language. This will be used for core ExpressionEngine error/user messages. <a href="https://github.com/EllisLab">Download language packs</a>.</small></p>
        </td>
        <td>
            <p><?php echo form_dropdown('language_pack', $language_packs, (isset($language->language_pack) ? $language->language_pack : 'english')); ?></p>
        </td>
    </tr>
    <tr>
        <td width="40%">
            Category URL Indicator
            <p><small>This should be the translated value of the Category URL Indicator in the Global Channel Preferences.</small></p>
        </td>
        <td>
            <?php
            $disabled = (isset($language) AND $language->is_default == 'y' ? 'disabled="disabled"' : FALSE);
            $value    = $disabled ? $cat_url_indicator : (isset($language->cat_url_indicator) ? $language->cat_url_indicator : '');

            $field_data = array(
                'name' => 'cat_url_indicator',
                'value' => $value
            );

            if ($disabled)
            {
                $field_data['disabled'] = $disabled;
                echo form_input($field_data);
                echo form_hidden('cat_url_indicator', $cat_url_indicator);
            }
            else
            {
                echo form_input($field_data);
            }
            ?>
        </td>
    </tr>
    <tr>
        <td>
            Sites
            <p><small>Choose which sites this language is enabled on. The language will only appear in the <code>{exp:publisher:langauges}</code> and pair and <code>{exp:publisher:switcher}</code> tags for the sites it was enabled on.</small></p>
        </td>
        <td>
            <?php if (isset($language->is_default) && $language->is_default == 'y') :?>
                <?php foreach ($sites as $id => $site): ?>
                    <p><?php echo $site, form_hidden('sites[]', $id); ?></p>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php echo form_multiselect('sites[]', $sites, (isset($language->sites) ? $language->sites : '')); ?></p>
            <?php endif; ?>
        </td>
    </tr>
</table>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang($button_label), 'class' => 'submit'))?></p>
</form>