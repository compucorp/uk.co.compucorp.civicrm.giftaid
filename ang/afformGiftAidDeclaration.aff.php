<?php
use CRM_Civigiftaid_ExtensionUtil as E;

return [
  'type' => 'form',
  'title' => E::ts('Gift Aid Declaration'),
  'placement' => [
    'msg_token_single',
  ],
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/gift-aid-declaration',
  'is_public' => TRUE,
  'permission' => [
    '@afformPageToken',
  ],
  'create_submission' => TRUE,
  'confirmation_type' => 'show_confirmation_message',
  'confirmation_message' => 'Thank you for updating your Gift Aid preferences.',
];
