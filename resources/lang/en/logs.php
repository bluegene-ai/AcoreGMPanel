<?php
return array (
  'page_title' => 'Unified Log Management',
  'intro' => 'Centralized view of panel module logs with quick filters, auto refresh, and raw output.',
  'fields' => 
  array (
    'module' => 'Module',
    'type' => 'Type',
    'limit' => 'Row limit',
  ),
  'actions' => 
  array (
    'load' => 'Load',
    'auto_refresh' => 'Enable auto refresh',
  ),
  'table' => 
  array (
    'headers' => 
    array (
      'time' => 'Time',
      'server' => 'Server',
      'actor' => 'Actor',
      'summary' => 'Summary',
    ),
    'loading' => 'Loading…',
  ),
  'raw' => 
  array (
    'title' => 'Raw log output',
    'empty' => '-- Waiting for data --',
  ),
  'index' => 
  array (
    'page_title' => 'Log Viewer',
    'errors' => 
    array (
      'invalid_module' => 'Invalid module or type',
      'read_failed' => 'Failed to read logs: :message',
      'unauthorized' => 'Unauthorized',
    ),
  ),
  'manager' => 
  array (
    'summary' => 
    array (
      'impact' => 'Impact: :count',
      'impact_paren' => ' (Impact: :count)',
      'error_prefix' => ' | ERR: :message',
    ),
    'pipe_sql' => 
    array (
      'summary' => ':type :status (Affected: :affected)',
      'sql_suffix' => ' | :sql',
      'error_suffix' => ' | ERR: :error',
    ),
  ),
  'config' => 
  array (
    'modules' => 
    array (
      'item_owner' => 
      array (
        'label' => 'Item Ownership',
        'description' => 'Bulk delete and replace history.',
        'types' => 
        array (
          'actions' => 
          array (
            'label' => 'Action records',
          ),
        ),
      ),
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'logs' => 
      array (
        'summary' => 
        array (
          'module' => 'Module: ',
          'type' => 'Type: ',
          'source' => 'Source: ',
          'display' => 'Showing: ',
          'separator' => ' | ',
        ),
        'status' => 
        array (
          'no_entries' => 'No log entries',
          'panel_not_ready' => 'Panel API is not ready, please verify panel.js is loaded correctly.',
          'panel_waiting' => 'Panel API is initializing, please wait…',
          'load_failed' => 'Load failed',
          'no_raw' => '-- No log --',
          'request_error' => 'Request error',
          'exception_prefix' => '[EXCEPTION] ',
          'error_prefix' => '[ERROR] ',
          'info_prefix' => '[INFO] ',
        ),
        'actions' => 
        array (
          'auto_on' => 'Enable auto refresh',
          'auto_off' => 'Disable auto refresh',
        ),
      ),
    ),
  ),
);
