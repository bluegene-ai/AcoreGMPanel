<?php
return array (
  'page_title' => 'Account Management',
  'search' => 
  array (
    'type_username' => 'By username',
    'type_id' => 'By ID',
    'placeholder' => 'Search…',
    'submit' => 'Search',
    'load_all' => 'Load all accounts',
    'create' => 'Create account',
  ),
  'filters' => 
  array (
    'online' => 'Online status',
    'online_any' => 'All accounts',
    'online_only' => 'Online only',
    'online_offline' => 'Offline only',
    'ban' => 'Ban status',
    'ban_any' => 'All accounts',
    'ban_only' => 'Banned only',
    'ban_unbanned' => 'Not banned',
    'exclude_username' => 'Exclude text',
    'exclude_username_placeholder' => 'e.g. test',
  ),
  'feedback' => 
  array (
    'found' => ':total records found · Page :page of :pages',
    'empty' => 'No results',
    'enter_search' => 'Enter search criteria',
    'private_ip_disabled' => 'LAN IP lookup disabled',
  ),
  'table' => 
  array (
    'id' => 'ID',
    'username' => 'Username',
    'gm' => 'GM',
    'online' => 'Online',
    'last_login' => 'Last login',
    'last_ip' => 'Last IP',
    'ip_location' => 'IP location',
    'actions' => 'Actions',
  ),
  'status' => 
  array (
    'online' => 'Online',
    'offline' => 'Offline',
  ),
  'actions' => 
  array (
    'chars' => 'Characters',
    'gm' => 'GM',
    'ban' => 'Ban',
    'unban' => 'Unban',
    'password' => 'Reset password',
    'email' => 'Email',
    'rename' => 'Rename',
    'same_ip' => 'Accounts on IP',
    'kick' => 'Kick',
    'delete' => 'Delete',
  ),
  'ban' => 
  array (
    'badge' => 'Banned (:duration)',
    'tooltip' => 'Reason: :reason
Start: :start
End: :end',
    'no_end' => 'Permanent',
    'permanent' => 'Permanent',
    'soon' => 'Ends soon',
    'under_minute' => 'Under 1 minute',
    'separator' => ' ',
    'duration' => 
    array (
      'day' => ':value day',
      'hour' => ':value hr',
      'minute' => ':value min',
    ),
    'prompt_hours' => 'Ban duration in hours (0 = permanent):',
    'error_hours' => 'Invalid duration',
    'prompt_reason' => 'Ban reason:',
    'default_reason' => 'Panel ban',
    'success' => 'Account banned successfully',
    'failure' => 'Failed to ban account',
    'confirm_unban' => 'Unban this account?',
    'unban_success' => 'Account unbanned',
    'unban_failure' => 'Failed to unban account',
  ),
  'ip_lookup' => 
  array (
    'private' => 'Private IP',
    'failed' => 'Lookup failed',
    'unknown' => 'Unknown location',
    'loading' => 'Looking up…',
  ),
  'characters' => 
  array (
    'title' => 'Character list - :name',
    'loading' => 'Loading…',
    'fetch_error' => 'Failed to load characters',
    'table' => 
    array (
      'guid' => 'GUID',
      'name' => 'Name',
      'level' => 'Level',
      'status' => 'Status',
    ),
    'kick_button' => 'Kick offline',
    'offline_tooltip' => 'Character offline, cannot kick',
    'empty' => 'No characters',
    'ban_badge' => 'Banned',
    'confirm_kick' => 'Kick character :name?',
    'kick_success' => 'Kick command dispatched: :name',
    'kick_failed' => 'Kick failed: :message',
    'fetch_failed' => 'Failed to load characters: :message',
  ),
  'gm' => 
  array (
    'prompt_level' => 'Set GM level (0-6):',
    'error_level' => 'Invalid GM level',
    'success' => 'GM level updated',
    'failure' => 'Failed to update GM level',
  ),
  'password' => 
  array (
    'prompt_new' => 'Enter new password (min 8 chars):',
    'error_empty' => 'Password cannot be empty',
    'error_length' => 'Password must be at least 8 characters',
    'prompt_confirm' => 'Re-enter new password:',
    'error_mismatch' => 'Passwords do not match',
    'success' => 'Password updated successfully (previous sessions invalidated)',
    'failure' => 'Failed to change password: :message',
    'failure_generic' => 'Unknown error',
  ),
  'email' => 
  array (
    'title' => 'Update email - :name',
    'labels' => 
    array (
      'email' => 'Email',
    ),
    'placeholders' => 
    array (
      'email' => 'example@domain.com',
    ),
    'actions' => 
    array (
      'cancel' => 'Cancel',
      'submit' => 'Save',
    ),
    'invalid' => 'Invalid email address',
    'not_supported' => 'Email column is not available in this schema',
    'blocked_online' => 'Cannot update email while account is online',
    'success' => 'Email updated',
  ),
  'rename' => 
  array (
    'title' => 'Rename account - :name',
    'labels' => 
    array (
      'username' => 'New username',
      'password' => 'New password',
      'password_confirm' => 'Confirm password',
    ),
    'actions' => 
    array (
      'cancel' => 'Cancel',
      'submit' => 'Save',
    ),
    'invalid_username' => 'Invalid username (1-20 chars)',
    'invalid_password' => 'Password must be at least 8 characters',
    'password_mismatch' => 'Passwords do not match',
    'password_reset_failed' => 'Password reset failed (cannot generate verifier)',
    'blocked_online' => 'Cannot rename while account is online',
    'taken' => 'Username is already taken',
    'success' => 'Username updated (:old → :new)',
  ),
  'create' => 
  array (
    'title' => 'Create account',
    'labels' => 
    array (
      'username' => 'Username',
      'password' => 'Password',
      'password_confirm' => 'Confirm password',
      'email' => 'Email (optional)',
      'gmlevel' => 'GM level',
    ),
    'placeholders' => 
    array (
      'username' => 'Case insensitive',
      'password' => 'At least 8 characters',
      'password_confirm' => 'Re-enter password',
      'email' => 'example@domain.com',
    ),
    'gm_options' => 
    array (
      'player' => '0 - Player',
      'one' => '1',
      'two' => '2',
      'three' => '3',
    ),
    'actions' => 
    array (
      'cancel' => 'Cancel',
      'submit' => 'Create',
    ),
    'status' => 
    array (
      'submitting' => 'Creating…',
    ),
    'errors' => 
    array (
      'username_required' => 'Please enter a username',
      'username_length' => 'Username may not exceed 32 characters',
      'password_length' => 'Password must be at least 8 characters',
      'password_mismatch' => 'Passwords do not match',
      'email_length' => 'Email address is too long',
      'email_invalid' => 'Invalid email address',
      'request_generic' => 'Creation failed',
    ),
    'success' => 'Account created: :name',
  ),
  'same_ip' => 
  array (
    'missing_ip' => 'No last IP available for this account',
    'title' => 'Accounts on IP - :ip',
    'loading' => 'Loading…',
    'empty' => 'No other accounts found for this IP',
    'table' => 
    array (
      'id' => 'ID',
      'username' => 'Username',
      'gm' => 'GM',
      'status' => 'Status',
      'last_login' => 'Last login',
      'ip_location' => 'IP location',
    ),
    'status' => 
    array (
      'banned' => 'Banned',
      'remaining' => 'Remaining: :value',
    ),
    'error_generic' => 'Failed to query accounts',
    'error' => 'Failed to query accounts: :message',
  ),
  'api' => 
  array (
    'validation' => 
    array (
      'username_min' => 'Username must be at least 3 characters long',
      'username_max' => 'Username may not exceed 32 characters',
      'password_min' => 'Password must be at least 8 characters long',
      'gm_range' => 'GM level must be between 0 and 6',
    ),
    'defaults' => 
    array (
      'no_reason' => 'No reason',
    ),
    'errors' => 
    array (
      'missing_username_column' => 'Account table is missing the username column',
      'username_exists' => 'Username already exists',
      'build_columns_failed' => 'Unable to build the account insert column set',
      'missing_account_id' => 'Unable to retrieve the newly created account ID',
      'password_set_failed' => 'Failed to set the account password',
      'create_failed' => 'Failed to create account: :message',
      'query_characters_failed' => 'Failed to query characters: :message',
      'password_schema_unsupported' => 'Password change failed: account schema does not support SRP or sha_pass_hash updates',
    ),
  ),
);
