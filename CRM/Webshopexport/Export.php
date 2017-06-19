<?php

class CRM_Webshopexport_Export {

  private $_activities = array();
  protected $csvFilename;

  private $_exportComplete = FALSE; // Set to true once we've successfully exported
  private $_uploadComplete = FALSE; // Set to true once we've successfully uploaded the csv export

  /**
   * Export all webshop activities
   */
  public function export() {
    if (!$this->getValidActivities()) {
      $result['is_error'] = TRUE;
      $result['message'] = 'No valid activities found for export';
      return $result;
    }

    $this->exportToCSV();
    $this->upload();
    $this->setOrderExported();
    if ($this->_exportComplete && $this->_uploadComplete) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get array of all valid activities
   *
   * @return bool|array of activities
   */
  private function getValidActivities() {
    // Activity is valid when the following conditions are met:
    // Activity Type = "Webshop Order"
    // payment_received = Yes
    // order_exported = No

    $this->activityTypeId = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', webshopexport_activityName());
    $this->paymentReceivedField = civicrm_api3('CustomField', 'getsingle', array(
      'name' => "payment_received",
    ));
    $this->orderExportedField = civicrm_api3('CustomField', 'getsingle', array(
      'name' => "order_exported",
    ));

    $activities = civicrm_api3('Activity', 'get', array(
      'activity_type_id' => $this->activityTypeId,
      'custom_'.$this->paymentReceivedField['id'] => 1,
      'custom_'.$this->orderExportedField['id'] => 0,
    ));

    if (empty($activities['is_error']) && ($activities['count'] > 0)) {
      $this->_activities = $activities['values'];
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Export the activities array to csv file
   */
  private function exportToCSV() {
    // Write to a csv file in tmp dir
    $date = new DateTime();
    $file = sys_get_temp_dir() . '/webshopexport_' . $date->format('YmdHisu').'.csv';
    $this->csvFilename = $file;

    $csvHeader = 'id,date';
    file_put_contents($file, $csvHeader.PHP_EOL, FILE_APPEND | LOCK_EX);

    foreach($this->_activities as $id => $activity) {
      // Build fields string
      $csv = $id.','.$activity['activity_date_time'];
      file_put_contents($file, $csv.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    // Set to TRUE on successful export
    $this->_exportComplete = TRUE;
  }

  /**
   * Upload the given file using method (default sftp)
   *
   * @param string $method
   */
  private function upload($method='sftp') {
    if ($this->_exportComplete) {
      // TODO: Add upload code, and settings to save login etc?

      // Set to TRUE on success
      $this->_uploadComplete = TRUE;
    }
  }

  /**
   * Set all the activities that we exported to order_exported
   */
  private function setOrderExported() {
    // We set the order_exported for the activity once we get confirmation that the export/upload completed successfully.
    if ($this->_uploadComplete) {
      foreach ($this->_activities as $activity) {
        $params = $activity;
        $params['custom_' . $this->orderExportedField['id']] = 1;
        $activities = civicrm_api3('Activity', 'create', $params);
      }
      return TRUE;
    }
    else {
      return FALSE;
    }
  }
}
