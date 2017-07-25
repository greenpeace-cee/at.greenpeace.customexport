<?php

class CRM_Customexport_Base {

  protected $localFilePath; // Path where files are stored locally
  protected $settings; // Settings
  protected $_exportComplete = FALSE; // Set to TRUE when export has completed

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
}