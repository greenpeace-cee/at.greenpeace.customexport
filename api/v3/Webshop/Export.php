<?php

/**
 * Webshop.Export API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_webshop_Export_spec(&$spec) {
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
function civicrm_api3_webshop_Export($params) {
  $exporter = new CRM_Webshopexport_Export();
  $result = $exporter->export();
  if (empty($result['is_error'])) {
    return civicrm_api3_create_success(1, $params, 'Webshop', 'Export');
  }
  else {
    if (!$result['message']) {
      $result['message'] = 'An error occurred during Webshop Export';
    }
    throw new API_Exception(/*errorMessage*/ $result['message'], /*errorCode*/ 1234);
  }
}
