=== Import WP – Export and Import CSV and XML files to WordPress ===
Contributors: jcollings,importwp  
Tags: import, csv, xml, importer, woocommerce, product import, post import, export, datafeed, bulk import, bulk export, schedule
Requires at least: 4.0  
Tested up to: 6.2  
Requires PHP: 5.6  
Stable tag: 2.7.14  
License: GPLv3  
License URI: http://www.gnu.org/licenses/gpl.html  
Donate link: https://www.importwp.com/

Easily Export and Import CSV files with our WordPress csv importer. Export and Import XML files. Import posts, categories, images, and custom fields.

== Description ==

Our optimised **WordPress importer** makes it easy to **export and import CSV and XML files** into WordPress posts, pages, categories, tags, custom post types, and custom taxonomies. We have simplified the process to **Import attachments, images, and any WordPress data** using our custom visual data selection tools that make it straightforward to import XML or CSV files from any **data feed**.

Our **WordPress exporter** allows you to **export CSV, XML or JSON files** with data from any wordpress post type or taxonomy, including exporting custom fields, images, attachments, and related terms. This means you can easily export CSV, XML or JSON files containing only the WordPress data and structure you need.

= Import CSV and XML files to WordPress posts, categories and users =

To create a **csv importer** or **xml importer** the steps are the same, except that xml files contain a nested data structure, instead of csv files that containing rows and headings.

1. **Choose Import file** - Import an XML or CSV file by either uploading a file, downloading from a remote url, or from a remote FTP.
2. **Map data** - Select what parts of your import file should be used to create and update a WordPress record.
3. **Set Permissions** - Set what is used to identify each record, and select what the importer can insert, update and delete.
4. **Run the importer**.

= Import WordPress Attachments from XML / CSV files =

**Import WordPress attachments** from CSV and XML files from either a **remote url**, an **ftp server**, the **websites filesystem**, or the **WordPress media library**. 

Attachments can be downloaded every time the importer runs, or can check the media library to **use an existing version** if it exists already, saving media from downloading duplicate images.

= WordPress CSV and XML Importer features =

* Simple XML data selection tool to Import XML files.
* Easy to use CSV data selection tool to import CSV files.
* Import data from remote urls and data feeds.
* Import data from an FTP server
* Import data from files stored on the web server.
* Import data to WordPress posts, pages and custom post types
* Import categories, tags and custom taxonomies.
* Import Attachments from Remote URL
* Import Attachments from  FTP server
* Import Attachments from local filesystem
* Import Attachments from Media Library.
* Import Attachment title, caption, alt tag metadata.
* Import custom fields

= WordPress Exporter features =

* Export all available fields, or select which WordPress data to export.
* Create nested XML files containing repeating data.
* Customise CSV file headings
* Customise XML node names and attribute labels.
* Filter which records are exported

= Product Import Export for WooCommerce Add-on =

The free [Import WP WooCommerce add-on extends](https://www.importwp.com/integrations/import-export-woocommerce-plugin/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) Import WP's XML and CSV import capabilities allowing to Export and Import WooCommerce products.

- Export WooCommerce products into CSV, XML or JSON files
- Import WooCommerce products from CSV and XML
- Import and Export simple, grouped, external, variable and variation products.
- Export and import WooCommerce products and images including featured product images and gallery images.
- Export and Import product categories including hierarchy.
- Export and Import WooCommerce product attributes, categories and tags
- Export and import product prices and stock levels.

A number of add-ons are available to add functionality to the importer / exporter.

* **Advanced Custom Fields ACF Add-on** - ACF XML & CSV data importer and exporter.
* **JetEngine Add-on** - JetEngine XML & CSV data importer and exporter.
* **WooCommerce Add-on** - XML & CSV Importer and Exporter for all WooCommerce product types.
* **Rank Math SEO Add-on** - XML & CSV Importer for Rank Math SEO metadata.
* **Yoast SEO Add-on** - XML & CSV Importer for Yoast SEO metadata.
* **Polylang Add-on** - XML & CSV Importer for Polylang translations.
* **BLM file importer Add-on** - BLM file and media Importer.

Find out more [about Import WP Add-ons](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting).

= What is Import WP Pro? =

Import WP Pro is a paid upgrade that includes premium support and adds the following extra features:

* Import data to custom fields - used by themes and plugins to store custom data associated with posts.
* Import data to WordPress custom post types.
* Import data to WordPress custom taxonomies
* Schedule importers

Find out more [about Import WP Pro on our website](https://www.importwp.com/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting).

== Installation ==

1. Upload 'jc-importer' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. A new menu item under Tools > Import WP should appear where you can access the plugin.

For further documentation on installing and using Import WP features can be found [here](https://www.importwp.com/documentation/).

== Frequently Asked Questions ==

= How to Import XML files using our XML Importer =

Our **XML importer** allows you to easily **import xml files** made up from any XML schema / structure. 

When **importing XML files** you first need to set the document base path, the XML base path defines what makes up a record and is used when calculating how many records will be imported.

Using our XML visual data selector you can choose what data to be imported from each record's XML nodes attributes or text, or you can manually select data using custom written XPath queries.

= How to Import CSV files using our CSV Importer =
Our **CSV importer** makes it possible to **import CSV files** containing any number of columns or rows, with settings to set the CSV delimiter character that is used to separate each data cells defaulting to a comma, set the CSV enclosure character that is used to wrap around each data cell defaulting to a quotation mark, and set what character encoding was used when creating the csv.

Using our CSV data selector you can visually choose what columns should be used when importing each record.

= What documentation is available =

Documentation can be found online on [importwp.com](https://www.importwp.com/docs/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting).

= What add-ons are available =

A full list of available addons can be found [here](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting).

== Screenshots ==

1. Import WP, New importer screen
2. Import WP, Post template setup screen
3. Import WP, Run import page
4. Import WP, Import history page

== Changelog ==

= 2.7.14 =

* FIX - Ftp username and passwords needed to be url encoded if they contain special characters.
* FIX - Add filter `iwp/ftp/passive_mode` to enable/disable ftp passive mode.

= 2.7.13 =

* FIX - Parsing of FTP connection strings when using Remote Url to download csv/xml files.

= 2.7.12 =

* FIX - Stop XML File reader parsing tags as xml in CDATA tags.
* FIX - Importer history date previously recording date as NaN... 
* FIX - Exporter status throwing DivisionByZeroError.
* FIX - Issue exporting User, Tax, and Comment Exports.

= 2.7.11 =

* FIX - Issue with ImporterRunner being passed to filter instead of Importer.

= 2.7.10 =

* ADD - Update Exporter and Importer Runner.

= 2.7.9 =

* FIX - Exporter issue caused by property access level.

= 2.7.8 =

* FIX - Changing delimiter or enclosure was previously not reindexing the temp config file.
* ADD - Allow csv exporter to add subrows.

= 2.7.7 =

* ADD - Add continue button to manually ran imports.
* ADD - field map ability to all fields, mapped field type has been deprecated.

= 2.7.6 =

* FIX - Importer PostMapper version tag breaking when using multiple post types.
* FIX - Issue with running importer not checking server limits.
* FIX - Issue with memory limit reading -1 as no memory, insead of unlimited.
* ADD - Add `iwp/importer/init` action when importer is being started.
* Add - Schedule exporter upgrade message.

= 2.7.5 =

* FIX - issue with reading memory_limit set using G suffix
* FIX - issue with post_date not setting post_date_gmt.

= 2.7.4 =

* FIX - issue with schedule interface, once saved you were not able to add new rows.
* ADD - Add filters to modify export query.
* ADD - Add helper function iwp_fn_prefix_items

= 2.7.3 =

* ADD - exported CSV files with default column names will autofill template fields when creating a new importer.
* FIX - fixed issue with addon data being cleared.
* FIX - fixed issue with importer status not being displayed correctly.

= 2.7.2 = 

* ADD - new delimiter field to attachments and taxonomy settings.
* FIX - fix UI speed issue.

= 2.7.1 = 

* FIX - Revert default exporter delimiter back to ","

= 2.7.0 =

* ADD - Extend exporter field select options with ability to rename and structure output.
* FIX - Multiline custom method matching.
* FIX - Fix missing wp_read_audio_metadata dependency when importing audio files.

= 2.6.5 =

* ADD - new helper function iwp_fn_get_posts_by.
* FIX - Add memory usage escape check, hard limit of 90%.

= 2.6.4 =

* FIX - Importer CSV file settings page disable flag not changing when fields are updated.
* ADD - Imported Record tracking is moved from meta tables to own table.
* ADD - Allow custom text entry on repeater base node field.

= 2.6.3 =

* FIX - Importer session has changed fatal importer error during import initialization.

= 2.6.2 =

* FIX - AddonBasePanel save callback was not following permission rules.
* FIX - PHP error caused by , at end of arrays. 
* FIX - Importer session has changed fatal importer error during import initialization.

= 2.6.1 =

* FIX - Importer state to work with WP Multisite.

= 2.6.0 =

* ADD - Simplified importer runner, import chunks removed in faviour of state locking.

= 2.5.5 =

* ADD - New action `iwp/importer/mapper/init` run before a record has been imported.
* ADD - New action `iwp/importer/mapper/before` to modify data before importing a record.
* ADD - New action `iwp/importer/mapper/before_insert` to modify data before inserting a record.
* ADD - New action `iwp/importer/mapper/before_update` to modify data before updating a record.
* ADD - New action `iwp/importer/mapper/after` run after a record has been imported.

= 2.5.4 =

* ADD - New filter `iwp/allowed_file_types` to allow different file types apart from the default xml / csv.
* ADD - New filter `iwp/get_filetype_from_ext` to allow setting the file type based on the attached file name.

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