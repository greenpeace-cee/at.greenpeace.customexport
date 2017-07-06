<?php

class CRM_Customexport_Webshop {

  private $_activities = array();
  private $localFilePath; // Path where files are stored locally

  private $_exportComplete = FALSE; // Set to true once we've successfully exported
  private $settings;
  private $customFields;

  const ACTIVITY_NAME = 'Webshop Order';

  static function install() {
    // Create a "Webshop Order" activity type
    // See if we already have this type
    $activity = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => "activity_type",
      'name' => self::ACTIVITY_NAME,
    ));
    if (empty($activity['count'])) {
      $activityParams = array(
        'option_group_id' => "activity_type",
        'name' => self::ACTIVITY_NAME,
        'description' => self::ACTIVITY_NAME
      );
      $activityType = civicrm_api3('OptionValue', 'Create', $activityParams);
    }

    // Create example settings
    $webshopExports['default'] = array(
      'file' => 'default',
      'remote' => 'sftp://test:test@example.org/default/',
    );
    $webshopExports[1] = array(
      'file' => 'webshop1',
      'remote' => 'sftp://test1:test1@example.org/webshop1/'
    );
    CRM_Customexport_Utils::setSetting(json_encode($webshopExports), 'webshop_exports');
  }

  function __construct() {
    if (!$this->getExportSettings()) {
      throw new Exception('Could not load get webshopExports settings - did you define a default value?');
    };
    $this->getCustomFields();
    $this->localFilePath = sys_get_temp_dir(); // FIXME: May want to change path
  }

  /**
   * Get the metadata for all the custom fields in the group webshop_information
   */
  private function getCustomFields() {
    $customFields = civicrm_api3('CustomField', 'get', array(
      'custom_group_id' => "webshop_information",
    ));
    // Store by name so we can find them easily later
    foreach ($customFields['values'] as $key => $values) {
      $this->customFields[$values['name']] = $values;
    }
  }

  /**
   * Get the settings for webshopExports
   * This defines the list of files to export and where they should be sent
   */
  private function getExportSettings() {
    // This is an array of exports:
    // order_type => optionvalue_id(order_type) = array(
    //   file => csv file name (eg. export),
    //   remote => remote server (eg. sftp://user:pass@server.com/dir/)
    // )
    $this->settings = json_decode(CRM_Customexport_Utils::getSettings('webshop_exports'));
    foreach ($this->settings['order_type'] as $orderType) {
      if ($orderType == 'default') {
        return TRUE;
      }
    }
    return FALSE;
  }
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

    $activities = civicrm_api3('Activity', 'get', array(
      'activity_type_id' => $this->activityTypeId,
      'custom_'.$this->customFields['payment_received']['id'] => 1,
      'custom_'.$this->customFields['order_exported']['id'] => 0,
    ));

    if (empty($activities['is_error']) && ($activities['count'] > 0)) {
      $this->_activities = $activities['values'];
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Export the activities array to csv file
   * We export each order_type based on what we find in settings
   * If default is specified in settings we export all order_types that are not listed separately using this type.
   */
  private function exportToCSV() {
    // Write to a csv file in tmp dir
    $date = new DateTime();

    // order_type => optionvalue_id(order_type),
    // file => csv file name (eg. export),
    // remote => remote server (eg. sftp://user:pass@server.com/dir/)
    foreach ($this->settings as $setting) {
      $this->files[$setting['order_type']] = $setting;
      $this->files[$setting['order_type']]['outfilename'] = $setting['file'] . '_' . $date->format('YmdHisu'). '.csv';
      $this->files[$setting['order_type']]['outfile'] = $this->localFilePath . '/' . $this->files[$setting['order_type']]['outfilename'];


      $this->files[$setting['order_type']]['hasContent'] = FALSE; // Set to TRUE once header is written
    }

    // Load and cache all contact IDs before we export, so we don't do multiple lookups for the same contact.
    $sourceContacts = array();
    foreach($this->_activities as $id => $activity) {
      if (!array_key_exists($sourceContacts[$activity['source_contact_id']])) {
        $sourceContacts[$activity['source_contact_id']] = civicrm_api3('Contact', 'getsingle', array('id' => $activity['source_contact_id']));
      }
    }
    foreach($this->_activities as $id => $activity) {
      // Build an array of values for export
      $fields = array(
        'id' => $id,
        'date' => $activity['activity_date_time'],
        'order_type' => $activity['custom' . $this->customFields['order_type']['id']],
      );

      // Get the correct output file
      if (isset($this->files[$fields['order_type']])) {
        $file = $this->files[$fields['order_type']];
      }
      else {
        $file = $this->files['default'];
      }
      // Build the row
      $csv = implode(',', array_values($fields));

      // Write header on first line
      if (!$file['hasContent']) {
        $header = implode(',', array_keys($fields));
        file_put_contents($file['outfile'], $header.PHP_EOL, FILE_APPEND | LOCK_EX);
        $file['hasContent'] = TRUE;
      }

      file_put_contents($file['outfile'], $csv.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    // Set to TRUE on successful export
    $this->_exportComplete = TRUE;
  }

  /**
   * Upload the given file using method (default sftp)
   *
   * @param string $method
   */
  private function upload() {
    if ($this->_exportComplete) {
      // Upload each file in sequence.  If one fails record error and move on to the next one.
      foreach ($this->settings as $setting) {
        $fileData = $this->files[$setting['order_type']];
        // Check if any data was found for each file type
        if (!$fileData['hasContent']) {
          continue;
        }

        // We have data, so upload the file
        // $fileData['outfile']; local file
        // $fileData['remote']; remote file
        $uploader = new CRM_Customexport_Upload($fileData['outfile']);
        $uploader->setServer($fileData['remote'] . $fileData['outfilename']);
        if ($uploader->upload() != 0) {
          $this->files[$setting['order_type']]['uploaded'] = FALSE;
          $this->files[$setting['order_type']]['uploadError'] = $uploader->getErrorMessage();
        }
        else {
          $this->files[$setting['order_type']]['uploaded'] = TRUE;
        }
      }
    }

    if (isset($errors)) {
      $this->uploadErrors = $errors;
    }
  }

  /**
   * Set all the activities that we exported to order_exported
   */
  private function setOrderExported() {
    // We set the order_exported for the activity once we get confirmation that the export/upload completed successfully.

    // Get the upload status for each order type and put in an array for lookup later
    foreach ($this->settings as $setting) {
      $orderUploaded[$setting['order_type']] = $this->files[$setting['order_type']]['uploaded'];
    }

    foreach ($this->_activities as $activity) {
      $orderType = $activity['custom_'] . $this->customFields['order_type']['id'];
      // We need to see if the order_type has been uploaded or not
      $uploaded = FALSE;
      if (isset($orderUploaded[$orderType])) {
        // Has the specific order type been uploaded
        $uploaded = $orderUploaded[$orderType];
      }
      elseif (isset($orderUploaded['default'])) {
        // If the specific order type doesn't exist, it will have been uploaded using the default order_type
        $uploaded = $orderUploaded['default'];
      }
      if ($uploaded) {
        $params = $activity;
        $params['custom_' . $this->customFields['order_exported']['id']] = 1;
        $activities = civicrm_api3('Activity', 'create', $params);
      }
    }
  }

}
