<?php
return array (
  'index' => 
  array (
    'page_title' => 'Creature Management',
    'filters' => 
    array (
      'search_type' => 
      array (
        'name' => 'By name',
        'id' => 'By ID',
      ),
      'placeholders' => 
      array (
        'search_value' => 'Keyword or ID',
        'min_level' => 'Min level',
        'max_level' => 'Max level',
      ),
      'buttons' => 
      array (
        'search' => 'Search',
        'reset' => 'Reset',
        'create' => 'Create',
        'log' => 'View logs',
      ),
    ),
    'npcflag' => 
    array (
      'summary' => 'NPC flag filter',
      'apply' => 'Apply',
      'clear' => 'Clear',
      'mode_hint' => 'Mode: all selected bits must be present (AND)',
    ),
    'table' => 
    array (
      'headers' => 
      array (
        'id' => 'ID',
        'name' => 'Name',
        'subname' => 'Subname',
        'min_level' => 'Min level',
        'max_level' => 'Max level',
        'faction' => 'Faction',
        'npcflag' => 'NPC flags',
        'actions' => 'Actions',
        'verify' => 'Verify',
      ),
      'actions' => 
      array (
        'edit' => 'Edit',
        'delete' => 'Delete',
      ),
      'verify_button' => 'Verify',
      'empty' => 'No results',
    ),
    'modals' => 
    array (
      'new' => 
      array (
        'title' => 'Create creature',
        'id_label' => 'New ID*',
        'copy_label' => 'Copy from (optional)',
        'copy_hint' => 'Leave the copy ID empty to create a blank template.',
        'cancel' => 'Cancel',
        'confirm' => 'Create',
      ),
      'log' => 
      array (
        'title' => 'Creature logs',
        'type_label' => 'Log type',
        'types' => 
        array (
          'sql' => 'SQL execution',
          'deleted' => 'Delete snapshots',
          'actions' => 'Action trace',
        ),
        'refresh' => 'Refresh',
        'empty' => '-- No logs yet --',
        'close' => 'Close',
      ),
      'verify' => 
      array (
        'title' => 'Row verification',
        'headers' => 
        array (
          'field' => 'Field',
          'rendered' => 'Rendered value',
          'database' => 'Database value',
          'status' => 'Status',
        ),
        'close' => 'Close',
        'copy_sql' => 'Copy UPDATE statement',
      ),
    ),
  ),
  'edit' => 
  array (
    'title' => 'Edit creature #:id',
    'actions' => 
    array (
      'back' => 'Back to list',
      'compact' => 'Compact',
      'delete' => 'Delete',
      'save' => 'Save',
      'diff_sql' => 'Diff SQL',
      'exec_sql' => 'Execute SQL',
      'copy' => 'Copy',
      'execute' => 'Execute',
      'add_model' => 'Add model',
      'edit_model' => 'Edit',
      'delete_model' => 'Delete',
      'cancel' => 'Cancel',
    ),
    'labels' => 
    array (
      'only_changes' => 'Only changes',
    ),
    'toolbar' => 
    array (
      'changed_fields' => 'Changed fields:',
    ),
    'diff' => 
    array (
      'title' => 'Diff SQL preview',
      'hint' => 'Click "Diff SQL" to generate. Only modified columns are included. Empty strings become NULL and LIMIT 1 is added automatically.',
      'placeholder' => '-- Not generated yet --',
    ),
    'models' => 
    array (
      'heading' => 'Model list (creature_template_model)',
      'table' => 
      array (
        'index' => 'Index',
        'display_id' => 'Display ID',
        'scale' => 'Scale',
        'probability' => 'Probability',
        'verified_build' => 'Verified build',
        'actions' => 'Actions',
      ),
      'empty' => 'No models',
    ),
    'modal' => 
    array (
      'title' => 'Model',
      'display_id' => 'Display ID',
      'scale' => 'Scale',
      'probability' => 'Probability (0-1)',
      'verified_build' => 'Verified build',
    ),
    'rank_enum' => 
    array (
      0 => 'Normal',
      1 => 'Elite',
      2 => 'Rare Elite',
      3 => 'Boss',
      4 => 'Rare',
    ),
    'type_enum' => 
    array (
      0 => 'None',
      1 => 'Beast',
      2 => 'Dragonkin',
      3 => 'Demon',
      4 => 'Elemental',
      5 => 'Giant',
      6 => 'Undead',
      7 => 'Humanoid',
      8 => 'Critter',
      9 => 'Mechanical',
      10 => 'Not specified',
      11 => 'Totem',
      12 => 'Companion',
      13 => 'Gas Cloud',
    ),
  ),
  'config' => 
  array (
    'groups' => 
    array (
      'base' => 
      array (
        'label' => 'Base information',
        'fields' => 
        array (
          'name' => 
          array (
            'label' => 'Name',
          ),
          'subname' => 
          array (
            'label' => 'Subname',
          ),
          'minlevel' => 
          array (
            'label' => 'Minimum level',
          ),
          'maxlevel' => 
          array (
            'label' => 'Maximum level',
          ),
          'exp' => 
          array (
            'label' => 'Experience type (exp)',
            'help' => '0=None, 1=Normal, 2=Elite',
          ),
          'faction' => 
          array (
            'label' => 'Faction ID (faction)',
          ),
          'scale' => 
          array (
            'label' => 'Model scale (scale)',
          ),
          'speed_walk' => 
          array (
            'label' => 'Walk speed (speed_walk)',
          ),
          'speed_run' => 
          array (
            'label' => 'Run speed (speed_run)',
          ),
          'rank' => 
          array (
            'label' => 'Rank type (rank)',
          ),
          'type' => 
          array (
            'label' => 'Creature type (type)',
          ),
        ),
      ),
      'combat' => 
      array (
        'label' => 'Combat parameters',
        'fields' => 
        array (
          'dmgschool' => 
          array (
            'label' => 'Damage school (dmgschool)',
          ),
          'baseattacktime' => 
          array (
            'label' => 'Melee attack interval (ms)',
          ),
          'rangeattacktime' => 
          array (
            'label' => 'Ranged attack speed (ms)',
          ),
          'mindmg' => 
          array (
            'label' => 'Melee minimum damage (mindmg)',
          ),
          'maxdmg' => 
          array (
            'label' => 'Melee maximum damage (maxdmg)',
          ),
          'dmg_multiplier' => 
          array (
            'label' => 'Damage modifier (dmg_multiplier)',
          ),
          'basevariance' => 
          array (
            'label' => 'Damage variance (basevariance)',
          ),
          'rangevariance' => 
          array (
            'label' => 'Ranged damage variance (rangevariance)',
          ),
          'attackpower' => 
          array (
            'label' => 'Melee attack power (attackpower)',
          ),
          'rangedattackpower' => 
          array (
            'label' => 'Ranged attack power (rangedattackpower)',
          ),
        ),
      ),
      'vitals' => 
      array (
        'label' => 'Health / Mana / Resistances',
        'fields' => 
        array (
          'healthmodifier' => 
          array (
            'label' => 'Health modifier (healthmodifier)',
          ),
          'manamodifier' => 
          array (
            'label' => 'Mana modifier (manamodifier)',
          ),
          'armormodifier' => 
          array (
            'label' => 'Armor modifier (armormodifier)',
          ),
          'resistance1' => 
          array (
            'label' => 'Holy resistance (resistance1)',
          ),
          'resistance2' => 
          array (
            'label' => 'Fire resistance (resistance2)',
          ),
          'resistance3' => 
          array (
            'label' => 'Nature resistance (resistance3)',
          ),
          'resistance4' => 
          array (
            'label' => 'Frost resistance (resistance4)',
          ),
          'resistance5' => 
          array (
            'label' => 'Shadow resistance (resistance5)',
          ),
          'resistance6' => 
          array (
            'label' => 'Arcane resistance (resistance6)',
          ),
        ),
      ),
      'drops' => 
      array (
        'label' => 'Drops / Economy',
        'fields' => 
        array (
          'lootid' => 
          array (
            'label' => 'Standard loot ID (lootid)',
          ),
          'pickpocketloot' => 
          array (
            'label' => 'Pickpocket loot ID (pickpocketloot)',
          ),
          'skinloot' => 
          array (
            'label' => 'Skinning loot ID (skinloot)',
          ),
          'mingold' => 
          array (
            'label' => 'Gold minimum (mingold)',
          ),
          'maxgold' => 
          array (
            'label' => 'Gold maximum (maxgold)',
          ),
        ),
      ),
      'ai' => 
      array (
        'label' => 'AI / Scripts',
        'fields' => 
        array (
          'ainame' => 
          array (
            'label' => 'AI name (ainame)',
          ),
          'scriptname' => 
          array (
            'label' => 'Script name (scriptname)',
          ),
          'gossip_menu_id' => 
          array (
            'label' => 'Gossip menu ID (gossip_menu_id)',
          ),
          'movementtype' => 
          array (
            'label' => 'Movement type (movementtype)',
          ),
        ),
      ),
    ),
    'flags' => 
    array (
      'label' => 'Flags / Bitmasks',
      'fields' => 
      array (
        'npcflag' => 
        array (
          'label' => 'NPC flags (npcflag)',
        ),
        'unit_flags' => 
        array (
          'label' => 'Unit flags (unit_flags)',
        ),
        'unit_flags2' => 
        array (
          'label' => 'Unit flags 2 (unit_flags2)',
        ),
        'type_flags' => 
        array (
          'label' => 'Type flags (type_flags)',
        ),
        'flags_extra' => 
        array (
          'label' => 'Extra flags (flags_extra)',
        ),
        'dynamicflags' => 
        array (
          'label' => 'Dynamic flags (dynamicflags)',
        ),
      ),
    ),
  ),
  'flags' => 
  array (
    'npcflag' => 
    array (
      0 => 'Gossip',
      1 => 'QuestGiver',
      2 => 'Trainer',
      3 => 'ClassTrainer',
      4 => 'ProfessionTrainer',
      5 => 'Vendor',
      6 => 'VendorAmmo',
      7 => 'VendorFood',
      8 => 'VendorPoison',
      9 => 'VendorReagent',
      10 => 'Repair',
      11 => 'FlightMaster',
      12 => 'SpiritHealer',
      13 => 'SpiritGuide',
      14 => 'Innkeeper',
      15 => 'Banker',
      16 => 'Petitioner',
      17 => 'TabardDesigner',
      18 => 'BattleMaster',
      19 => 'Auctioneer',
      20 => 'StableMaster',
      21 => 'GuildBanker',
      22 => 'Spellclick',
      23 => 'Mailbox',
    ),
    'unit_flags' => 
    array (
      0 => 'ServerControlled',
      1 => 'NonAttackable',
      2 => 'RemoveClientControl',
      3 => 'PlayerControlled',
      4 => 'Rename',
      5 => 'Preparation',
      6 => 'Unk6',
      7 => 'NotAttackable1',
      8 => 'ImmunePC',
      9 => 'ImmuneNPC',
      10 => 'Looting',
      11 => 'PetInCombat',
      12 => 'PvP',
      13 => 'Silenced',
      14 => 'CannotSwim',
      15 => 'CanSwim',
      16 => 'NonAttackable2',
      17 => 'Pacified',
      18 => 'Stunned',
      19 => 'InCombat',
      20 => 'TaxiFlight',
      21 => 'Disarmed',
    ),
    'unit_flags2' => 
    array (
      0 => 'FeignDeath',
      1 => 'HideBody',
      2 => 'Unk2',
      3 => 'NoSelect',
      4 => 'InteractWhileDead',
      5 => 'ForceMovement',
      6 => 'DisarmOffhand',
      7 => 'Unk7',
      8 => 'AllowChangingTalents',
      9 => 'NotTauntable',
    ),
    'type_flags' => 
    array (
      0 => 'Tameable',
      1 => 'GhostVisible',
      2 => 'Boss',
      3 => 'DoNotPlayWoundParryAnim',
      4 => 'NoLoot',
      5 => 'NoXP',
      6 => 'Trigger',
      7 => 'Guard',
    ),
    'flags_extra' => 
    array (
      0 => 'InstanceBind',
      1 => 'Civilian',
      2 => 'NoAggro',
      3 => 'NoInteract',
      4 => 'TameablePet',
      5 => 'DeadInteract',
      6 => 'ForceGossip',
    ),
    'dynamicflags' => 
    array (
      0 => 'Glow',
      1 => 'Lootable',
      2 => 'TrackUnit',
      3 => 'Tapped',
      4 => 'TappedByPlayer',
      5 => 'SpecialInfo',
    ),
  ),
  'factions' => 
  array (
    14 => 'Monster',
    35 => 'Friendly Guard',
    68 => 'Generic Friendly',
    69 => 'Player: Alliance',
    70 => 'Player: Horde',
    74 => 'Stormwind Guard',
    84 => 'Ironforge Guard',
    120 => 'Thunder Bluff Guard',
    121 => 'Orgrimmar Guard',
    122 => 'Darnassus Guard',
    123 => 'Gnomeregan Guard',
    124 => 'Undercity Guard',
    169 => 'Steamwheedle Port',
    469 => 'Alliance',
    529 => 'Argent Dawn',
    530 => 'Horde',
    609 => 'Cenarion Circle',
    910 => 'Darkmoon Faire',
    934 => 'The Frostborn',
    935 => 'Valiance Expedition',
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'creature' => 
      array (
        'create' => 
        array (
          'enter_new_id' => 'Please enter a new ID',
          'success_redirect' => 'Creature created, redirecting...',
          'failure' => 'Failed to create creature',
          'failure_with_reason' => 'Creation failed: :reason',
        ),
        'logs' => 
        array (
          'loading_placeholder' => '-- Loading... --',
          'empty_placeholder' => '-- No logs --',
          'load_failed_placeholder' => '-- Load failed --',
          'load_failed' => 'Failed to load logs',
          'load_failed_with_reason' => 'Failed to load logs: :reason',
        ),
        'list' => 
        array (
          'confirm_delete' => 'Delete creature :id?',
          'delete_success' => 'Creature deleted',
          'delete_failed' => 'Failed to delete creature',
          'delete_failed_with_reason' => 'Failed to delete creature: :reason',
        ),
        'diff' => 
        array (
          'group_change_count' => '(:count changes)',
          'no_changes_placeholder' => '-- No changes --',
          'copy_sql_success' => 'SQL copied',
        ),
        'common' => 
        array (
          'copy_failed' => 'Copy failed',
        ),
        'errors' => 
        array (
          'panel_api_not_ready' => 'Panel API is not ready',
        ),
        'exec' => 
        array (
          'actions' => 
          array (
            'clear' => 'Clear',
            'hide' => 'Hide',
            'copy_json' => 'Copy JSON',
            'copy_sql' => 'Copy SQL',
          ),
          'result_heading' => 'Execution result',
          'rows_affected' => 'Rows: :count',
          'sample_row_heading' => 'Sample row',
          'sql_prefix' => 'SQL: :sql',
          'copy_json_success' => 'JSON copied',
          'copy_sql_success' => 'SQL copied',
          'default_success' => 'Execution succeeded',
          'default_error' => 'Execution failed',
          'failure_with_reason' => ':prefix: :reason',
          'sql_empty_notify' => 'SQL is empty, cannot execute',
          'sql_empty_response' => 'SQL is empty',
          'confirm_run_diff' => 'Execute the following SQL?
:sql',
          'no_diff_sql' => 'No diff SQL to execute',
          'diff_sql_success' => 'Diff SQL executed successfully',
          'prompt_sql' => 'Enter a single UPDATE/INSERT SQL statement',
          'only_update_insert' => 'Only UPDATE or INSERT statements are allowed',
          'status' => 
          array (
            'save_success' => 'Save succeeded',
            'run_success' => 'Execution succeeded',
            'save_failed' => 'Save failed',
            'run_failed' => 'Execution failed',
          ),
        ),
        'models' => 
        array (
          'confirm_delete' => 'Delete this model?',
          'save_success' => 'Model saved successfully',
          'save_failed' => 'Failed to save model',
          'save_failed_with_reason' => 'Failed to save model: :reason',
          'delete_success' => 'Model deleted',
          'delete_failed' => 'Failed to delete model',
          'delete_failed_with_reason' => 'Failed to delete model: :reason',
        ),
        'save' => 
        array (
          'no_changes' => 'No changes to save',
          'success' => 'Saved successfully',
          'failed' => 'Failed to save',
          'failed_with_reason' => 'Failed to save: :reason',
          'confirm_delete_creature' => 'Delete creature :id?',
          'delete_success' => 'Deleted successfully',
          'delete_failed' => 'Failed to delete',
          'delete_failed_with_reason' => 'Failed to delete: :reason',
        ),
        'verify' => 
        array (
          'failure' => 'Verification failed',
          'failure_with_reason' => 'Verification failed: :reason',
          'diff_bad' => 'Different',
          'diff_ok' => 'Match',
          'diff_summary' => 'Detected :count mismatches',
          'copy_update' => 'Copy UPDATE statement',
          'copied' => 'Copied',
          'row_match' => 'Row matches database',
        ),
        'nav' => 
        array (
          'auto_group_title' => 'Group :index',
        ),
        'compact' => 
        array (
          'mode' => 
          array (
            'normal' => 'Normal',
            'compact' => 'Compact',
          ),
        ),
        'bitmask' => 
        array (
          'modal_title' => 'Bitmask selection',
          'search_placeholder' => 'Search...',
          'select_all' => 'Select all',
          'clear' => 'Clear',
          'tips' => 'Tip: checking will update the value immediately. Use search to filter descriptions.',
          'close' => 'Close',
          'field_title' => ':field (:value)',
          'trigger' => 'Bits',
        ),
      ),
    ),
  ),
);
