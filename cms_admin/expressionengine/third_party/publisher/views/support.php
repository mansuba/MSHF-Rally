<?php echo form_open($save_url, array(), $hidden); ?>

<div class="ootb-message">
	<p>If you are seeking support for Publisher create a ticket at <a href="http://boldminded.com/support">http://boldminded.com/support</a>.</p>
	<p>After creating a ticket you can submit this form and information about your Publisher and ExpressionEngine configuration will be sent to <a href="mailto:support@boldminded.com">support@boldminded.com</a> to help troubleshoot your issue.</p>
</div>

<?php echo ($validation_errors ? '<div class="ootb-message">'. $validation_errors .'</div>' : '') ?>

<table class="mainTable padTable" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <th>Field</th>
        <th>Value</th>
    </tr>
    <tr>
        <td>Email Address</td>
        <td><?php echo form_input(array(
        	'name' => 'email_address', 
        	'value' => ($email_address ? $email_address : ''),
        	'required' => '',
        	'type' => 'email'
        	)); ?>
       	</td>
    </tr>
    <tr>
        <td>Ticket Number</td>
        <td><?php echo form_input(array(
        	'name' => 'ticket_number', 
        	'value' => ($ticket_number ? $ticket_number : ''), 
        	'required' => '',
        	'type' => 'number'
        	)); ?>
        </td>
    </tr>
</table>

<?php 

$settings_formatted = '<h1>Settings</h1>'.$settings.
					  '<h1>Languages</h1>'.$languages.
					  '<h1>Templates</h1>'.$templates.
					  '<h1>Previews</h1>'.$previews.
					  '<h1>Site Pages</h1>'.$site_pages.
					  '<h1>EE Config</h1>'.$config;

?>

<?php echo form_textarea('settings', $settings_formatted, 'style="display: none"'); ?>

<p><?php echo form_submit(array('name' => 'submit', 'value' => lang($button_label), 'class' => 'submit'))?></p>
</form>