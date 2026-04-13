<?php

return [
  'admin' => [
    'capabilities' => ['*'],
  ],
  'boundaries' => [
    'accounts' => [
      'list' => 'Account list and basic search surfaces.',
      'ip' => 'IP association and IP location tooling.',
      'characters' => 'Account-linked character list and status reads.',
      'create' => 'Account creation.',
      'update' => 'Account email and username changes.',
      'password' => 'Account password rotation.',
      'gm' => 'GM level changes.',
      'ban' => 'Account ban and unban actions.',
      'kick' => 'SOAP kick actions triggered from account surfaces.',
      'delete' => 'Account deletion actions.',
    ],
    'characters' => [
      'list' => 'Character list and lightweight name resolution reads.',
      'details' => 'Character detail reads including inventory, cooldowns, quests, and mail count.',
      'ban' => 'Character ban and unban actions.',
      'level' => 'Character level changes.',
      'gold' => 'Character gold changes.',
      'kick' => 'Character kick actions.',
      'teleport' => 'Character teleport and unstuck actions.',
      'reset' => 'Character reset and rename-flag actions.',
      'delete' => 'Character deletion actions.',
    ],
    'aegis' => [
      'dashboard' => 'Aegis page shell and option payload bootstrap.',
      'overview' => 'Aegis summary counters and overview cards.',
      'offenses' => 'Aegis offense search and offense history reads.',
      'events' => 'Aegis event timeline reads.',
      'player' => 'Aegis player snapshot lookups.',
      'actions' => 'Manual Aegis SOAP actions such as clear, delete, purge, and reload.',
      'logs' => 'Aegis log reads.',
    ],
    'audit' => [
      'read' => 'Audit log list and filter reads.',
    ],
    'mail' => [
      'list' => 'Mail page shell, mail list, and base filters.',
      'view' => 'Single mail detail reads including attachments.',
      'mark_read' => 'Mail read-state changes.',
      'delete' => 'Mail delete and bulk delete actions.',
      'stats' => 'Mail statistics reads.',
      'logs' => 'Mail SQL and action log reads.',
    ],
    'logs' => [
      'catalog' => 'Logs page shell and module/type catalog reads.',
      'read' => 'Log tail reads for a selected module/type.',
    ],
    'mass_mail' => [
      'compose' => 'Mass mail page shell and template bootstrap.',
      'announce' => 'Broadcast announcement sending.',
      'send' => 'Mass mail send actions for mail, items, and gold.',
      'logs' => 'Mass mail recent log reads.',
      'boost' => 'Mass-mail-triggered character boost actions.',
    ],
    'boost' => [
      'apply' => 'GM-triggered character boost actions from character detail surfaces.',
      'templates' => 'Boost template list, edit, create, update, and delete actions.',
      'codes' => 'Redeem code generation, listing, deletion, and purge management.',
    ],
    'soap' => [
      'catalog' => 'SOAP wizard page shell and command catalog reads.',
      'execute' => 'SOAP command execution.',
    ],
    'content' => [
      'view' => 'Editor list pages, edit page bootstrap, and read-only content fetches.',
      'create' => 'Content row creation and clone actions.',
      'update' => 'Content field updates and model edits.',
      'delete' => 'Content delete and destructive model removal actions.',
      'sql' => 'Ad hoc SQL execution tools embedded in content editors.',
      'logs' => 'Content SQL and action log reads.',
      'preview' => 'Generated preview and dry-run builder output.',
    ],
  ],
];