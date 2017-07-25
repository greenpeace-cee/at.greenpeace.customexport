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