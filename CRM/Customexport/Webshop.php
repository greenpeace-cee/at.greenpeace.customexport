<?php

class CRM_Customexport_Webshop extends CRM_Customexport_Base {

  private $_activities = array();
  private $customFields;
  private $files;

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
  }

  function __construct($params = array()) {
    if (!$this->getExportSettings('webshop_exports')) {
      throw new Exception('Could not load webshopExports settings - did you define a default value?');
    };
    $this->getCustomFields();
    $this->getLocalFilePath();

    // params override setting, if param not specified read the setting
    if (!isset($params['create_activity'])) {
      $params['create_activity'] = CRM_Customexport_Utils::getSettings('webshop_create_export_activity');
    }
    if (!isset($params['export_activity_subject'])) {
      $params['export_activity_subject'] = CRM_Customexport_Utils::getSettings('webshop_export_activity_subject');
    }

    parent::__construct($params);
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
   * Export all webshop activities
   */
  public function export() {
    if (!$this->getValidActivities()) {
      $result['message'] = 'No valid activities found for export';
      return $result;
    }

    $this->exportToCSV();
    $this->upload();
    $this->setOrderExported();

    // Return all upload errors
    foreach ($this->files as $orderType => $file) {
      if ($file['hasContent']) {
        $return[$orderType]['order_type'] = $orderType;
        $return[$orderType]['is_error'] = isset($file['uploadError']) ? $file['uploadError'] : FALSE;
        $return[$orderType]['message'] = $file['uploadErrorMessage'];
        $return[$orderType]['error_code'] = $file['uploadErrorCode'];
        $return[$orderType]['count'] = $file['count'];
        $return[$orderType]['filename'] = $file['outfilename'];
      }
    }
    return $return;
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

    $this->activityTypeId = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', self::ACTIVITY_NAME);

    $activities = civicrm_api3('Activity', 'get', array(
      'activity_type_id' => $this->activityTypeId,
      'custom_'.$this->customFields['payment_received']['id'] => 1,
      'custom_'.$this->customFields['order_exported']['id'] => 0,
      'options' => array('limit' => 0),

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
  function exportToCSV() {
    // Write to a csv file in tmp dir
    $date = new DateTime();

    // order_type => optionvalue_id(order_type),
    // file => csv file name (eg. export),
    // remote => remote server (eg. sftp://user:pass@server.com/dir/)
    foreach ($this->settings as $orderType => $setting) {
      $this->files[$orderType] = $setting;
      $this->files[$orderType]['outfilename'] = $setting['file'] . '_' . $date->format('YmdHis'). '.csv';
      $this->files[$orderType]['outfile'] = $this->localFilePath . '/' . $this->files[$orderType]['outfilename'];
      $this->files[$orderType]['hasContent'] = FALSE; // Set to TRUE once header is written
      $this->files[$orderType]['count'] = 0;
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
      // Required fields:
      // ["id", "titel", "anrede", "vorname", "nachname", "co", "strasse", "plz", "ort", "postfach", "land", "zielgruppe ID",
      // "zielgruppe", "paket", "kundennummer", "sepa_belegart", "iban_empfaenger", "bic_empfaenger", "pruefziffer"]
      // "item", "anzahl_items", "date_of_order", "activity_description", "spendensumme"]

      $contact = $sourceContacts[$activity['source_contact_id']];
      $this->contact_ids[] = $contact['id'];

      $fields = array(
        'id' => $id,
        'titel' => $contact['formal_title'],
        'anrede' => $contact['individual_prefix'],
        'vorname' => $contact['first_name'],
        'nachname' => $contact['last_name'],
        'co' => $contact['current_employer'],
        'strasse' => $contact['street_address'],
        'state' => $contact['state_province'],
        'ort' => $contact['city'],
        'postfach' => $contact['postal_code'],
        'land' => $contact['country'],
        //'zeilgruppe_id' =>
        //'zielgruppe' =>
        //'paket' =>
        'kundennummer' => $this->formatKundennummer(isset($activity['campaign_id']) ? $activity['campaign_id'] : 0, $contact['id']),
        //'sepa_belegart' =>
        //'iban_empfaenger' =>
        //'bic_empfaenger' =>
        //'pruefziffer' =>
        'item' => CRM_Core_OptionGroup::getLabel('order_type', $activity['custom_' . $this->customFields['order_type']['id']]),
        'anzahl_items' => $activity['custom_' . $this->customFields['order_count']['id']],
        'tshirt_type' => $activity['custom_' . $this->customFields['shirt_type']['id']],
        'tshirt_size' => $activity['custom_' . $this->customFields['shirt_size']['id']],
        'date_of_order' => $activity['activity_date_time'],
        'activity_description' => $activity['details'],
        'contribution_id' => $activity['custom_' . $this->customFields['linked_contribution']['id']],
        'membership_id' => $activity['custom_' . $this->customFields['linked_membership']['id']],
        'multi_purpose' => $activity['custom_' . $this->customFields['multi_purpose']['id']],
        'order_type' => $activity['custom_' . $this->customFields['order_type']['id']], // Required for lookups prior to export
        'email' => $contact['email'],
      );

      // Get the correct output file
      if (isset($this->files[$fields['order_type']])) {
        $fileKey = $fields['order_type'];
      }
      else {
        $fileKey = 'default';
      }
      // Build the row
      $csv = implode(self::$CSV_SEPARATOR, array_values($fields));

      // Write header on first line
      if (!$this->files[$fileKey]['hasContent']) {
        $this->files[$fileKey]['fp'] = fopen($this->files[$fileKey]['outfile'], 'w');
        fputcsv($this->files[$fileKey]['fp'], array_keys($fields),self::$CSV_SEPARATOR);
        $this->files[$fileKey]['hasContent'] = TRUE;
      }

      fputcsv($this->files[$fileKey]['fp'], array_values($fields), self::$CSV_SEPARATOR);
      $this->files[$fileKey]['count']++;
    }

    // Close files
    foreach ($this->settings as $orderType => $setting) {
      //$this->files[$orderType] = $setting;
      if (!empty($this->files[$orderType]['fp'])) {
        fclose($this->files[$orderType]['fp']);
      }
    }
    // Set to TRUE on successful export
    $this->_exportComplete = TRUE;
  }

  function formatKundennummer($campaignId, $contactId) {
    return str_pad($campaignId, 4, '0', STR_PAD_LEFT)
      . 'C'
      . str_pad($contactId, 9, '0', STR_PAD_LEFT);
  }

  /**
   * Upload the given file using method (default sftp)
   *
   * @param string $method
   */
  function upload() {
    if ($this->_exportComplete) {
      // Upload each file in sequence.  If one fails record error and move on to the next one.
      foreach ($this->settings as $orderType => $setting) {
        $fileData = $this->files[$orderType];
        // Check if any data was found for each file type
        if (!$fileData['hasContent']) {
          continue;
        }

        // We have data, so upload the file
        // $fileData['outfile']; local file
        // $fileData['remote']; remote file
        $uploader = new CRM_Customexport_Upload($fileData['outfile']);
        $uploader->setServer($fileData['remote'] . $fileData['outfilename'], TRUE);

        $errorCode = $uploader->upload();
        $this->files[$orderType]['uploadErrorCode'] = $errorCode;
        if (!empty($errorCode)) {
          $this->files[$orderType]['uploadError'] = TRUE;
          $this->files[$orderType]['uploadErrorMessage'] = $uploader->getErrorMessage();
        }
        else {
          // Delete the local copy of the csv file
          unlink($this->files[$orderType]['outfile']);
          $this->files[$orderType]['uploadError'] = FALSE;
          $this->files[$orderType]['uploadErrorMessage'] = NULL;
        }
      }
    }
  }

  /**
   * Set all the activities that we exported to order_exported
   */
  private function setOrderExported() {
    // We set the order_exported for the activity once we get confirmation that the export/upload completed successfully.

    // Get date (now) for field order_exported_date (we can use the same date/time for each one)
    $date = new DateTime();
    $now = $date->format('Y-m-d H:i:s');
    // Get the upload status for each order type and put in an array for lookup later
    foreach ($this->settings as $orderType => $setting) {
      $orderUploaded[$orderType] = !$this->files[$orderType]['uploadError'];
    }

    foreach ($this->_activities as $activity) {
      $orderType = $activity['custom_' . $this->customFields['order_type']['id']];
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
        // Mark the order as exported and set the date
        $params = $activity;
        $params['custom_' . $this->customFields['order_exported']['id']] = 1;
        $params['custom_' . $this->customFields['order_exported_date']['id']] = $now;
        $params['status_id'] = 2; // Completed
        $activities = civicrm_api3('Activity', 'create', $params);

        // create an activity "Action"
        if (!empty($this->params['create_activity'])) {
          $this->createMassActivity();
        }
      }
    }
  }

  public function createMassActivity($activity_params = array()) {
    try {
      $campaign = civicrm_api3('Campaign', 'getsingle', array(
        'external_identifier' => 'Web_Shop',
        'return'              => 'title,id'));

      $activity_params = array(
        'status_id'        => 'Completed',
        'activity_type_id' => 'Action',
        'subject'          => $this->params['export_activity_subject'],
        'campaign_id'      => $campaign['id']);

      parent::createMassActivity($activity_params);
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Customexport - Problem creating activity: ' . $e->getMessage());
    }
  }
}
