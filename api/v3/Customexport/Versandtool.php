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
 * Versandtool.Export API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_customexport_versandtool_spec(&$spec) {
}

/**
 * Versandtool.Export API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_customexport_versandtool($params) {
  $exporter = new CRM_Customexport_Versandtool();
  $result = $exporter->export();
  if (empty($result['is_error'])) {
    return civicrm_api3_create_success($result['values'], $params, 'Customexport', 'Versandtool');
  }
  else {
    if (!$result['message']) {
      $result['message'] = 'An error occurred during Versandtool Export';
    }
    throw new API_Exception(/*errorMessage*/ $result['message'], /*errorCode*/ $result['error_code']);
  }
}
