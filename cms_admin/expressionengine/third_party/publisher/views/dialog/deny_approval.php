<div id="publisher-dialog-deny-approval-<?php echo $type .'-'. $type_id ?>" style="display: none;">
	<?php echo form_open($deny_approval_url, array(), array(
		'type' => $type, 
		'type_id' => $type_id,
		'email_to' => $approval->data->member_data->email,
		'title' => $approval->data->title
	)); ?>
	<p><?php echo $approval->data->deny_template ?></p>
	<?php echo form_textarea('notes'); ?>
	</form>
</div>