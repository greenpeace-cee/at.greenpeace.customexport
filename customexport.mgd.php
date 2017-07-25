<?php
/*-------------------------------------------------------+
| SYSTOPIA - Custom Export for Greenpeace                |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: M. Wire (mjw@mjwconsult.co.uk)                 |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

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
