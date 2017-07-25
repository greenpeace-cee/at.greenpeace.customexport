<?php

class CRM_Customexport_WelcomepackagePost extends CRM_Customexport_Base {

  function __construct($batchSize = NULL) {
    if (!$this->getExportSettings('welcomepackagepost_exports')) {
      throw new Exception('Could not load welcomepackagepostExports settings - did you define a default value?');
    };

    $this->getLocalFilePath();
  }

  public function export() {
    return parent::export();
  }

  /**
   * The keys we need in the csv export.  These MUST exist in the sql select
   * @return array
   */
  function keys() {
    return array("id", "titel", "anrede", "vorname", "nachname", "co", "strasse", "plz", "ort", "postfach", "land", "kundennummer");
  }

  /**
   * The actual array of queries
   */
  function sql() {
/*# *******#
#   OUT  #
# *******#

#Create basis table of contacts with first reduction:
#OUT: deceased, deleted, do not mail, not AT, empty address, Retourenzähler >=2 */
    $sql[] = "DROP TABLE IF EXISTS temp_welcome;";
    $sql[] = "
CREATE TABLE IF NOT EXISTS temp_welcome AS 
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
    ";
    $sql[] = "ALTER TABLE temp_welcome ADD PRIMARY KEY (contact_id);";

/*#Table with all contacts, which should not receive any welcome mail*/
    $sql[] = "DROP TABLE IF EXISTS temp_welcome_delete;";
    $sql[] = "
CREATE TABLE IF NOT EXISTS temp_welcome_delete AS 
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
    ";

    $sql[] = "ALTER TABLE temp_welcome_delete ADD PRIMARY KEY (contact_id);";

/*#Delete all contacts from the welcome-list which were collected in the delete-table*/
    $sql[] = "
DELETE
FROM temp_welcome 
WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete)
;
    ";

/*# *******#
#   IN   #
# *******#

#Table with all contacts, that have to be kept and should receive a welcome mail
#IN: Membership Current/ Paused, Sepa RCUR / FRST, join date in last 6 months*/
    $sql[] = "DROP TABLE IF EXISTS temp_welcome_keep;";
    $sql[] = "
CREATE TABLE IF NOT EXISTS temp_welcome_keep AS 
	(
    SELECT DISTINCT contact_id
	FROM civicrm_membership AS m
	LEFT JOIN civicrm_membership_status AS ms ON m.status_id=ms.id
	LEFT JOIN civicrm_value_membership_payment AS mp ON mp.entity_id=m.id
	LEFT JOIN civicrm_option_value v ON v.value=mp.payment_instrument AND v.option_group_id=10
	WHERE ms.label IN ('Current','Paused') AND v.name IN ('RCUR','FRST')  AND join_date >= (NOW() - INTERVAL 6 MONTH) AND join_date < (NOW() - INTERVAL 2 DAY)
    );
    ";

    $sql[] = "ALTER TABLE temp_welcome_keep ADD PRIMARY KEY (contact_id);";

/*#Mark the contacts, which should definetly be on the welcome list*/
    $sql[] = "
UPDATE temp_welcome 
SET  keep_contact=1
WHERE contact_id IN (SELECT contact_id FROM temp_welcome_keep);
    ";

/*# *******#
#   OUT  #
# *******#

#Final delete of all contacts, which are not marked to be kept*/
    $sql[] = "
DELETE
FROM temp_welcome 
WHERE keep_contact=0;
";

/*# ****************#
#   FINAL SELECT  #
# ****************#*/
    $sql[] = "
SET @CiviCampaignID:= (SELECT id FROM civicrm_campaign
    WHERE external_identifier='AKTION-7767');
    ";
    
/*#Output for CSV File
#\"id\", \"titel\", \"anrede\", \"vorname\", \"nachname\", \"co\", \"strasse\", \"plz\", \"ort\", \"postfach\", \"land\", \"kundennummer\"*/
    $sql[] = "
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
  LEFT JOIN civicrm_option_value v 		ON v.value=c.prefix_id AND v.option_group_id=6;
    ";
    return $sql;
  }
}
