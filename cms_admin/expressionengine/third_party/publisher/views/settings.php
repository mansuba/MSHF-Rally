<?php echo form_open($save_url, array('id' => 'settings'), $hidden); ?>
<form method="post" action="<?php echo $save_url ?>">
	<?php echo $settings ?>

	<p>If Publisher was installed <i>after</i> Playa, Assets, or Matrix you will need to run some updates. This update only needs to be run once.
	<a href="<?php echo $install_pt_url ?>">Update P&amp;T add-ons</a> </p>

	<button class="submit">Save Settings</button>
</form>