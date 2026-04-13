<?php
return array (
  'page_title' => 'SOAP Command Wizard',
  'intro' => 'A curated list of AzerothCore GM commands. Pick one and follow the wizard to fill parameters and execute.',
  'search_label' => 'Search commands',
  'search_placeholder' => 'Type keywords or command fragments',
  'summary' => 
  array (
    'title' => 'Select a command',
    'hint' => 'Choose a category and command on the left. Use search to filter, and switch realms through the top dropdown.',
  ),
  'target_hint' => 'Requires selecting a target in-game before executing.',
  'steps' => 
  array (
    'fill' => 'Step 1: Provide parameters',
    'confirm' => 'Step 2: Confirm command',
  ),
  'preview_label' => 'Pending command',
  'actions' => 
  array (
    'copy' => 'Copy command',
    'execute' => 'Execute command',
  ),
  'output_title' => 'Execution result',
  'legacy' => 
  array (
    'errors' => 
    array (
      'curl_failed' => 'Failed to contact the SOAP endpoint.',
      'curl_error_unknown' => 'Unknown cURL error.',
      'http_error' => 'SOAP request returned HTTP status :code.',
    ),
  ),
  'api' => 
  array (
    'errors' => 
    array (
      'unauthorized' => 'Unauthorized',
      'invalid_arguments' => 'Invalid arguments.',
    ),
  ),
  'modules' => 
  array (
    'soap' => 
    array (
      'feedback' => 
      array (
        'execute_success' => 'Executed successfully',
        'execute_failed' => 'Execution failed',
      ),
    ),
  ),
  'wizard' => 
  array (
    'errors' => 
    array (
      'command_not_found' => 'Command definition not found',
      'command_missing' => 'The command is not available or has been retired',
      'argument_required' => 'This field is required',
      'validation_failed' => 'Validation failed',
      'template_missing_list' => 'Missing template parameters: :fields',
      'template_incomplete' => 'Command template is incomplete',
      'number_required' => 'Please enter a numeric value',
      'number_invalid' => 'The value must be a number',
      'number_too_small' => 'Value may not be less than :min',
      'number_too_large' => 'Value may not exceed :max',
      'invalid_option' => 'Invalid option selected',
    ),
    'catalog' => 
    array (
      'categories' => 
      array (
        'general' => 
        array (
          'label' => 'General',
          'summary' => 'Server status, announcements, and GM utilities',
        ),
        'account' => 
        array (
          'label' => 'Account management',
          'summary' => 'Account GM levels, locking, and bans',
        ),
        'character' => 
        array (
          'label' => 'Character management',
          'summary' => 'Character level, appearance, and status control',
        ),
        'teleport' => 
        array (
          'label' => 'Teleport / position',
          'summary' => 'Teleportation and positioning commands for characters or GMs',
        ),
        'item' => 
        array (
          'label' => 'Items / equipment',
          'summary' => 'Add or remove items for the target',
        ),
        'spell' => 
        array (
          'label' => 'Spells / skills',
          'summary' => 'Grant or remove spells, skills, or talents',
        ),
        'quest' => 
        array (
          'label' => 'Quests',
          'summary' => 'Grant, complete, or remove quests',
        ),
        'misc' => 
        array (
          'label' => 'Appearance / status',
          'summary' => 'Miscellaneous commands such as morphing or editing money',
        ),
      ),
      'commands' => 
      array (
        'server-info' => 
        array (
          'description' => 'Show core information, build time, online players, and uptime.',
        ),
        'server-motd' => 
        array (
          'description' => 'View or update the server MOTD (login message).',
          'arguments' => 
          array (
            'message' => 
            array (
              'label' => 'Announcement message',
              'placeholder' => 'Leave empty to display the current MOTD',
            ),
          ),
        ),
        'announce-global' => 
        array (
          'description' => 'Broadcast a system announcement to all players.',
          'arguments' => 
          array (
            'message' => 
            array (
              'label' => 'Announcement message',
              'placeholder' => 'Enter the announcement text',
            ),
          ),
        ),
        'announce-name' => 
        array (
          'description' => 'Send an announcement that displays the GM name.',
          'arguments' => 
          array (
            'message' => 
            array (
              'label' => 'Announcement message',
            ),
          ),
        ),
        'notify' => 
        array (
          'description' => 'Show a center-screen notification to all players.',
          'arguments' => 
          array (
            'message' => 
            array (
              'label' => 'Notification text',
            ),
          ),
        ),
        'gm-visible' => 
        array (
          'description' => 'Toggle GM visibility in the world.',
          'arguments' => 
          array (
            'state' => 
            array (
              'label' => 'Visibility state',
              'options' => 
              array (
                'on' => 'on - Enable GM invisibility',
                'off' => 'off - Disable GM invisibility',
              ),
            ),
          ),
        ),
        'account-set-gmlevel' => 
        array (
          'description' => 'Set the GM level for an account.',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => 'Account username',
            ),
            'level' => 
            array (
              'label' => 'GM level',
              'options' => 
              array (
                0 => '0 - Player',
                1 => '1 - Junior GM',
                2 => '2 - Full GM',
                3 => '3 - Administrator',
              ),
            ),
            'realm' => 
            array (
              'label' => 'Realm ID (optional)',
              'placeholder' => 'Leave empty to apply to all realms',
            ),
          ),
        ),
        'account-set-password' => 
        array (
          'description' => 'Reset an account password.',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => 'Account username',
            ),
            'password' => 
            array (
              'label' => 'New password',
            ),
          ),
        ),
        'account-lock' => 
        array (
          'description' => 'Enable or disable account locking.',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => 'Account username',
            ),
            'state' => 
            array (
              'label' => 'Lock state',
              'options' => 
              array (
                'on' => 'on - Lock logins',
                'off' => 'off - Unlock logins',
              ),
            ),
          ),
        ),
        'ban-account' => 
        array (
          'description' => 'Ban an account for a duration with an optional reason.',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => 'Account username',
            ),
            'duration' => 
            array (
              'label' => 'Ban duration',
              'placeholder' => 'For example 3d, 12h, or permanent',
            ),
            'reason' => 
            array (
              'label' => 'Reason (optional)',
            ),
          ),
        ),
        'unban-account' => 
        array (
          'description' => 'Lift an account ban.',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => 'Account username',
            ),
          ),
        ),
        'character-level' => 
        array (
          'description' => 'Set a character\'s level.',
          'arguments' => 
          array (
            'name' => 
            array (
              'label' => 'Character name',
            ),
            'level' => 
            array (
              'label' => 'Level',
            ),
          ),
        ),
        'character-rename' => 
        array (
          'description' => 'Force a character to rename on next login.',
          'arguments' => 
          array (
            'name' => 
            array (
              'label' => 'Character name',
            ),
          ),
        ),
        'character-customize' => 
        array (
          'description' => 'Force a character to customize appearance on next login.',
          'arguments' => 
          array (
            'name' => 
            array (
              'label' => 'Character name',
            ),
          ),
        ),
        'character-revive' => 
        array (
          'description' => 'Revive a dead character.',
          'arguments' => 
          array (
            'name' => 
            array (
              'label' => 'Character name',
            ),
          ),
        ),
        'character-lookup' => 
        array (
          'description' => 'Search characters by name pattern.',
          'arguments' => 
          array (
            'pattern' => 
            array (
              'label' => 'Character name keyword',
            ),
          ),
        ),
        'tele-name' => 
        array (
          'description' => 'Teleport to a predefined location (must exist in the database).',
          'arguments' => 
          array (
            'location' => 
            array (
              'label' => 'Location name',
            ),
          ),
        ),
        'tele-worldport' => 
        array (
          'description' => 'Teleport to explicit map coordinates. Confirm the coordinates before use.',
          'arguments' => 
          array (
            'map' => 
            array (
              'label' => 'Map ID',
            ),
            'x' => 
            array (
              'label' => 'X coordinate',
            ),
            'y' => 
            array (
              'label' => 'Y coordinate',
            ),
            'z' => 
            array (
              'label' => 'Z coordinate',
            ),
            'o' => 
            array (
              'label' => 'Facing (optional)',
            ),
          ),
          'notes' => 
          array (
            'ensure_valid' => 'Ensure the coordinates are valid to avoid disconnects or stuck characters.',
          ),
        ),
        'go-creature' => 
        array (
          'description' => 'Teleport to the location of a creature GUID.',
          'arguments' => 
          array (
            'guid' => 
            array (
              'label' => 'Creature GUID',
            ),
          ),
        ),
        'go-object' => 
        array (
          'description' => 'Teleport to the location of a game object GUID.',
          'arguments' => 
          array (
            'guid' => 
            array (
              'label' => 'Game object GUID',
            ),
          ),
        ),
        'summon-player' => 
        array (
          'description' => 'Summon a player to the GM\'s position.',
          'arguments' => 
          array (
            'player' => 
            array (
              'label' => 'Player name',
            ),
          ),
          'notes' => 
          array (
            'require_online' => 'Target player must be online.',
          ),
        ),
        'additem' => 
        array (
          'description' => 'Give the selected player a specific item.',
          'arguments' => 
          array (
            'item' => 
            array (
              'label' => 'Item ID',
            ),
            'count' => 
            array (
              'label' => 'Count (optional)',
            ),
          ),
        ),
        'additemset' => 
        array (
          'description' => 'Give the selected player an entire item set.',
          'arguments' => 
          array (
            'itemset' => 
            array (
              'label' => 'Item set ID',
            ),
          ),
        ),
        'removeitem' => 
        array (
          'description' => 'Remove an item from the selected player.',
          'arguments' => 
          array (
            'item' => 
            array (
              'label' => 'Item ID',
            ),
            'count' => 
            array (
              'label' => 'Count (optional)',
            ),
          ),
        ),
        'learn-spell' => 
        array (
          'description' => 'Teach the selected character a spell or skill.',
          'arguments' => 
          array (
            'spell' => 
            array (
              'label' => 'Spell ID',
            ),
          ),
        ),
        'unlearn-spell' => 
        array (
          'description' => 'Remove a spell or skill from the selected character.',
          'arguments' => 
          array (
            'spell' => 
            array (
              'label' => 'Spell ID',
            ),
          ),
        ),
        'talent-reset' => 
        array (
          'description' => 'Reset the selected character\'s talents.',
        ),
        'quest-add' => 
        array (
          'description' => 'Add a quest to the selected character.',
          'arguments' => 
          array (
            'quest' => 
            array (
              'label' => 'Quest ID',
            ),
          ),
        ),
        'quest-complete' => 
        array (
          'description' => 'Complete a quest for the selected character.',
          'arguments' => 
          array (
            'quest' => 
            array (
              'label' => 'Quest ID',
            ),
          ),
        ),
        'quest-remove' => 
        array (
          'description' => 'Remove a quest from the selected character.',
          'arguments' => 
          array (
            'quest' => 
            array (
              'label' => 'Quest ID',
            ),
          ),
        ),
        'morph' => 
        array (
          'description' => 'Morph the selected character into a display ID.',
          'arguments' => 
          array (
            'display' => 
            array (
              'label' => 'Display ID',
            ),
          ),
        ),
        'demorph' => 
        array (
          'description' => 'Restore the selected character\'s original model.',
        ),
        'modify-money' => 
        array (
          'description' => 'Add or remove copper from the selected character; positive adds, negative removes.',
          'arguments' => 
          array (
            'amount' => 
            array (
              'label' => 'Copper amount (can be negative)',
              'placeholder' => 'e.g. 100000 (10 gold) or -5000',
            ),
          ),
        ),
        'modify-speed' => 
        array (
          'description' => 'Adjust the selected character\'s movement speed multiplier.',
          'arguments' => 
          array (
            'multiplier' => 
            array (
              'label' => 'Speed multiplier',
              'placeholder' => '1 = normal, 2 = double',
            ),
          ),
        ),
      ),
    ),
  ),
  'executor' => 
  array (
    'errors' => 
    array (
      'empty' => 'Command cannot be empty',
      'not_whitelisted' => 'Command is not whitelisted',
      'request_failed' => 'Request failed',
      'unknown' => 'Unknown error',
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'soap' => 
      array (
        'meta' => 
        array (
          'updated_at' => 'Command catalog updated at :date',
          'source_link' => 'GM Commands',
          'source_label' => 'Reference: :link',
          'separator' => ' · ',
        ),
        'categories' => 
        array (
          'all' => 
          array (
            'label' => 'All commands',
            'summary' => 'Show all catalogued commands',
          ),
        ),
        'list' => 
        array (
          'empty' => 'No matching commands',
        ),
        'risk' => 
        array (
          'badge' => 
          array (
            'low' => 'Low risk',
            'medium' => 'Medium risk',
            'high' => 'High risk',
            'unknown' => 'Unknown risk',
          ),
          'short' => 
          array (
            'low' => 'L',
            'medium' => 'M',
            'high' => 'H',
            'unknown' => '?',
          ),
        ),
        'fields' => 
        array (
          'empty' => 'This command requires no additional parameters.',
        ),
        'errors' => 
        array (
          'missing_required' => 'Please fill all required fields.',
          'unknown_response' => 'Unknown response',
        ),
        'form' => 
        array (
          'error_joiner' => ', ',
        ),
        'feedback' => 
        array (
          'execute_success' => 'Command executed successfully',
          'execute_failed' => 'Command failed',
        ),
        'output' => 
        array (
          'unknown_time' => 'Unknown time',
          'meta' => 'Status: :code · Time: :time',
          'empty' => '(No output)',
        ),
        'copy' => 
        array (
          'empty' => 'Nothing to copy',
          'success' => 'Copied to clipboard',
          'failure' => 'Copy failed',
        ),
      ),
    ),
  ),
);
