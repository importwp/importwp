=== Import WP - Import and Export WordPress data to XML or CSV files ===
Contributors: jcollings  
Tags: wordpress csv import, wordpress xml import, wordpress csv export, wordpress xml export, csv, xml, bulk edit, migrate, wordpress importer, wordpress exporter, import xml file, import csv file, xml importer, csv importer, csv to wordpress, xml to wordpress
Requires at least: 4.0  
Tested up to: 6.0  
Requires PHP: 5.4  
Stable tag: 2.5.3  
License: GPLv3  
License URI: http://www.gnu.org/licenses/gpl.html  
Donate link: https://www.importwp.com/

Import WP is a powerful Importer & Exporter with a visual data selection tool that makes it easy to Export or Import any XML or CSV file to WordPress.

== Description ==

Import WP makes it easy to import any xml or csv files to WordPress, and export any wordpress data to xml, csv or json.

Import WP is a WordPress Importer and Exporter plugin that makes importing and exporting wordpress content easy, fast and straightforward.

Each import has been optimised to the following steps:

1. Attach an XML or CSV file.
2. Select each part of a WordPress post, page, category, taxonomy or user and mapping it to the file.
3. Set what is used to identify each record, and select what the importer can insert, update and delete.
4. Run the importer.

Import WP importer fully supports all core WordPress data types such as Posts, Pages, Attachments, Tags, Categories, and Users. This means you can easily import data from any xml or csv into all core WordPress data types using our data selection tool to visually map every field.

Each export is comprised of the following steps:

1. Select what WordPress fields should be exported.
2. Select the desired output format.
3. Run the exporter.

Import WP exporter allows you to export posts, pages, custom post types, attachment urls and details, tags, categories, custom taxonomies, users, and custom fields. This means you can easily generate csv, xml or json files containing only the data you need.

**What if you want to import xml or csv to custom post types, custom fields or custom taxonomies?**

[With the pro version of Import WP](https://www.importwp.com/pricing/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting), you can import xml or csv to any custom post types, custom fields or custom taxonomies created by themes or plugins.


**What is Pro?**

The Pro version adds the following extra features:

* Import any post type, taxonomy or user custom fields.
* Import WordPress custom post types.
* Import WordPress custom taxonomies
* Speed imports by importing chunks in parallel using the WordPress cron system.
* Schedule importers

Find out more [about Import WP Pro on our websie](https://www.importwp.com/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting).

== Installation ==

1. Upload 'jc-importer' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. A new menu item under Tools > Import WP should appear where you can access the plugin.

For further documentation on installing and using Import WP features can be found [here](https://www.importwp.com/documentation/).

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

1. Import WP, New importer screen
2. Import WP, Post template setup screen
3. Import WP, Run import page
4. Import WP, Import history page

== Changelog ==

= 2.5.3 =

* FIX - Addon Panel, Core template fields are no longer excluded from processing due to missing enabled flag.

= 2.5.2 = 

* FIX - Hash geneartion on local files now match correctly.
* ADD - Downloaded media without extensions, attempt to get extension from content-type header.

= 2.5.1 = 

* FIX - Restore old method of loading woocomerce / yoast addon
* FIX - Only the importer that deletes a record, can restore that record unless you use the post_status field.

= 2.5.0 =

* ADD - 1st release of `iwp_register_importer_addon` api
* ADD - File encoding dropdown added to xml imports under file settings step.
* FIX - Issue where 2 fields can match with the same prefix on a group e.g. gallery-id vs gallery-id-url.

= 2.4.10 =

* FIX - Custom methods no longer break when the character ")" is present.

= 2.4.9 =

* FIX - fix js load issue on some installs with ReferenceError: regeneratorRuntime is not defined.
* FIX - Add missing term description field to exporter.
* FIX - Term importer now displays list of private taxonomies. 
* FIX - Post taxonomies section hierachy follows stricter rules allowing for multiple terms with the same name if nested under a different parent e.g. "Term 1 > Term 1" 

= 2.4.8 =

* FIX - Update importer filter row functionality.

= 2.4.7 =

* FIX - Allow for zip, and gz file extensions to work with previously forced file extensions.
* ADD - Add new addon registration function `iwp_register_importer_addon` 

= 2.4.6 =

* ADD - New setting to limit the number of log files stored, by default this is set to unlimited.
* FIX - Fix RCE issue by forcing correct extensions on imported data files.

= 2.4.5 =

* ADD - New "Media Library" option added to attachment download field, allowing you to search for attachments previously uploaded to your media library.

= 2.4.4 = 

* ADD - Add filters to allow addons to extend the exporter custom field list and alter data before being exported.

= 2.4.3 =

* ADD - Add contains-in, not-contains-in importer filters.
* FIX - Fix contains and not contains filters.

= 2.4.2 =

* ADD - Edit name of importer by clicking the importers name on single importer screen.

= 2.4.1 =

* ADD - Attachment fields now have an option to fetch fresh images, instead of always using a found image from media library.
* ADD - export parent id, slug, and names for taxonomies.
* FIX - Tweak interface, and add upgrade notice to importer page.

= 2.4.0 =

* ADD - Added new exporter section to interface, allowing the exporting of wordpress data to XML / CSV / JSON.
* FIX - Fix upload error causing white screen.

= 2.3.0 =

* ADD - Add option to move posts / pages to trash instead of forceful deletion.
* ADD - Add `iwp/custom_field_label` filter to alter custom field label on importer log.
* ADD - Add data mapper and serialization tool.
* ADD - Add unique identifier field on permissions step for templates using user or post mapper. 

= 2.2.5 =

* ADD - Add `iwp/register_events` hook to allow extension of the event system.

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

* FIX - Post Deletion Vulnerability in Import WP.
* FIX - Add tool to reset Import WP database.
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

* FIX - Import WP XMLFile reader, stop xml parsing CDATA
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

* Rename plugin to Import WP
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