<?php

class CRM_Customexport_WelcomepackageEmail extends CRM_Customexport_Base {

  function __construct($batchSize = NULL) {
    if (!$this->getExportSettings('welcomepackageemail_exports')) {
      throw new Exception('Could not load welcomepackagepostExports settings - did you define a default value?');
    };

    $this->getLocalFilePath();
  }

  public function export() {
    parent::export();
  }

  /**
   * The keys we need in the csv export.  These MUST exist in the sql select
   * @return array
   */
  function keys() {
    return array("contact_id");
  }

  /**
   * The actual array of queries
   */
  function sql() {
    /*# *******#
    #   OUT  #
    # *******#

    #Create basis table of contacts with first reduction:
    #OUT: deceased, deleted, do not email, no empty emailaddress*/
    $sql="DROP TABLE IF EXISTS temp_welcome;";
    $sql="
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome AS
	(
  SELECT DISTINCT c.id AS contact_id
        , 0 AS keep_contact
	FROM civicrm_contact c
		LEFT JOIN civicrm_email email ON email.contact_id=c.id  AND is_primary=1
	WHERE c.is_deceased=0 AND c.is_deleted=0
    AND c.do_not_email=0 AND c.is_opt_out=0 AND email.email IS NOT NULL
    );
    ";

    $sql="ALTER TABLE temp_welcome ADD PRIMARY KEY (contact_id);";

/*#Table with all contacts, which should not receive any welcome mail*/
    $sql="DROP TABLE IF EXISTS temp_welcome_delete;";
    $sql="
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_delete AS
	(
  SELECT DISTINCT contact_id
    FROM (
    #OUT: inaktiv, VIP, Firma, Schule
      SELECT DISTINCT et.entity_id AS contact_id
			FROM civicrm_entity_tag et
			LEFT JOIN civicrm_tag ct ON et.tag_id=ct.id
			WHERE et.entity_table = 'civicrm_contact' AND ct.name IN ('inaktiv','VIP')

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

            #OUT: Emailhistory in last 6 Months
            SELECT ac.contact_id
			FROM civicrm_activity AS activity
			LEFT JOIN civicrm_activity_contact AS ac ON ac.activity_id=activity.id
			LEFT JOIN civicrm_campaign AS campaign ON campaign.id=activity.campaign_id
			WHERE campaign.external_identifier='AKTION-7769' AND activity_date_time >= NOW()-INTERVAL 6 MONTH

            #OUT: Hard/Softbounces - not in Civi, yet! when implemented, then this has to be updated

		) AS delete_multiple_contacts
    );
    ";

    $sql="ALTER TABLE temp_welcome_delete ADD PRIMARY KEY (contact_id);";

/*#Delete all contacts from the welcome-list which were collected in the delete-table*/
    $sql="
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
    $sql="DROP TABLE IF EXISTS temp_welcome_keep;";
    $sql="
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_keep AS
	(
  SELECT DISTINCT contact_id
	FROM civicrm_membership AS m
	LEFT JOIN civicrm_membership_status AS ms ON m.status_id=ms.id
	LEFT JOIN civicrm_value_membership_payment AS mp ON mp.entity_id=m.id
	LEFT JOIN civicrm_option_value v ON v.value=mp.payment_instrument AND v.option_group_id=10
	WHERE ms.label IN ('Current','Paused') AND v.name IN ('RCUR','FRST')  AND join_date >= (NOW() - INTERVAL 6 MONTH) AND join_date < (NOW() - INTERVAL 2 DAY)
    );
    ";
    $sql="ALTER TABLE temp_welcome_keep ADD PRIMARY KEY (contact_id);";

/*#Mark the contacts, which should definetly be on the welcome list*/
    $sql="
UPDATE temp_welcome
SET  keep_contact=1
WHERE contact_id IN (SELECT contact_id FROM temp_welcome_keep);
    ";

/*# *******#
#   OUT  #
# *******#

#Final delete of all contacts, which are not marked to be kept*/
    $sql="
DELETE
FROM temp_welcome
WHERE keep_contact=0;
";

/*# ****************#
#   FINAL SELECT  #
# ****************#*/
    $sql="
SET @CiviCampaignID:= (SELECT id FROM civicrm_campaign
    WHERE external_identifier='AKTION-7769');
    ";

    $sql="
SELECT contact_id
FROM temp_welcome
    ";

    return $sql;
  }
}
