<?php echo form_open($save_url, array('id' => 'phrase_manage'), $hidden); ?>

<?php echo ($validation_errors ? '<div class="ootb-message">'. $validation_errors .'</div>' : '') ?>

<script type="text/javascript">
$(function(){
    $('.resizable').autosize();
});
</script>
<table class="mainTable padTable" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <th>Preference</th>
        <th>Value</th>
    </tr>
    <tr>
        <td width="40%">
            Phrase Name
            <p><small>Single word, no spaces. Underscores and dashes allowed.</small></p>

            <?php if ( !$hidden['phrase_id']): ?>
            <p><small>You can also create multiple phrases, along with their default language value,
                at once by entering them in the following format.</small></p>
            <p><small><code>phrase : value<br />another_phrase : longer sring value</code></small></p>
            <?php endif; ?>
        </td>
        <td>
            <?php
            $class = !$hidden['phrase_id'] ? 'small resizable' : 'small';
            echo form_textarea(array('name' => 'phrase_name', 'value' => $phrase_name, 'class' => $class))
            ?>
        </td>
    </tr>
    <tr>
        <td width="40%">
            Phrase Description
            <p><small>Enter a short description of the phrase to describe where it is used on the site or what it is for.</small></p>
        </td>
        <td>
            <?php
            echo form_textarea(array('name' => 'phrase_desc', 'value' => $phrase_desc, 'class' => 'small resizable'))
            ?>
        </td>
    </tr>
    <tr>
        <td>
            Phrase Group
        </td>
        <td>
            <p><?php echo form_dropdown('group_id', $groups, (isset($hidden['group_id']) ? $hidden['group_id'] : FALSE)); ?></p>
        </td>
    </tr>
</table>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang($button_label), 'class' => 'submit'))?></p>
</form>