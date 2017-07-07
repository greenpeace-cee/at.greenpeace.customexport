# de.systopia.customexport

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