<?php

class CRM_Customexport_Base {

  protected $localFilePath; // Path where files are stored locally
  protected $settings; // Settings

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

}