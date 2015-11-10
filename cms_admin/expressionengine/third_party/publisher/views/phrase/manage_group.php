<?php echo form_open($save_url, array('id' => 'phrase_manage_group'), $hidden); ?>

<?php echo $validation_errors ?>

<table class="mainTable solo" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td style="width: 40%;">
            Phrase Group Label
        </td>
        <td><?php echo form_input(array('name' => 'group_label', 'value' => $group_label))?></td>
    </tr>
    <tr>
        <td>
            Phrase Group Name<br /><small>(Single word, no spaces. Underscores and dashes allowed.)</small>
        </td>
        <td>
            <?php if($group_name == 'default'): ?>
                <?php echo  $group_name; ?>
                <?php echo form_hidden('group_name', $group_name)?>
            <?php else: ?>
                <?php echo form_input(array('name' => 'group_name', 'value' => $group_name))?>
            <?php endif; ?>
        </td>
    </tr>
</table>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang('publisher_update'), 'class' => 'submit'))?></p>
</form>