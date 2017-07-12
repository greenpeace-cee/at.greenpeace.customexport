<?php
/**
 * The record will be automatically inserted, updated, or deleted from the
 * database as appropriate. For more details, see "hook_civicrm_managed" at:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/Hook+Reference
 */
return array(
  0 => array (
    'name' => 'Cron:CustomExport.versandtool_export',
    'entity' => 'Job',
    'params' => array (
      'version' => 3,
      'name' => 'Export data for versandtool',
      'description' => 'Versandtool export.',
      'run_frequency' => 'Daily',
      'api_entity' => 'Customexport',
      'api_action' => 'versandtool',
      'parameters' => '',
    ),
  ),
);
