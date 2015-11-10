<?php echo $this->load->view('phrase/_header');

$this->table->set_template($table_template);

if (! isset($phrases) OR empty($phrases))
{
    $this->table->add_row('No Phrases found.');
}
else
{
    $this->table->set_heading(
        array('data' => 'Phrase', 'style' => 'width: 40%'),
        array('data' => 'Translation Status', 'style' => 'width: 30%'),
        array('data' => 'Action', 'style' => 'width: 30%')
    );
    
    foreach($phrases as $phrase)
    {
        $this->table->add_row(
            '<a href="'. $phrase_manage_url .AMP.'group_id='. $phrase->group_id .AMP. 'phrase_id='. $phrase->phrase_id .'" class="ajax-edit" data-type="phrase" data-id="'. $phrase->phrase_id .'" name="phrase-'. $phrase->phrase_id .'">'.LD. $phrase_prefix . $phrase->phrase_name .RD.'</a>',
            '<span class="translation-status-'. $phrase->phrase_id .'">'. $phrase->translation_status .'</span>',
            '<a href="'. $phrase_manage_url .AMP.'group_id='. $phrase->group_id .AMP.'phrase_id='. $phrase->phrase_id .'">Edit</a> |
             <a href="'. $phrase_delete_url .AMP.'group_id='. $phrase->group_id .AMP.'phrase_id='. $phrase->phrase_id .'">Delete</a>'
        );
    }
}

echo $this->table->generate();
$this->table->clear();

echo $this->load->view('phrase/_footer'); ?>	