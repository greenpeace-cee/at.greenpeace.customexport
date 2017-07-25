<?php

abstract class CRM_Customexport_Base {

  protected $localFilePath; // Path where files are stored locally
  protected $settings; // Settings
  protected $_exportComplete = FALSE; // Set to TRUE when export has completed
  protected $exportFile; // Details of the file used for export
  protected $exportLines; // Lines for export
  protected static $CSV_SEPARATOR = ';'; // Separator for CSV file

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

  protected function stripSQLComments($sql) {
    // Commented version
    $sqlComments = '@
        (([\'"]).*?[^\\\]\2) # $1 : Skip single & double quoted expressions
        |(                   # $3 : Match comments
            (?:\#|--).*?$    # - Single line comments
            |                # - Multi line (nested) comments
             /\*             #   . comment open marker
                (?: [^/*]    #   . non comment-marker characters
                    |/(?!\*) #   . ! not a comment open
                    |\*(?!/) #   . ! not a comment close
                    |(?R)    #   . recursive case
                )*           #   . repeat eventually
            \*\/             #   . comment close marker
        )
        @msx';

    $uncommentedSQL = trim( preg_replace( $sqlComments, '$1', $sql ) );
    return $uncommentedSQL;
  }

  /**
   * Export the lines array to csv file
   */
  protected function exportToCSV() {
    foreach($this->exportLines as $id => $line) {

      // Build the row
      $csv = implode(self::$CSV_SEPARATOR, $line);

      // Write header on first line
      if (!$this->exportFile['hasContent']) {
        $header = implode(self::$CSV_SEPARATOR, $this->keys());
        file_put_contents($this->exportFile['outfile'], $header.PHP_EOL, FILE_APPEND | LOCK_EX);
        $this->exportFile['hasContent'] = TRUE;
      }

      file_put_contents($this->exportFile['outfile'], $csv.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
  }

  /**
   * Upload the given file using method (default sftp)
   *
   * @param string $method
   */
  protected function upload() {
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
    $this->upload();

    $return['is_error'] = $this->exportFile['uploadError'];
    $return['message'] = $this->exportFile['uploadErrorMessage'];
    $return['error_code'] = $this->exportFile['uploadErrorCode'];
    return $return;
  }

  /**
   * Run the query
   * @return bool
   */
  protected function doQuery() {
    $sql = $this->sql();

    if (is_array($sql)) {
      foreach ($sql as $query) {
        CRM_Core_Error::debug_log_message($sql);
        $dao = CRM_Core_DAO::executeQuery($query);
      }
    }
    else {
      CRM_Core_Error::debug_log_message($sql);
      $dao = CRM_Core_DAO::executeQuery($sql);
    }

    $dao->_get_keys();

    $keys = $this->keys();

    while ($dao->fetch()) {
      $line = (array) $dao;
      $newLine = array();
      foreach ($keys as $key) {
        $newLine[] = $line[$key];
      }
      $this->exportLines[] = $newLine;
    }

    return TRUE;
  }

  abstract protected function sql();
  abstract protected function keys();
}