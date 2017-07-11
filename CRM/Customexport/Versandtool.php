<?php

class CRM_Customexport_Versandtool extends CRM_Customexport_Base {

  private $contactsBatch = array(); // Array to hold batches of contacts

  private $batchSize; // Number of contacts in each batch (all batches output to the same csv file)
  private $batchOffset; // The current batch offset
  private $totalContacts; // The total number of contacts meeting criteria
  private $exportFile; // Details of the file used for export

  private $customFields;

  function __construct($batchSize = 100) {
    if (!$this->getExportSettings('versandtool_exports')) {
      throw new Exception('Could not load versandtoolExports settings - did you define a default value?');
    };
    $this->getCustomFields();
    $this->getLocalFilePath();

    $this->batchSize = $batchSize;
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
   * Export all contacts meeting criteria
   */
  public function export() {
    $this->totalContacts = $this->getContactCount();
    $this->batchOffset = 0;

    while ($this->batchOffset < $this->totalContacts) {
      // Export each batch to csv
      $this->_exportComplete = FALSE;
      if (!$this->getValidContacts($this->batchSize, $this->batchOffset)) {
        $result['is_error'] = TRUE;
        $result['message'] = 'No valid contacts found for export';
        return $result;
      }
      $this->exportToCSV();
      if (!$this->_exportComplete) {
        $result['is_error'] = TRUE;
        $result['message'] = 'Error during exportToCSV';
        return $result;
      }
      // Increment batch
      $this->batchOffset = $this->batchOffset + $this->batchSize;
    }
    
    // Once all batches exported:
    $this->upload();
  }

  /**
   * Get the count of all contacts meeting criteria
   *
   * @return bool
   */
  private function getContactCount() {
    $contactCount = civicrm_api3('Contact', 'getcount', array(
      'contact_type' => "Individual",
      'do_not_email' => 0,
      'is_opt_out' => 0,
    ));
    if (empty($contactCount['is_error'])) {
      return $contactCount['result'];
    }
    return FALSE;
  }

  /**
   * Get batch of contacts who are Individuals; do_not_email, user_opt_out is not set
   * Retrieve in batches for performance reasons
   * @param $limit
   * @param $offset
   *
   * @return bool
   */
  private function getValidContacts($limit, $offset) {
    $contacts = civicrm_api3('Contact', 'get', array(
      'contact_type' => "Individual",
      'options' => array('limit' => $limit, 'offset' => $offset),
      'do_not_email' => 0,
      'is_opt_out' => 0,
    ));

    if (empty($contacts['is_error']) && ($contacts['count'] > 0)) {
      $this->contactsBatch = $contacts['values'];
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
    if (!isset($this->settings['default']))
      return FALSE;

    $this->exportFile = $this->settings['default'];
    $this->exportFile['outfilename'] = $this->exportFile['file'] . '_' . $date->format('YmdHis'). '.csv';
    $this->exportFile['outfile'] = $this->localFilePath . '/' . $this->exportFile['outfilename'];
    $this->exportFile['hasContent'] = FALSE; // Set to TRUE once header is written

    foreach($this->contactsBatch as $id => $contact) {
      // Build an array of values for export
      // Required fields:
      // Kontakt-Hash;E-Mail;Salutation;Firstname;Lastname;Birthday;Title;ZIP;City;Country;Address;
      // Contact_ID;Telephone;PersonID_IMB;Package_id;Segment_id;Community NL;Donation Info;Campaign_Topic;Petition
      // CiviCRM Kontakt-Hash;Bulk E-Mail falls vorhanden, ansonsten Primäre E-Mail-Adresse;Prefix;First Name;Last Name;Date of Birth;Title;ZIP code (primary);City (primary);countyr code (primary;Street Address AND Supplemental Address (primary);
      // CiviCRM Contakt-ID;phone number (primary);The old IMB Contact ID – should be filled if contact has an external CiviCRM ID that starts with „IMB-“;to be ignored for daily/regular export;to be ignored for daily/regular export;Contact status (added, removed, none) of  Group „Community NL“;
      // Contact status (added, removed, none) of  Group „Donation Info “;fill with external campaign identifiers of the linked survey (linked via activity) (each value only once);fill with internal CiviCRM „Survey ID“ if any activity of the contact is connected to a survey  (each value only once)

      $fields = array(
        //'Kontakt-Hash' => // CiviCRM Kontakt-Hash
        //'E-Mail' => // Bulk E-Mail falls vorhanden, ansonsten Primäre E-Mail-Adresse
        'Salutation' => $contact['individual_prefix'], // Prefix
        'Firstname' => $contact['first_name'], // First Name
        'Lastname' => $contact['last_name'], // Last Name
        //'Birthday' => // Date of Birth
        'Title' => $contact['formal_title'], // Title
        'ZIP' => $contact['postal_code'], // ZIP code (primary)
        'city' => $contact['city'], // City (primary)
        'Country' => $contact['country'], // Country code (primary)
        //'Address' => // Street Address AND Supplemental Address (primary)
        'Contact_ID' => $id, // CiviCRM Contakt-ID
        //'Telephone' => // phone number (primary)
        //'PersonID_IMB' => // The old IMB Contact ID – should be filled if contact has an external CiviCRM ID that starts with „IMB-“
        'Package_id' => '', // to be ignored for daily/regular export
        'Segment_id' => '', // to be ignored for daily/regular export
        //'Community_ NL' => // Contact status (added, removed, none) of  Group „Community NL“
        //'Donation Info' => // Contact status (added, removed, none) of  Group „Donation Info “
        //'Campaign_Topic' => // fill with external campaign identifiers of the linked survey (linked via activity) (each value only once)
        //'Petition' => // fill with internal CiviCRM „Survey ID“ if any activity of the contact is connected to a survey  (each value only once)

        // Not required
        //'co' => $contact['current_employer'],
        //'strasse' => $contact['street_address'],
        //'state' => $contact['state_province'],
      );

      // Build the row
      $csv = implode(',', array_values($fields));

      // Write header on first line
      if (!$this->exportFile['hasContent']) {
        $header = implode(',', array_keys($fields));
        file_put_contents($this->exportFile['outfile'], $header.PHP_EOL, FILE_APPEND | LOCK_EX);
        $this->exportFile['hasContent'] = TRUE;
      }

      file_put_contents($this->exportFile['outfile'], $csv.PHP_EOL, FILE_APPEND | LOCK_EX);
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
      // Check if any data was found
      if (!$this->exportFile['hasContent']) {
        return FALSE;
      }

      // We have data, so upload the file
      $uploader = new CRM_Customexport_Upload($this->exportFile['outfile']);
      $uploader->setServer($this->exportFile['remote'] . $this->exportFile['outfilename'], TRUE);
      if ($uploader->upload() != 0) {
        $this->exportFile['uploaded'] = FALSE;
        $this->exportFile['uploadError'] = $uploader->getErrorMessage();
      }
      else {
        $this->exportFile['uploaded'] = TRUE;
      }
    }
  }
}
