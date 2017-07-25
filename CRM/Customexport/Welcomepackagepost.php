<?php

class CRM_Customexport_WelcomepackagePost extends CRM_Customexport_Base {

  private $exportFile; // Details of the file used for export

  function __construct($batchSize = NULL) {
    if (!$this->getExportSettings('welcomepackagepost_exports')) {
      throw new Exception('Could not load welcomepackagepostExports settings - did you define a default value?');
    };

    $this->getLocalFilePath();
  }

  /**
   * Export all contacts meeting criteria
   */
  public function export() {
    $this->configureOutputFile();

    $this->doQuery();
    return;

    // Once all batches exported:
    $this->upload();

    $return['is_error'] = $this->exportFile['uploadError'];
    $return['message'] = $this->exportFile['uploadErrorMessage'];
    $return['error_code'] = $this->exportFile['uploadErrorCode'];
    return $return;
  }

  /**
   * Get batch of contacts who are Individuals; do_not_email, user_opt_out is not set
   * Retrieve in batches for performance reasons
   * @param $limit
   * @param $offset
   *
   * @return bool
   */
  private function doQuery() {
    $sql = $this->sql();
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      _civicrm_api3_object_to_array($dao,$values);
      CRM_Core_Error::debug_log_message('values:'.print_r($values,TRUE));
      break;
    }
    return TRUE;
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

  /**
   * The actual query
   */
  private function sql() {
    return "
# *******#
#   OUT  #
# *******#

#Create basis table of contacts with first reduction:
#OUT: deceased, deleted, do not mail, not AT, empty address, Retourenzähler >=2
DROP TABLE IF EXISTS temp_welcome;
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome AS 
	(
	SELECT DISTINCT c.id AS contact_id
        , 0 AS keep_contact
	FROM civicrm_contact c
		LEFT JOIN civicrm_address address 		ON address.contact_id=c.id AND is_primary=1
		LEFT JOIN civicrm_value_address_statistics address_stat ON address_stat.entity_id=address.id 
		LEFT JOIN civicrm_country ctry 			ON address.country_id=ctry.id
	WHERE c.is_deceased=0 AND c.is_deleted=0  			
		AND c.do_not_mail=0 AND ctry.iso_code='AT' AND address.street_address IS NOT NULL AND address_stat.rts_counter <2  	 
    );

ALTER TABLE temp_welcome ADD PRIMARY KEY (contact_id);

#Table with all contacts, which should not receive any welcome mail
DROP TABLE IF EXISTS temp_welcome_delete;
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_delete AS 
	(
	SELECT DISTINCT contact_id
    FROM (	
			#OUT: inaktiv, VIP, Firma, Schule 
			SELECT DISTINCT et.entity_id AS contact_id
			FROM civicrm_entity_tag et 		
			LEFT JOIN civicrm_tag ct ON et.tag_id=ct.id 
			WHERE et.entity_table = 'civicrm_contact' AND ct.name IN ('inaktiv','VIP','Firma','Schule') 
			
			UNION ALL
            
			#OUT: Erblasser
			SELECT DISTINCT group_c.contact_id
			FROM civicrm_group_contact group_c 
			LEFT JOIN civicrm_group g ON g.id=group_c.group_id
			WHERE g.title='Erblasser'
			
			UNION ALL
			
            #OUT: Major Donors
			SELECT cb.contact_id
			FROM civicrm_contribution AS cb 
			WHERE receive_date >= NOW() - INTERVAL 1 YEAR
			GROUP BY cb.contact_id
			HAVING SUM(cb.total_amount)>=1000
            
            UNION ALL
            
            #OUT: Mailhistory in last 6 Months
            SELECT ac.contact_id 
			FROM civicrm_activity AS activity
			LEFT JOIN civicrm_activity_contact AS ac ON ac.activity_id=activity.id
			LEFT JOIN civicrm_campaign AS campaign ON campaign.id=activity.campaign_id
			WHERE campaign.external_identifier='AKTION-7767' AND activity_date_time >= NOW()-INTERVAL 6 MONTH
            
            
		) AS delete_multiple_contacts
    );
    
ALTER TABLE temp_welcome_delete ADD PRIMARY KEY (contact_id);

#Delete all contacts from the welcome-list which were collected in the delete-table    
DELETE
FROM temp_welcome 
WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete)
;

# *******#
#   IN   #
# *******#

#Table with all contacts, that have to be kept and should receive a welcome mail
#IN: Membership Current/ Paused, Sepa RCUR / FRST, join date in last 6 months
DROP TABLE IF EXISTS temp_welcome_keep;
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_keep AS 
	(
    SELECT DISTINCT contact_id
	FROM civicrm_membership AS m
	LEFT JOIN civicrm_membership_status AS ms ON m.status_id=ms.id
	LEFT JOIN civicrm_value_membership_payment AS mp ON mp.entity_id=m.id
	LEFT JOIN civicrm_option_value v ON v.value=mp.payment_instrument AND v.option_group_id=10
	WHERE ms.label IN ('Current','Paused') AND v.name IN ('RCUR','FRST')  AND join_date >= (NOW() - INTERVAL 6 MONTH) AND join_date < (NOW() - INTERVAL 2 DAY)
    );
    
ALTER TABLE temp_welcome_keep ADD PRIMARY KEY (contact_id);

#Mark the contacts, which should definetly be on the welcome list
UPDATE temp_welcome 
SET  keep_contact=1
WHERE contact_id IN (SELECT contact_id FROM temp_welcome_keep);

# *******#
#   OUT  #
# *******#

#Final delete of all contacts, which are not marked to be kept
DELETE
FROM temp_welcome 
WHERE keep_contact=0;

# ****************#
#   FINAL SELECT  #
# ****************#

SET @CiviCampaignID:= (SELECT id FROM civicrm_campaign
    WHERE external_identifier='AKTION-7767');
    
#Output for CSV File
#\"id\", \"titel\", \"anrede\", \"vorname\", \"nachname\", \"co\", \"strasse\", \"plz\", \"ort\", \"postfach\", \"land\", \"kundennummer\" 
SELECT 	w.contact_id 			AS id
		,formal_title 			AS titel     
		, v.label 				AS anrede      
        , c.first_name 			AS vorname
        , c.last_name 			AS nachname
		, NULL 					AS co
        , address.street_address AS strasse
        , address.postal_code 	AS plz
		, address.city 			AS ort
        , NULL 					AS postfach
        , ctry.iso_code 		AS land
        , CONCAT(@CiviCampaignID,'C',LPAD(w.contact_id, 9, '0')) 
								AS kundennummer
FROM temp_welcome w
	LEFT JOIN civicrm_contact c 			ON c.id=w.contact_id
	LEFT JOIN civicrm_address address 		ON address.contact_id=c.id AND is_primary=1
	LEFT JOIN civicrm_value_address_statistics address_stat ON address_stat.entity_id=address.id 
	LEFT JOIN civicrm_country ctry 			ON address.country_id=ctry.id
  LEFT JOIN civicrm_option_value v 		ON v.value=c.prefix_id AND v.option_group_id=6
    ";
  }
}
