<input type="hidden" class="publisher-type" value="category" />
<input type="hidden" class="publisher-type-id" value="<?php echo $cat_id ?>" />
<input type="hidden" class="publisher-group-id" value="<?php echo $group_id ?>" />

<script type="text/javascript">
$(function(){
	$('.resizable').autosize();
});
</script>

<table class="publisher">
	<?php foreach ($data as $lang_id => $category): ?>
	<tr>
		<td width="25%">
			<?php echo $this->publisher_helper->get_flag($languages[$lang_id]['short_name'], $languages[$lang_id]['long_name']); ?>
			<?php echo $languages[$lang_id]['long_name']; ?>
		</td>
		<td width="75%">
			<?php foreach ($custom_fields as $field_name => $field_data): ?>
				<?php if ($field_name == 'cat_image'): ?>
					<?php
					$field_id = 'translation_'. $category->cat_id .'_'. $lang_id .'_'. $field_name;
					list($image_url, $image_name) = $this->publisher_helper->parse_file_path($category->$field_name, 'url', TRUE, TRUE);
					?>
					<script type="text/javascript">
						$(function(){ Publisher.bind_filemanager('<?php echo $field_id ?>') });
					</script>
					<div class="publisher-field file_field" data-content-type="img" data-directory="all">
						<label><?php echo (isset($category->$field_name) ? $field_data->field_label : $field_name) ?></label>
						<input name="translation[<?php echo $category->cat_id ?>][<?php echo $lang_id ?>][<?php echo $field_name ?>]" value="<?php echo $category->$field_name; ?>" id="<?php echo $field_id ?>" type="hidden" />

						<div class="file_set <?php if ( !$image_url AND !$image_name): ?>js_hide<?php endif; ?>">
							<p class='filename'>
								<?php if ($image_url AND $image_name): ?>
									<img src="<?php echo $image_url; ?>" /><br />
									<?php echo $image_name; ?>
								<?php endif; ?>
							</p>
							<p class='sub_filename'><a href="#" class="remove_file"><?php echo lang('publisher_remove_file') ?></a></p>
							<p></p>
						</div>

						<div class="no_file  <?php if ($image_url AND $image_name): ?>js_hide<?php endif; ?>">
							<p class='sub_filename'><a href="#" id="<?php echo $field_id ?>_trigger" class="choose_file"><?php echo lang('publisher_upload') ?></a></p>
						</div>
					</div>
				<?php else: ?>
					<div class="publisher-field <?php echo $field_name ?>">
						<label><?php echo (isset($category->$field_name) ? $field_data->field_label : $field_name) ?></label>
						<textarea dir="<?php echo $category->text_direction ?>" name="translation[<?php echo $category->cat_id ?>][<?php echo $lang_id ?>][<?php echo $field_name ?>]" class="small resizable"><?php echo $category->$field_name; ?></textarea>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</td>
	</tr>
	<?php endforeach; ?>
</table>