=== ImportWP - Import any XML or CSV File into WordPress ===
Contributors: jcollings  
Tags: import, wordpress xml importer, wordpress csv importer, xml, csv
Requires at least: 4.0  
Tested up to: 5.7
Stable tag: 2.2.4
License: GPLv3  
License URI: http://www.gnu.org/licenses/gpl.html  
Donate link: https://www.importwp.com/

ImportWP is a powerful importer that allows you to import WordPress posts, pages, users and custom post types from any XML or CSV file.

== Description ==

ImportWP is an Advanced WordPress CSV/XML Importer, allowing you to easily drag and drop data into import templates. ImportWP has been built with developers in mind allowing import templates to be easily created and mapped to post_types, taxonomies, users, or tables.

= Features =

* Import from XML and CSV files.
* Import WordPress Users.
* Import WordPress Posts and Pages.
* Import WordPress Categories and Tags.
* Import WordPress Attachments.
* Permissions to control what can be imported.
* Import Custom Fields **(Requires Pro)**
* Import to Custom Post Types  **(Requires Pro)**
* Import to custom Taxonomies **(Requires Pro)**
* Schedule Imports  **(Requires Pro)**

ImportWP is a base for importing files into WordPress which can be extended upon to create custom templates and parsers specific to your needs, for more details [view the documentation](https://www.importwp.com/documentation/).

== Installation ==

1. Upload 'jc-importer' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. A new menu item under Tools > ImportWP should appear where you can access the plugin.

For further documentation on installing and using ImportWP features can be found [here](https://www.importwp.com/documentation/).

== Frequently Asked Questions ==

= How do i create an importer =

Documentation on adding an importer can be viewed [here](https://www.importwp.com/documentation/how-to-add-an-importer/)

= What settings does each importer have =

Documentation on using the importer settings can be found [here](https://www.importwp.com/documentation/importer-file-settings/)

= How to import from an XML/CSV files =

A guide to importing data from an xml file can be viewed [here](https://www.importwp.com/documentation/template-fields/)

= How do i run an importer once it is setup =

A guide to running and pausing an import can be viewed [here](https://www.importwp.com/documentation/run-import/)

== Screenshots ==

1. ImportWP, New importer screen
2. ImportWP, Post template setup screen
3. ImportWP, Run import page
4. ImportWP, Import history page

== Changelog ==

= 2.2.4 =

* FIX - Update custom method handler to work with xml with large amounts of whitespace. 

= 2.2.3 =

* FIX - Unable to create new importer due to null file settings merge.

= 2.2.2 = 

* FIX - Importer files are now prefixed with id-file_id-
* FIX - Downloading remote files now return not found, or empty file errors.

= 2.2.1 = 

* ADD - New setting field for file rotation
* FIX - Replace php data flushing with manual ajax fetching of importer status.
* FIX - Make it easier to select csv fields with empty values.
* FIX - Properly log skipped records
* FIX - Fixed issue on windows file uploads causing File Not Found Error.
* FIX - Allow '.' in field name when using permissions.
* FIX - Attempt to skip php output in ajax/rest response.

= 2.2.0 =

* ADD - Ability to split imports into sections/chunks.
* ADD - filter iwp/importer/chunk_size
* ADD - iwp/importer/datasource
* ADD - Ability to use custom methods when importing, [custom_fun("arg1", ...)]
* FIX - Fixed an issue with repeater sections displaying wrong data on front end.
* FIX - Fixed double serialization on term meta and user meta when updating records.

= 2.1.0 =

* ADD - Ability to filter records/rows before being imported.
* FIX - Fixed post_status bugs
* ADD - Ability to add multiple schedules per importer
* ADD - Ability to import/export importers.
* FIX - Fixed issue with cron scheduling, now uses local time
* ADD - new filter to override the default timeout length

= 2.0.23 =

* ADD - Update importer.custom_fields.process_field to return values
* ADD - Send xml / csv headers when downloading file
* FIX - Fix issue with duplicate nested xml tags prematurely closing record.
* FIX - File process now checks x amount of records when processing file for importer editor.
* FIX - Template fields are now fetched via REST, passing importer model to get_fields()

= 2.0.22 =
* ADD - Ability to download debug log on import screen.
* FIX - Enable field label uses Field label instead of key.

= 2.0.0 =

* ADD - Rewrite of PHP code to use namespaces, and WordPress Rest API.
* ADD - New User Interface, Moved plugin menu item from top level to under tools.
* ADD - Update frontend code to use ReactJS library.
* ADD - Debug panel on edit importer screen.
* ADD - Importer logs to Debug panel.
* ADD - Importer Settings base64 encoded string.
* FIX - Remove taxonomy field from term template, now pick taxonomy at start. 
* FIX - AJAX load in field options.
* FIX - Importer Speed increases.

= 1.1.8 =

* FIX - Permissions bug
* ADD - Uninstall method to clear database and files on plugin deletion (not deactivation).

= 1.1.7 =

* DEL - Remove unused custom curl methods for downloading attachments
* FIX - escape output data globally
* FIX - sanitize data globally
* FIX - Add nounce's to all ajax requests

= 1.1.6 =

* FIX - Post Deletion Vulnerability in ImportWP.
* FIX - Add tool to reset ImportWP database.
* FIX - Display errors when processing file fails.

= 1.1.5 =

* ADD - Visual Permission editor to filter fields to be imported per insert/update
* EDIT - Change csv column selector to show a vertical table with one record.

= 1.1.4 - 11/07/2019 =

* ADD - Fetch files via wp_remote_get instead of curl or file_get_contents
* ADD - Display information about custom field imports / errors
* FIX - post_parent field for page template displays correctly
* FIX - default value not being loaded when enabling field
* FIX - Fetch non cached row count when selecting xml base nodes

= 1.1.3 - 08/04/2019 =

* ADD - iwp/importer/file_uploaded action
* ADD - set file encoding via filter iwp/importer/file_encoding
* FIX - Change plugin support link
* FIX - include delimiter into record size calculations

= 1.1.2 - 16/03/2019 =

* ADD - store hash of imported attachments to compare against versions
* ADD - Add iwp/{FIELD_TYPE}_field, iwp/{FIELD_TYPE}/{FIELD_NAME} filters
* ADD - post date now tries to convert to correct format

= 1.1.1 - 22/01/2019 =

* FIX - Remove whitespace before importing remote attachments.
* FIX - Attachment Preview.
* FIX - Preview Error when importer has been first created.

= 1.1.0 - 16/01/2019 =

* FIX - ImportWP XMLFile reader, stop xml parsing CDATA
* ADD - Introduce new methods to make building templates easier
* EDIT - Re-Structure libs folder.

= 1.0.5 - 23/10/2018 =

* ADD - unique template field, set which field is used to identify a record
* FIX - Multiple bugs fixed.
* ADD - new filter 'iwp/import_mapper_permissions' to block fields from being modified
* ADD - new filter 'iwp/template_unique_fields' to override the templates unique reference field

= 1.0.4 - 16/10/2018 =

* ADD - Add aria-label to inputs.
* ADD - Add file index cache based on amount of rows chosen

= 1.0.3 - 09/10/2018 =

* FIX - Reduce memory load by streaming file indices.
* FIX - Speed up Post importer for larger files.
* FIX - Add Processing notification when file is added/updated.
* FIX - Remove templates fetching dropdown option values to edit importer screen only.

= 1.0.1 - 28/09/2018 =

* FIX - multiple XML import issues
* FIX - import display to show error total
* FIX - Preview block to hide loading text if no value

= 1.0 - 29/04/2018 =

* Integrated new and improved XML and CSV importers

= 0.7 - 04/02/2018 =

* Add - tooltips ot xml base node fields, Slightly simplify XML process by hiding group base nodes.
* Fix - Run link on importer archive screen would not start new csv import if one had completed previously.
* Fix - Change links in about block

= 0.6 - 22/08/2017 =

* Optimise CSV Parser

= 0.5 =

* Add tooltips to title, slug, content and excerpt fields.
* Add the ability to import attachments from a local folder.
* Fix - Issue with php7.1.1 and saving of template data throwing warning for type missmatch string to array.

= 0.4.1 - 08/06/2017 =

* Fix admin redirect when uploading new file on edit screen
* Add filter to change template name when importer is created: jci/importer/template_name

= 0.4 - 20/05/2017 =

* Add filter to alter field data: jci/parse_csv_field and jci/parse_xml_field
* Add filter to alter specific field data: jci/parse_csv_field/{{field_name}} and jci/parse_xml_field/{{field_name}}
* UI Improvements 

= 0.3.1 - 11/04/2017 =

* Change Interfaces
* Change upgrade notices

= 0.3 - 26/03/2017 =

* Rename plugin to ImportWP
* Fix Broken unit tests, make it work with WP 4.7.3
* Simplify Creation Process

= 0.2 =

* fix wrong user version importer tag when adding
* allow addition of user meta values (add/edit)

= 0.1.9 =

* Improve create importer screen. 
* fix misspelled version variable causing warnings

= 0.1.8 =

* Fix get_groups() issue in mapper

= 0.1.7 =

* Update XML node selector modal window

= 0.1.6 =

* set default options to field dropdown list
* skip empty attachments, and taxonomies
* move preview record box next to fields

= 0.1.5 =

* Disable attachment check when switching importer file from list due to empty results
* Add XMLReader library for creating xml node and element selectors

= 0.1.4 =

* Switch saving importer files as attachments to custom post type, stopping filling media library with files
* Clear current import status on new file upload globally

= 0.1.3 =

* Add file session storage for CSV import
* Save session between imports to keep track of file pointer position
* Allow multiple records to be imported per ajax request

= 0.1.2 =

* Improved import speed
* Fixed attachment import error message
* Added importer permissions to add screen

= 0.1.1 =

* Allow page authors to use username, or ID
* Fix post author and post name
* Add Tax Template and Mapper

= 0.1 =

* JC Importer