<?php echo $this->load->view('category/_header');

$this->table->set_template($table_template);

if (! isset($categories) OR empty($categories))
{
    $this->table->add_row('No Categories found.');
}
else
{
    $this->table->set_heading(
        array('data' => 'Category', 'style' => 'width: 40%'),
        array('data' => 'Translation Status', 'style' => 'width: 30%'),
        array('data' => 'Action', 'style' => 'width: 30%')
    );
    
    foreach($categories as $category)
    {
        $this->table->add_row(
            '<a href="'. $category_edit_url .AMP.'group_id='. $category->group_id .AMP. 'cat_id='. $category->cat_id .AMP. 'group_id='. $category->group_id.'" class="ajax-edit" data-type="category" data-id="'. $category->cat_id .'" data-group-id="'. $category->group_id .'">'. $category->cat_name .'</a>',
            '<span class="translation-status-'. $category->cat_id .'">'. $category->translation_status .'</span>',
            '<a href="'. $category_edit_url .AMP.'group_id='. $category->group_id .AMP.'cat_id='. $category->cat_id .'">Edit</a> |
             <a href="'. $category_delete_url .AMP.'group_id='. $category->group_id .AMP.'cat_id='. $category->cat_id .'">Delete</a>'
        );
    }
}

echo $this->table->generate();
$this->table->clear();

echo $this->load->view('category/_footer'); ?>	