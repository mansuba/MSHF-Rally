<form action="<?=$base_url?>&amp;method=delete_group" method="post">
	<div>
		<input type="hidden" name="group_id" value="<?=$group_id?>" />
		<input type="hidden" name="<?=$csrf_token_name?>" value="<?=$csrf_token_value?>" />
	</div>
	<div id="low-variables-delete">
		<p><?=$confirm_message?></p>
		<ul>
			<li><?=htmlentities($group_label)?></li>
		</ul>
		<p class="important"><?=lang('action_can_not_be_undone')?></p>
		<button type="submit" class="submit"><?=lang('delete')?></button>
		<a href="<?=$base_url?>" class="cancel"><?=lang('cancel')?></a>
	</div>
</form>