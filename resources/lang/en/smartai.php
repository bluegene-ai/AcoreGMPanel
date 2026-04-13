<?php
return array (
  'page_title' => 'SmartAI Wizard',
  'intro' => 'Build events, actions, and targets following the AzerothCore Smart Scripts spec, then generate executable SQL.',
  'sidebar' => 
  array (
    'nav_title' => 'Step guide',
    'steps' => 
    array (
      'base' => 'Basics',
      'event' => 'Select Event',
      'action' => 'Configure Action',
      'target' => 'Target & Preview',
    ),
    'quick_view' => 'At a glance',
    'view_wiki' => 'View official Wiki',
    'updated_at' => 'Catalog updated: :date',
  ),
  'base' => 
  array (
    'title' => 'Basics',
    'description' => 'Define the script scope and shared fields (entry, chance, phase, etc.).',
  ),
  'segment' => 
  array (
    'add' => 'Add segment',
    'hint' => 'Each segment owns its event, action, and target, executed sequentially.',
  ),
  'event' => 
  array (
    'title' => 'Select Event',
    'description' => 'Events decide when the script runs. Pick a type and fill parameters based on the Wiki.',
  ),
  'action' => 
  array (
    'title' => 'Configure Action',
    'description' => 'Actions run when the event fires; combine spells, dialogues, summons, and more.',
  ),
  'target' => 
  array (
    'title' => 'Target & Preview',
    'description' => 'Choose targets and generate SQL for execution or download.',
  ),
  'preview' => 
  array (
    'title' => 'SQL Preview',
    'generate' => 'Generate SQL',
    'copy' => 'Copy',
    'placeholder' => '-- Finish previous steps and click Generate first',
  ),
  'footer' => 
  array (
    'prev' => 'Previous',
    'next' => 'Next',
    'step_indicator' => 'Step :current of :total',
  ),
  'catalog' => 
  array (
    'metadata' => 
    array (
      'notes' => 
      array (
        0 => 'Field and parameter semantics follow the AzerothCore Wiki.',
        'exclude_username' => 'Username not contains',
        'exclude_username_placeholder' => 'e.g. test',
        1 => 'Generated SQL can be applied to the smart_scripts table.',
      ),
    ),
    'source_types' => 
    array (
      0 => 
      array (
        'label' => 'Creature',
      ),
      1 => 
      array (
        'label' => 'GameObject',
      ),
      2 => 
      array (
        'label' => 'AreaTrigger',
      ),
      3 => 
      array (
        'label' => 'Event',
      ),
      9 => 
      array (
        'label' => 'Timed ActionList',
      ),
    ),
    'base' => 
    array (
      'entryorguid' => 
      array (
        'label' => 'Entry / GUID',
        'hint' => 'Provide the entry or guid based on the selected source type.',
      ),
      'source_type' => 
      array (
        'label' => 'Source Type',
        'hint' => 'Script source (Creature/GameObject/Timed ActionList/etc.).',
      ),
      'id' => 
      array (
        'label' => 'ID',
        'hint' => 'Script index within the same entry/source_type.',
      ),
      'link' => 
      array (
        'label' => 'Link',
        'hint' => 'Link to a previous script ID (0 = no link).',
      ),
      'event_phase_mask' => 
      array (
        'label' => 'Phase Mask',
        'hint' => 'Event phase mask (bitmask).',
      ),
      'event_chance' => 
      array (
        'label' => 'Chance',
        'hint' => 'Trigger chance (0-100).',
      ),
      'event_flags' => 
      array (
        'label' => 'Event Flags',
        'hint' => 'Event flags (bitmask).',
      ),
      'comment' => 
      array (
        'label' => 'Comment',
        'hint' => 'Optional comment.',
      ),
      'include_delete' => 
      array (
        'label' => 'Include DELETE',
        'hint' => 'Include a statement to delete previous scripts when generating SQL.',
      ),
    ),
  ),
  'builder' => 
  array (
    'messages' => 
    array (
      'validation_failed' => 'Parameter validation failed',
    ),
    'errors' => 
    array (
      'base' => 
      array (
        'entryorguid' => 'Please enter a valid entry or GUID.',
        'source_type' => 'Unsupported source_type. Please choose one of the dropdown options.',
        'event_chance' => 'Chance must be between 0 and 100.',
        'event_flags' => 'Event flags may not be negative.',
        'id_negative' => 'Script ID may not be negative.',
        'link_negative' => 'Link may not be negative.',
        'phase_negative' => 'Phase mask may not be negative.',
      ),
      'segment' => 
      array (
        'event_required' => 'At least one event is required.',
      ),
      'event' => 
      array (
        'type' => 'Please select an event type.',
      ),
      'action' => 
      array (
        'type' => 'Please select an action type.',
      ),
      'target' => 
      array (
        'type' => 'Please select a target type.',
      ),
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'smartai' => 
      array (
        'segments' => 
        array (
          'move_up_title' => 'Move up',
          'move_down_title' => 'Move down',
          'delete_segment_title' => 'Delete segment',
          'default_label' => 'Segment :number',
          'empty_prompt' => 'Please add a segment.',
        ),
        'search' => 
        array (
          'placeholder' => 'Search keywords or ID',
        ),
        'list' => 
        array (
          'empty' => 'No matches found',
        ),
        'selector' => 
        array (
          'select_type' => 'Select a type.',
          'no_params' => 'This type has no extra parameters.',
        ),
        'validation' => 
        array (
          'entry_required' => 'Please enter a valid entry.',
          'entry_invalid' => 'A valid entry is required.',
          'segment_required' => 'Please add at least one segment.',
          'event_required_next' => 'Select an event type before continuing.',
          'event_required' => 'Please select an event type.',
          'event_required_all' => 'Please select an event type for every segment.',
          'action_required_next' => 'Select an action type before continuing.',
          'action_required' => 'Please select an action type.',
          'action_required_all' => 'Please select an action type for every segment.',
          'target_required_next' => 'Select a target type before continuing.',
          'target_required' => 'Please select a target type.',
          'target_required_all' => 'Please select a target type for every segment.',
        ),
        'api' => 
        array (
          'no_response' => 'No response from server',
        ),
        'preview' => 
        array (
          'placeholder' => '-- No SQL generated --',
          'error_placeholder' => '-- Generation failed, check form errors --',
        ),
        'summary' => 
        array (
          'segments' => 'Segments: :count',
          'event' => 'Event: :name',
          'action' => 'Action: :name',
          'target' => 'Target: :name',
        ),
        'feedback' => 
        array (
          'generate_success' => 'SQL generated successfully',
          'generate_failed' => 'Generation failed',
          'request_failed' => 'Request failed',
          'copy_success' => 'Copied to clipboard',
          'copy_failed' => 'Copy failed, please copy manually',
        ),
      ),
    ),
  ),
);
