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

abstract class CRM_Customexport_Base {

  protected $params;
  protected $contact_ids;
  protected $localFilePath; // Path where files are stored locally
  protected $settings; // Settings
  protected $_exportComplete = FALSE; // Set to TRUE when export has completed
  protected $exportFile; // Details of the file used for export
  protected $exportLines; // Lines for export
  protected static $CSV_SEPARATOR = ';'; // Separator for CSV file


  function __construct($params = array()) {
    $this->contact_ids = array();
    $this->params = $params;
  }

  /**
   * Get the settings
   * This defines the list of files to export and where they should be sent
   */
  protected function getExportSettings($setting) {
    // This is an array of exports:
    // optionvalue_id(order_type) = array(
    //   file => csv file name (eg. export),
    //   remote => remote server (eg. sftp://user:pass@server.com/dir/)
    // )
    $this->settings = json_decode(CRM_Customexport_Utils::getSettings($setting), TRUE);
    foreach ($this->settings as $key => $data) {
      if ($key == 'default') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the local path for storing csv files
   */
  protected function getLocalFilePath() {
    $this->localFilePath = sys_get_temp_dir(); // FIXME: May want to change path
  }

  /**
   * Export the lines array to csv file
   */
  protected function exportToCSV() {
    $fp = fopen($this->exportFile['outfile'], 'w');
    foreach($this->exportLines as $id => $line) {

      // Write header on first line
      if (!$this->exportFile['hasContent']) {
        fputcsv($fp, $this->keys(), self::$CSV_SEPARATOR);
        $this->exportFile['hasContent'] = TRUE;
      }

      fputcsv($fp, $line, self::$CSV_SEPARATOR);
    }
    fclose($fp);
  }

  /**
   * create an activity connected to every exported contact
   * for this to work, the query used has to contain a contact_id field
   * Override to set $activity_params then call parent class
   */
  protected function createMassActivity($activity_params = array()) {
    if (!empty($this->contact_ids)) {
      $activity = civicrm_api3('Activity', 'create', $activity_params);
      $contact_id_list = implode(',', $this->contact_ids);

      // connect all contact_ids to the activity
      if (!empty($contact_id_list) && !empty($activity['id'])) {
        $query = "INSERT IGNORE INTO civicrm_activity_contact
                   (SELECT
                      NULL              AS id,
                      {$activity['id']} AS activity_id,
                      id                AS contact_id,
                      3                 AS record_type
                    FROM civicrm_contact
                    WHERE civicrm_contact.id IN ({$contact_id_list}))";
        CRM_Core_DAO::executeQuery($query);
      }
    }
  }

  /**
   * Upload the given file using method (default sftp)
   *
   * @param string $method
   * @return bool
   */
  protected function upload() {
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
      // Upload failed
      $this->exportFile['uploadError'] = TRUE;
      $this->exportFile['uploadErrorMessage'] = $uploader->getErrorMessage();
      return FALSE;
    }

    // Delete the local copy of the csv file
    unlink($this->exportFile['outfile']);
    $this->exportFile['uploadError'] = FALSE;
    $this->exportFile['uploadErrorMessage'] = NULL;
    return TRUE;
  }

  protected function configureOutputFile() {
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
   * Export all contacts meeting criteria
   */
  protected function export() {
    $this->configureOutputFile();

    $this->doQuery();

    $this->exportToCSV();
    // Once all batches exported:
    if ($this->upload() && !empty($this->params['create_activity'])) {
      $this->createMassActivity();
    }
    if ($this->exportFile['hasContent']) {
      $return['is_error'] = $this->exportFile['uploadError'];
      $return['message'] = $this->exportFile['uploadErrorMessage'];
      $return['error_code'] = $this->exportFile['uploadErrorCode'];
      $return['values']['count'] = count($this->exportLines);
    }
    else {
      $return['is_error'] = TRUE;
      $return['message'] = 'No data available for upload';
      $return['values'] = NULL;
    }
    return $return;
  }

  /**
   * Run the query
   * @return bool
   */
  protected function doQuery() {
    // Merge all sql statements together
    // We store them in different functions to make updates easier
    $sql = array_merge(explode(';', $this->sql()), explode(';', $this->sqlFinalSelect()));

    if (is_array($sql)) {
      foreach ($sql as $query) {
        $query = trim($query);
        if (!empty($query)) { // Explode may create some empty sql queries (just whitespace/newlines), don't try and run them
          try {
            $dao = CRM_Core_DAO::executeQuery($query);
          }
          catch (Exception $e) {
            throw new Exception("Query failed '".$e->getMessage()."'. Query: ".$query);
          }
        }
      }
    }
    else {
      CRM_Core_Error::debug_log_message($sql);
      $dao = CRM_Core_DAO::executeQuery($sql);
    }

    $dao->_get_keys();

    $keys = $this->keys();

    while ($dao->fetch()) {
      // TODO: avoid full DAO conversion, just access via $dao->$key
      $line = (array) $dao;
      $newLine = array();
      foreach ($keys as $key) {
        $newLine[] = $line[$key];
      }
      $this->exportLines[] = $newLine;

      // add contact IDs to the list
      if (isset($dao->contact_id)) {
        $this->contact_ids[] = (int) $dao->contact_id;
      }
    }

    return TRUE;
  }

  protected function keys() {}
  protected function sql() {}
  protected function sqlFinalSelect() {}
}