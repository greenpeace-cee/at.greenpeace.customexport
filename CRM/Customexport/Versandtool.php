<?php

class CRM_Customexport_Versandtool extends CRM_Customexport_Base {

  private $contactsBatch = array(); // Array to hold batches of contacts

  private $batchSize; // Number of contacts in each batch (all batches output to the same csv file)
  private $batchOffset; // The current batch offset
  private $totalContacts; // The total number of contacts meeting criteria
  private $exportFile; // Details of the file used for export

  function __construct($batchSize = NULL) {
    if (!$this->getExportSettings('versandtool_exports')) {
      throw new Exception('Could not load versandtoolExports settings - did you define a default value?');
    };
    if (!isset($batchSize)) {
      $this->batchSize = CRM_Customexport_Utils::getSettings('versandtool_batchsize');
    }

    $this->getLocalFilePath();
  }

  /**
   * Export all contacts meeting criteria
   */
  public function export() {
    $this->totalContacts = $this->getContactCount();
    $this->batchOffset = 1;

    $this->configureOutputFile();

    while ($this->batchOffset < $this->totalContacts) {
      // Export each batch to csv
      $this->_exportComplete = FALSE;
      $starttime = microtime(true);
      if (!$this->getValidContacts($this->batchSize, $this->batchOffset)) {
        $result['is_error'] = TRUE;
        $result['message'] = 'No valid contacts found for export';
        return $result;
      }
      $time_elapsed_secs = microtime(true) - $starttime; //DEBUG
      CRM_Core_Error::debug_log_message('contact exec time: '.$time_elapsed_secs); //DEBUG
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

    $return['is_error'] = $this->exportFile['uploadError'];
    $return['message'] = $this->exportFile['uploadErrorMessage'];
    $return['error_code'] = $this->exportFile['uploadErrorCode'];
    return $return;
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
    return $contactCount;
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
      'return' => 'individual_prefix,first_name,last_name,birth_date,formal_title,postal_code,city,country,external_identifier',
    ));

    if (empty($contacts['is_error']) && ($contacts['count'] > 0)) {
      $this->contactsBatch = $contacts['values'];
      return TRUE;
    }
    return FALSE;
  }

  private function configureOutputFile() {
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
  }

  /**
   * Export the activities array to csv file
   * We export each order_type based on what we find in settings
   * If default is specified in settings we export all order_types that are not listed separately using this type.
   */
  private function exportToCSV() {
    $startContactId = $this->batchOffset;
    $endContactId = $this->batchSize+$this->batchOffset;

    $starttime = microtime(true);
    $emails = $this->getBulkEmailAddresses($startContactId, $endContactId);
    $time_elapsed_secs = microtime(true) - $starttime; //DEBUG
    CRM_Core_Error::debug_log_message('email exec time: '.$time_elapsed_secs); //DEBUG
    $starttime = microtime(true);
    $addresses = $this->getPrimaryAddresses($startContactId, $endContactId);
    $time_elapsed_secs = microtime(true) - $starttime; //DEBUG
    CRM_Core_Error::debug_log_message('address exec time: '.$time_elapsed_secs); //DEBUG
    $starttime = microtime(true);
    $phones = $this->getPrimaryPhones($startContactId, $endContactId);
    $time_elapsed_secs = microtime(true) - $starttime; //DEBUG
    CRM_Core_Error::debug_log_message('phone exec time: '.$time_elapsed_secs); //DEBUG
    $starttime = microtime(true);
    $groupC = $this->getContactGroupStatus($startContactId, $endContactId,'Community NL');
    $time_elapsed_secs = microtime(true) - $starttime; //DEBUG
    CRM_Core_Error::debug_log_message('groupnl exec time: '.$time_elapsed_secs); //DEBUG
    $starttime = microtime(true);
    $groupD = $this->getContactGroupStatus($startContactId, $endContactId,'Donation Info');
    $time_elapsed_secs = microtime(true) - $starttime; //DEBUG
    CRM_Core_Error::debug_log_message('groupdon exec time: '.$time_elapsed_secs); //DEBUG
    $starttime = microtime(true);
    $this->filterExternalContactIds($this->contactsBatch);
    $time_elapsed_secs = microtime(true) - $starttime; //DEBUG
    CRM_Core_Error::debug_log_message('filter exec time: '.$time_elapsed_secs); //DEBUG
    $starttime = microtime(true);
    $surveys = $this->getContactSurveys($startContactId, $endContactId);
    $time_elapsed_secs = microtime(true) - $starttime; //DEBUG
    CRM_Core_Error::debug_log_message('surv exec time: '.$time_elapsed_secs); //DEBUG


    foreach($this->contactsBatch as $id => $contact) {
      // Build an array of values for export
      // Required fields:
      // Kontakt-Hash;E-Mail;Salutation;Firstname;Lastname;Birthday;Title;ZIP;City;Country;Address;
      // Contact_ID;Telephone;PersonID_IMB;Package_id;Segment_id;Community NL;Donation Info;Campaign_Topic;Petition
      // CiviCRM Kontakt-Hash;Bulk E-Mail falls vorhanden, ansonsten Primäre E-Mail-Adresse;Prefix;First Name;Last Name;Date of Birth;Title;ZIP code (primary);City (primary);countyr code (primary;Street Address AND Supplemental Address (primary);
      // CiviCRM Contakt-ID;phone number (primary);The old IMB Contact ID – should be filled if contact has an external CiviCRM ID that starts with „IMB-“;to be ignored for daily/regular export;to be ignored for daily/regular export;Contact status (added, removed, none) of  Group „Community NL“;
      // Contact status (added, removed, none) of  Group „Donation Info “;fill with external campaign identifiers of the linked survey (linked via activity) (each value only once);fill with internal CiviCRM „Survey ID“ if any activity of the contact is connected to a survey  (each value only once)

      $fields = array(
        'Kontakt-Hash' => CRM_Contact_BAO_Contact_Utils::generateChecksum($id), // CiviCRM Kontakt-Hash
        'E-Mail' => $emails[$id], // Bulk E-Mail falls vorhanden, ansonsten Primäre E-Mail-Adresse
        'Salutation' => $contact['individual_prefix'], // Prefix
        'Firstname' => $contact['first_name'], // First Name
        'Lastname' => $contact['last_name'], // Last Name
        'Birthday' => $contact['birth_date'], // Date of Birth
        'Title' => $contact['formal_title'], // Title
        'ZIP' => $contact['postal_code'], // ZIP code (primary)
        'City' => $contact['city'], // City (primary)
        'Country' => $contact['country'], // Country code (primary)
        'Address' => $addresses[$id], // Street Address AND Supplemental Address (primary)
        'Contact_ID' => $id, // CiviCRM Contakt-ID
        'Telephone' => $phones[$id], // phone number (primary)
        'PersonID_IMB' => $contact['external_identifier'], // The old IMB Contact ID – should be filled if contact has an external CiviCRM ID that starts with „IMB-“
        'Package_id' => '', // to be ignored for daily/regular export
        'Segment_id' => '', // to be ignored for daily/regular export
        'Community_ NL' => $groupC[$id], // Contact status (added, removed, none) of  Group „Community NL“
        'Donation Info' => $groupD[$id], // Contact status (added, removed, none) of  Group „Donation Info “
        'Campaign_Topic' => $surveys[$id]['external_identifier'], // fill with external campaign identifiers of the linked survey (linked via activity) (each value only once)
        'Petition' => $surveys[$id]['survey_id'], // fill with internal CiviCRM „Survey ID“ if any activity of the contact is connected to a survey  (each value only once)
      );

      // Build the row
      $csv = implode(';', array_values($fields));

      // Write header on first line
      if (!$this->exportFile['hasContent']) {
        $header = implode(';', array_keys($fields));
        file_put_contents($this->exportFile['outfile'], $header.PHP_EOL, FILE_APPEND | LOCK_EX);
        $this->exportFile['hasContent'] = TRUE;
      }

      file_put_contents($this->exportFile['outfile'], $csv.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    // Set to TRUE on successful export
    $this->_exportComplete = TRUE;
  }

  /**
   * Returns an array of [contact_id]=>email
   * @param $startContactId
   * @param $endContactId
   *
   * @return array
   */
  private function getBulkEmailAddresses($startContactId, $endContactId) {
    // Get list of email addresses for contact
    // We sort by is_bulkmail and then is_primary so we don't have to search the whole array,
    //  as we can just match the first one
    $emails = civicrm_api3('Email', 'get', array(
      'contact_id' => array('BETWEEN' => array($startContactId, $endContactId)),
      'options' => array('sort' => "contact_id ASC", 'limit' => 0),
      'return' => 'contact_id,is_bulkmail,is_primary,email',
    ));

    $emailData = array();
    if ($emails['count'] > 0) {
      // As we sorted by is_bulkmail and then is_primary the first record will always be the one we want
      $contactId = 0;
      foreach ($emails['values'] as $id => $email) {
        // Each contact has multiple emails, we sorted by contact Id so check each email for contact
        if ($email['contact_id'] != $contactId) {
          // If contact doesn't match we're looking at a new contact
          $contactId = $email['contact_id'];
          $bulkFound=FALSE;
          $primaryFound=FALSE;
        };
        if (!empty($email['is_bulkmail'])) {
          // If we have a bulkmail address use it
          $bulkFound = TRUE;
          $emailData[$email['contact_id']] = $email['email'];
        }
        if (!empty($email['is_primary']) && !$bulkFound) {
          // If we don't have a bulkmail address set to primary
          $primaryFound = TRUE;
          $emailData[$email['contact_id']] = $email['email'];
        }
        if (!$bulkFound && !$primaryFound) {
          // Set this as the email address, will get overwritten if primary or bulkmail is set.
          $emailData[$email['contact_id']] = $email['email'];
        }
      }
    }
    return $emailData;
  }

  /**
   * Returns an array of [contact_id]=>phone
   * @param $startContactId
   * @param $endContactId
   *
   * @return array
   */
  private function getPrimaryPhones($startContactId, $endContactId) {
    $phoneData = array();
    $phones = civicrm_api3('Phone', 'get', array(
      'contact_id' => array('BETWEEN' => array($startContactId, $endContactId)),
      'is_primary' => 1,
      'options' => array('limit' => 0),
      'return' => 'contact_id,phone',
    ));

    if ($phones['count'] > 0) {
      foreach ($phones['values'] as $id => $phone) {
        $phoneData[$phone['contact_id']] = $phone['phone'];
      }
    }
    return $phoneData;
  }

  /**
   * Returns an array of [contact_id]=>address(street_address,sup1,sup2)
   * @param $startContactId
   * @param $endContactId
   *
   * @return array
   */
  private function getPrimaryAddresses($startContactId, $endContactId) {
    // Get list of postal addresses for contact.
    // We sort by is_primary so we can just match the first one
    $addresses = civicrm_api3('Address', 'get', array(
      'contact_id' => array('BETWEEN' => array($startContactId, $endContactId)),
      'is_primary' => 1,
      'options' => array('limit' => 0),
      'return' => "street_address,supplemental_address_1,supplemental_address_2",
    ));
    $addressData = array();
    if ($addresses['count'] > 0) {
      foreach ($addresses['values'] as $id => $address) {
        $newAddress = $address['street_address'];
        // Append supplemental address fields separated by commas if defined
        if (!empty($address['supplemental_address_1'])) {
          $newAddress = $address . ', ' . $address['supplemental_address_1'];
        }
        if (!empty($address['supplemental_address_2'])) {
          $newAddress = $address . ', ' . $address['supplemental_address_2'];
        }
        $addressData[$address['contact_id']] = $newAddress;
      }
    }
    return $addressData;
  }

  /**
   * Filter the external identifier if it starts with "IMB-", if not set to ''
   * @param $contacts array
   *
   * @return string
   */
  private function filterExternalContactIds(&$contacts) {
    foreach ($contacts as $id => $data) {
      if (substr($data['external_identifier'], 0, 4) != 'IMB-') {
        $contacts['id']['external_identifier'] = NULL;
      }
    }
  }

  /**
   * Returns an array of [contact_id]=>group status (eg. Added)
   * @param $startContactId
   * @param $endContactId
   * @param $groupName
   *
   * @return array
   */
  private function getContactGroupStatus($startContactId, $endContactId, $groupName) {
    // Get the group Id
    $group = civicrm_api3('Group', 'get', array(
      'name' => $groupName,
      'options' => array('limit' => 1),
    ));
    $groups = array();
    if ($group['count'] > 0) {
      $sql="
SELECT contact_id,status FROM `civicrm_group_contact` gcon 
WHERE gcon.contact_id BETWEEN %1 AND %2 AND gcon.group_id=%3";
      $params[1] = array($startContactId, 'Integer');
      $params[2] = array($endContactId, 'Integer');
      $params[3] = array($group['id'], 'Integer');
      $dao = CRM_Core_DAO::executeQuery($sql,$params);
      while ($dao-fetch()) {
        $groups[$dao->contact_id] = $dao->status;
      }
    }
    return $groups;
  }

  /**
   * Returns an array of [contact_id]=>(external_identifier=>campaign_extid1,campaign_extid2.., survey_id=>surveyid1,surveyid2..)
   * @param $startContactId
   * @param $endContactId
   *
   * @return array
   */
  private function getContactSurveys($startContactId,$endContactId) {
    //'Campaign_Topic' => // fill with external campaign identifiers of the linked survey (linked via activity) (each value only once)
    //'Petition' => // fill with internal CiviCRM „Survey ID“ if any activity of the contact is connected to a survey  (each value only once)
    $surveys = array();
    $sql="
SELECT GROUP_CONCAT(DISTINCT acamp.external_identifier) AS external_identifier,GROUP_CONCAT(DISTINCT act.source_record_id) as survey_id,acon.contact_id 
  FROM `civicrm_activity` act 
LEFT JOIN `civicrm_activity_contact` acon ON act.id=acon.activity_id 
LEFT JOIN `civicrm_campaign` acamp ON act.campaign_id=acamp.id 
WHERE act.activity_type_id=28 AND acon.record_type_id=3 AND acon.contact_id BETWEEN %1 AND %2 
GROUP BY acon.contact_id";
    $params[1] = array($startContactId, 'Integer');
    $params[2] = array($endContactId, 'Integer');
    $dao = CRM_Core_DAO::executeQuery($sql,$params);
    while ($dao->fetch()) {
      $surveys[$dao->contact_id] = array(
        'external_identifier' => $dao->external_identifier,
        'survey_id' => $dao->survey_id,
      );
    }
    return $surveys;
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
      $errorCode = $uploader->upload();
      $this->exportFile['uploadErrorCode'] = $errorCode;
      if (!empty($errorCode)) {
        $this->exportFile['uploadError'] = TRUE;
        $this->exportFile['uploadErrorMessage'] = $uploader->getErrorMessage();
      }
      else {
        // Delete the local copy of the csv file
        unlink($this->exportFile['outfile']);
        $this->exportFile['uploadError'] = FALSE;
        $this->exportFile['uploadErrorMessage'] = NULL;
      }
    }
  }
}
