# at.greenpeace.customexport

## Requirements
Requires phpseclib for sftp

## Webshop Export
Export all activities of type webshop to csv file, upload via sftp.

### Configuration
The extension ships with custom data in auto_install.xml and also adds an activity type in CRM_Customexport_Webshop::install().

The CiviCRM setting webshop_exports contains a json encoded array as follows:
[
'default' => [ 'file' => 'filename', 'remote' => 'sftp://user:pass@example.org/dir/']
'1' => [ 'file' => 'filename', 'remote' => 'sftp://user:pass@example.org/dir/']
'2' => ...
'order_type_id' => ..
]

* Any order_type_id which is not specified will use the default.

* sftp timeout is fixed at 30 seconds.  We may wish to make this a setting in future.

### Usage
Run the api function customexport.webshop

## Versandtool Export
This exports on a daily basis contact info for all contacts who are not marked do_not_email or user_opt_out

### Configuration
The CiviCRM setting versandtool_exports contains a json encoded array as follows:
[
'default' => [ 'file' => 'filename', 'remote' => 'sftp://user:pass@example.org/dir/']
]

### Usage
Run the api function customexport.versandtool or enable the daily cron job.

## WelcomePackageEmail Export
This exports on a daily basis contact info for all contacts who are not marked do_not_email or user_opt_out

### Settings
* welcomepackageemail_exports: (contains a json encoded array as follows)
[
'default' => [ 'file' => 'filename', 'remote' => 'sftp://user:pass@example.org/dir/']
]
* welcomepackageemail_campaign_externalidentifier: (a string, eg AKTION-7797)

### Usage
Run the api function customexport.welcomepackageemail or enable the daily cron job.

## WelcomePackagePost Export
This exports on a daily basis contact info for all contacts who are not marked do_not_email or user_opt_out

### Settings
* welcomepackagepost_exports: (contains a json encoded array as follows)
[
'default' => [ 'file' => 'filename', 'remote' => 'sftp://user:pass@example.org/dir/']
]
* welcomepackagepost_campaign_externalidentifier: (a string, eg AKTION-7797)

### Usage
Run the api function customexport.welcomepackagepost or enable the daily cron job.

# Updating SQL queries for welcomepackageemail,welcomepackagepost,versandtool
The following functions should be updated as necessary:

```function keys()```:

An array of all keys which should appear in the CSV file.  Each key must EXACTLY match a field that is selected in the final SQL statement.
* `contact_id` **MUST EXIST** for export activities to be created.

```function sqlFinalSelect()```:

This contains the final SQL select statement and should not change unless you are also changing the format of the CSV file.
It may contain variables such as @SET which take values from CiviCRM settings(eg. a campaign Id).

```function sql()```:

This contains the main SQL queries for generating the data.  When you need to update the SQL query just paste between the ```return "``` and ```";``` lines.

**Important notes:**

* Do not use ```"``` character anywhere in the SQL script - you may use ```'``` if required.
* Only use ```#``` character for SQL comments
* If you create tables, make sure the fields match the naming requirements in ```sqlFinalSelect()```/```keys()``` so you don't have to update those functions as well.
* Don't specify the database name in the SQL script (eg. this is not allowed: ```SELECT * FROM pro_civicrm.civicrm_contact```; This is correct: ```SELECT * FROM civicrm_contact```)
