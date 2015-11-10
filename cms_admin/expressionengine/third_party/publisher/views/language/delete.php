<?php echo form_open($delete_url, array('id' => 'language_delete'), $hidden); ?>

<p class="notice">Are you sure you want to delete this language?</p>
<p><?php echo $long_name ?></p>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang('publisher_delete'), 'class' => 'submit'))?></p>
</form>