<?php
return array (
  'page_title' => 'Aegis Anti-Cheat',
  'intro' => 'Review offense history, investigate suspicious events, and run manual Aegis actions on the selected realm.',
  'actions' => 
  array (
    'refresh_all' => 'Refresh all',
    'search' => 'Search',
    'clear' => 'Clear',
    'delete' => 'Delete',
    'success' => 'Aegis command executed successfully.',
    'failure' => 'Aegis command failed.',
  ),
  'errors' => 
  array (
    'player_required' => 'Player name or GUID is required.',
    'target_required' => 'Target player is required.',
    'invalid_action' => 'Invalid Aegis action.',
  ),
  'overview' => 
  array (
    'days' => 'Window',
    'days_value' => ':days days',
    'stage_distribution' => 'Stage distribution',
    'cheat_distribution' => 'Cheat distribution',
    'top_offenders' => 'Top offenders',
  ),
  'player' => 
  array (
    'title' => 'Player lookup',
    'lookup_label' => 'Player name or GUID',
    'lookup_placeholder' => 'Example: PlayerName or 12345',
    'lookup_submit' => 'Load player',
    'empty' => 'Search for a player to view the current offense snapshot and recent events.',
  ),
  'manual' => 
  array (
    'title' => 'Manual processing',
    'action_label' => 'Action',
    'target_label' => 'Target player',
    'target_placeholder' => 'Required for clear/delete',
    'submit' => 'Run command',
    'actions' => 
    array (
      'clear' => 'Clear player',
      'delete' => 'Delete player data',
      'reload' => 'Reload config',
      'purge' => 'Purge all records',
    ),
    'help' => 
    array (
      'clear' => 'Clear removes the tracked offense state for a player while preserving the character itself.',
      'delete' => 'Delete removes the persisted Aegis record for a player.',
      'reload' => 'Reload re-reads the Aegis configuration without restarting the server.',
      'purge' => 'Purge clears all persisted offense and event data for the current realm.',
    ),
  ),
  'filters' => 
  array (
    'all' => 'All',
    'query' => 'Search',
    'query_placeholder' => 'Search by player, account, GUID or reason',
    'stage' => 'Stage',
    'cheat_type' => 'Cheat type',
    'status_label' => 'Punishment status',
    'evidence_level' => 'Evidence level',
    'days' => 'Days',
    'status' => 
    array (
      'all' => 'All tracked',
      'tracked' => 'Tracked only',
      'debuffed' => 'Debuffed',
      'jailed' => 'Jailed',
      'banned' => 'Temporarily banned',
      'permanent' => 'Permanent ban',
    ),
  ),
  'offense' => 
  array (
    'title' => 'Tracked offenses',
    'columns' => 
    array (
      'player' => 'Player',
      'account' => 'Account',
      'cheat' => 'Last cheat',
      'stage' => 'Stage',
      'offense_count' => 'Offenses',
      'tier' => 'Tier',
      'last_reason' => 'Last reason',
      'last_offense_at' => 'Last offense',
      'actions' => 'Actions',
    ),
  ),
  'event' => 
  array (
    'title' => 'Suspicious events',
    'columns' => 
    array (
      'time' => 'Time',
      'player' => 'Player',
      'account' => 'Account',
      'cheat' => 'Cheat',
      'level' => 'Evidence',
      'tag' => 'Tag',
      'risk' => 'Risk',
      'position' => 'Map / Position',
      'detail' => 'Detail',
    ),
  ),
  'log' => 
  array (
    'title' => 'Raw aegis.log',
    'refresh' => 'Refresh log',
    'meta_empty' => 'Waiting to load log metadata...',
    'empty' => '-- No log lines --',
  ),
  'enums' => 
  array (
    'cheat_type' => 
    array (
      0 => 'All types',
      1 => 'Speed',
      2 => 'Fly',
      3 => 'Teleport',
      4 => 'Jump',
      5 => 'Water walk',
      6 => 'No clip',
      7 => 'Root break',
      8 => 'Controlled move',
      9 => 'Other',
    ),
    'evidence_level' => 
    array (
      0 => 'Info',
      1 => 'Low',
      2 => 'Medium',
      3 => 'High',
    ),
    'punish_stage' => 
    array (
      0 => 'Tracked',
      1 => 'Warned',
      2 => 'Debuffed',
      3 => 'Jailed',
      4 => 'Banned',
      5 => 'Permanent',
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'aegis' => 
      array (
        'status' => 
        array (
          'loading' => 'Loading...',
          'empty' => 'No data',
        ),
        'pagination' => 
        array (
          'prev' => 'Prev',
          'next' => 'Next',
          'label' => 'Page :page / :pages',
        ),
        'cards' => 
        array (
          'tracked' => 'Tracked players',
          'debuffed' => 'Debuffed',
          'jailed' => 'Jailed',
          'banned' => 'Banned',
          'last_day' => 'Events 24h',
          'window_total' => 'Events window',
        ),
        'top' => 
        array (
          'offense_count' => 'Offenses: :count',
        ),
        'player' => 
        array (
          'online' => 'Online',
          'offline' => 'Offline',
          'guid' => 'GUID: :value',
          'account' => 'Account: :value',
          'level' => 'Level: :value',
          'stage' => 'Stage: :value',
          'cheat' => 'Cheat: :value',
          'offenses' => 'Offenses: :value',
          'tier' => 'Tier: :value',
          'no_reason' => 'No reason',
          'no_offense' => 'No offense record',
          'recent_events' => 'Recent events',
        ),
        'actions' => 
        array (
          'clear' => 'Clear',
          'delete' => 'Delete',
          'success' => 'Operation completed',
          'failure' => 'Operation failed',
        ),
        'manual' => 
        array (
          'confirm' => 'Confirm this Aegis operation?',
        ),
        'log' => 
        array (
          'meta_missing' => 'Log file not found',
          'empty' => '-- No log lines --',
        ),
        'errors' => 
        array (
          'generic' => 'Request failed',
          'player_required' => 'Please enter player name or GUID',
          'target_required' => 'Target player is required',
          'load_overview' => 'Failed to load overview',
          'load_player' => 'Failed to load player',
          'load_offenses' => 'Failed to load offenses',
          'load_events' => 'Failed to load events',
          'load_log' => 'Failed to load log',
        ),
      ),
    ),
  ),
);
