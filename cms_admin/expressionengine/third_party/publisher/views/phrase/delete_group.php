<?php echo form_open($delete_url, array('id' => 'phrase_delete_group'), $hidden); ?>

<p class="notice">Are you sure you want to delete this phrase group?</p>
<p><?php echo $group_name ?></p>
<p class="notice">Choose which group to re-assign all the <b><?php echo $group_name ?></b> phrases to. If no group is selected, they will be deleted.</p>
<p><?php echo form_dropdown('new_group', $groups); ?></p>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang('publisher_delete'), 'class' => 'submit'))?></p>
</form>