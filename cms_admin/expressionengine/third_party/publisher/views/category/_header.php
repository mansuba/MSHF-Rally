<div class="formArea" style="margin: 0 -14px;">

<div id="templateGroups">
	<div class="column">
		<div class="formHeading">
		    <div class="newTemplate">
    		    <a href="<?php echo $group_new_url ?>" class="publisher_dialog">New Group</a>
    		</div>
			Category Groups
		</div>
		
		<div class="groupList">
			<h3>Choose Group</h3>

			<select class="publisher-search-select">
                <?php echo $grouped ?>
            </select>
			
			<ul style="height: auto">
			    <?php foreach($category_groups as $group_id => $group): ?>
				    <li <?php if(isset($group_data) AND $group_id == $group_data->group_id) { echo 'class="selected"'; } ?>>
                        <a class="templateGroupName" href="<?php echo $group_view_url .AMP.'group_id='. $group_id ?>">
                            <?php echo $group->group_name ?>
                        </a>
                    </li>
				<?php endforeach; ?>
			</ul>
		</div>					
	</div>
</div>

<div id="templates" style="margin-left: 0; float: left; width: 68%">
	<div class="column">

	<div class="formHeading">Category Group: <?php echo $group_data->group_name ?>
        <div style="margin-left:15px" class="newTemplate">
            <a href="<?php echo $category_new_url ?>">New Category</a>
        </div>
        <div style="margin-left:15px" class="newTemplate">
            <a href="<?php echo $category_delete_group_url ?>">Delete Group</a>
        </div>
        <div style="margin-left:15px" class="newTemplate">
            <a href="<?php echo $group_edit_url ?>">Edit Group</a>
        </div>
    </div>
