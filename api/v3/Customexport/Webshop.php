<?php

/**
 * Webshop.Export API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_customexport_webshop_spec(&$spec) {
}

/**
 * Webshop.Export API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_customexport_webshop($params) {
  $exporter = new CRM_Customexport_Webshop();
  $result = $exporter->export();
  return civicrm_api3_create_success($result, $params, 'Customexport', 'Webshop');
}
