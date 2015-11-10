<?php echo form_open($delete_url, array('id' => 'phrase_delete'), $hidden); ?>

<p class="notice">Are you sure you want to delete this phrase?</p>
<p><?php echo $phrase_name ?></p>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang('publisher_delete'), 'class' => 'submit'))?></p>
</form>