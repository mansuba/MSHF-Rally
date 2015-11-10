<input type="hidden" class="publisher-type" value="phrase" />
<input type="hidden" class="publisher-type-id" value="<?php echo $phrase_id ?>" />

<script type="text/javascript">
$(function(){
	$('.resizable').autosize();
});
</script>

<table class="publisher">
	<?php foreach ($data as $lang_id => $phrase): ?>
	<tr>
		<td width="25%">
			<?php echo $this->publisher_helper->get_flag($languages[$lang_id]['short_name'], $languages[$lang_id]['long_name']); ?>
			<?php echo $languages[$lang_id]['long_name']; ?>
		</td>
		<td width="75%">
			<textarea dir="<?php echo $phrase->text_direction ?>" name="translation[<?php echo $phrase->phrase_id ?>][<?php echo $lang_id ?>]" class="small resizable"><?php echo $phrase->phrase_value; ?></textarea>
		</td>
	</tr>
	<?php endforeach; ?>
</table>