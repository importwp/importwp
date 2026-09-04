=== Import WP – CSV & XML Import Export for WordPress ===
Contributors: jcollings  
Tags: csv xml importer, export csv xml, google sheets import, import from ftp, import users csv
Requires at least: 4.0  
Tested up to: 7.1  
Requires PHP: 5.6  
Stable tag: 2.14.25  
License: GPLv3  
License URI: http://www.gnu.org/licenses/gpl.html  
Donate link: https://www.importwp.com/

Import and export WordPress posts, pages, users and media from any CSV or XML file, URL, FTP or Google Sheets — with visual field mapping.

== Description ==

Import WP makes it straightforward to **import and export WordPress data** from CSV and XML files — no fixed file layout required. Map any column or XML node to WordPress fields with a visual selector, then run the import.

Bring files in from a local upload, remote URL, data feed, FTP server, or Google Sheets. Export back out to CSV, XML, or JSON when you need a clean data dump or a migration bundle.

_This is a fantastic plugin. It allows an incredible amount of flexibility but the best part about it is the support, each question I had was answered near instantly and any problems were addressed and fixed immediately. I am impressed. I highly recommend the pro version, it is a relatively small cost for a really helpful tool. — WordPress.org review by nzstefan_

= How to import CSV or XML into WordPress =

1. **Choose your file** — Upload a CSV/XML file, or fetch it from a remote URL, FTP, or Google Sheets.
2. **Map your data** — Use the visual selector to connect file fields to WordPress posts, pages, categories, tags, users, or attachments.
3. **Set permissions** — Choose how records are matched, and whether the importer can insert, update, or delete.
4. **Run the importer** — Review the log and history when the run finishes.

= Import from URL, FTP, data feeds and Google Sheets =

Import WP is built for recurring and remote data sources, not only one-off uploads:

* Import CSV and XML from a remote URL or data feed
* Import from an FTP server
* Import from Google Sheets (via a published / downloadable sheet URL)
* Import from files already on your web server
* Skip unchanged records to speed up repeat imports

= Export WordPress data to CSV, XML or JSON =

The built-in **WordPress exporter** lets you export the fields you need:

* Export posts, pages, taxonomies and related data
* Export custom fields, images, attachments and terms
* Export to CSV, XML or JSON
* Customise CSV headings and XML node / attribute names
* Create nested XML for repeating data
* Filter which records are included in the export

= Import WordPress attachments and media =

Import images and other attachments from CSV or XML:

* Remote URL
* FTP server
* Local filesystem
* WordPress media library (reuse existing files to avoid duplicates)
* Attachment title, caption, alt text and description

= Free version features =

* Visual CSV and XML field mapping
* Import posts, pages, categories, tags, users and attachments
* Import from upload, URL, FTP, server path or Google Sheets
* Export to CSV, XML or JSON
* Unique identifiers for insert / update matching
* Insert, update and delete controls per import
* Import history and logging

= Free add-ons =

Extend Import WP with free integrations:

* [WooCommerce](https://www.importwp.com/integrations/import-export-woocommerce-plugin/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) — import and export products, variations, images, attributes, categories, prices and stock
* [Yoast SEO](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) and [Rank Math SEO](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) — import SEO metadata
* [Polylang](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) and [WPML](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) — import translations
* [BLM file importer](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) — property / BLM feeds

See all [Import WP add-ons](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting).

= WooCommerce product import and export =

With the free WooCommerce add-on you can:

* Import and export simple, grouped, external, variable and variation products
* Import and export product images (featured and gallery)
* Import and export categories (including hierarchy), attributes and tags
* Import and export prices and stock levels
* Export products to CSV, XML or JSON

= Import WP Pro =

[Import WP Pro](https://www.importwp.com/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) adds premium support plus:

* Import to custom fields
* Import to custom post types
* Import to custom taxonomies
* Schedule importers
* [Advanced Custom Fields (ACF) add-on](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) — import and export ACF fields (requires Pro)
* [JetEngine add-on](https://www.importwp.com/integrations/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) — import and export JetEngine data (requires Pro)

A flexible, well-supported alternative when you need CSV/XML import and export without enterprise pricing.

== Installation ==

1. Upload the `jc-importer` folder to the `/wp-content/plugins/` directory, or install via **Plugins → Add New**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Open **Tools → Import WP** to create an importer or exporter.

Full documentation: [importwp.com/documentation](https://www.importwp.com/documentation/).

== Frequently Asked Questions ==

= How do I import a CSV file into WordPress? =

Create a new importer, upload your CSV (or load it from a URL / FTP / Google Sheets), map each column to the WordPress fields you need, set how records should be matched, then run the import. You can set the CSV delimiter, enclosure and character encoding under file settings.

= How do I import an XML file into WordPress? =

Create a new importer and choose your XML file. Set the document base path so Import WP knows what counts as one record, then map XML nodes or attributes with the visual selector (or use XPath). Any XML structure is supported — the file does not need a fixed schema.

= Can I import from Google Sheets? =

Yes. Publish or share the sheet so it can be downloaded as CSV/XML, then use the remote URL option in Import WP to fetch it.

= Can I import from FTP or a remote data feed? =

Yes. Import WP can pull CSV or XML files from an FTP server, a remote URL, or a data feed, as well as from files already on your server.

= What WordPress data can I import? =

The free plugin imports posts, pages, categories, tags, users and attachments (including image metadata). Custom post types, custom taxonomies and custom fields are available in [Import WP Pro](https://www.importwp.com/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting). Free add-ons extend imports for WooCommerce, Yoast, Rank Math, Polylang and more. ACF and JetEngine add-ons require Import WP Pro.

= Can I export WordPress data to CSV, XML or JSON? =

Yes. The exporter is included in the free plugin. Choose the post type or taxonomy, pick the fields to include, optionally filter records, and export to CSV, XML or JSON.

= Does Import WP work with WooCommerce? =

Yes, via the free [WooCommerce add-on](https://www.importwp.com/integrations/import-export-woocommerce-plugin/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting). You can import and export products, variations, images, attributes, categories, prices and stock.

= What is the difference between free and Pro? =

Free covers core WordPress types (posts, pages, taxonomies, users, attachments), CSV/XML import from multiple sources, and CSV/XML/JSON export. Pro adds custom fields, custom post types, custom taxonomies, scheduled imports, premium support, and unlocks the ACF and JetEngine add-ons.

= Where is the documentation? =

Documentation is on [importwp.com](https://www.importwp.com/docs/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting).

= How do I get support? =

Use the [WordPress.org support forum](https://wordpress.org/support/plugin/jc-importer/) for the free plugin. Pro customers receive premium support via [importwp.com](https://www.importwp.com/?utm_campaign=Import%2BWP%2BPro%2BUpgrade&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting).

== Screenshots ==

1. Create a new importer — choose CSV or XML and your data source
2. Map file fields to WordPress posts, pages, users or taxonomies
3. Run the import and monitor progress
4. Review import history and logs

== Changelog ==

= 2.15.0 =

* ADD - JSON file importer with record base path selection, nested field mapping, and preview support.
* ADD - Allow JSON exports to be used when setting up a new importer from an exporter.
* FIX - allow for 0 to be used as a reference as empty cleared it.

= 2.14.25 =

* ADD - Improve admin UI error handling with a root error boundary, shared AJAX error formatting, and debug-gated console logging.
* FIX - Serve debug log downloads through an authenticated admin request so the button works after uploads/importwp was locked down.

= 2.14.24 =

* FIX - Prevent Importers page crash when an importer is left in a running state with an empty section.
* FIX - Save importer field maps as a packed JSON payload so large custom field setups are not limited by PHP max_input_vars.
* FIX - Collapse repeatable template rows by default when many rows are present, improving UI performance with large custom field lists.
* FIX - Store importer files relative to the uploads directory and remap legacy absolute paths, preventing open_basedir warnings after site path changes.

= 2.14.23 =

* FIX - unauthenticated download of admin export files researched by Muni Nitish Kumar Yaddala.

= 2.14.22 =

* FIX - local file import restriction bypass reported by CyberKareem.

= 2.14.21 =

* ADD - new filter `iwp/regenerate_response_filename_ext` to force unnamed downloaded attachments to add file extension based of content type.

= 2.14.20 =

* FIX - Fix issue when importer enabled fields on term template did not clear data if the field was empty.

= 2.14.19 =

* FIX - Force using filepath when creating htaccess and temp directories.

= 2.14.18 =

* FIX - Fix CVE-2025-12894 vulnerability reported by Wordfence, by restricting access to debug log files, session data, and export files.
* FIX - Update local media attachment finder to use complete match over partial matches.

= 2.14.17 =

* ADD - Extend addon api to make handling group data easier.
* FIX - Fix CVE-2025-12137 vulnerability reported by Wordfence, local file imports that are fetched from outside the wordpress directory now require to be whitelisted using the `iwp/importer/local_file/allowed_directories` filter.
* ADD - Add permission fields for attachment meta data (title, caption, alt, description).

= 2.14.16 =

* FIX - Fixed issue with Exporter modal not always showing download button once complete.

= 2.14.15 =

* FIX - default unique identifier to template.

= 2.14.14 =

* FIX - XML Namespaces now handled properly in record chunks, instead of setting them previously to false.

= 2.14.13 =

* ADD - Add option to remove imported media when an importer deletes an item.

= 2.14.12 =

* FIX - When importing empty post terms with append set to no, will now remove existing terms.

= 2.14.11 = 

* FIX - When importing multisite users that already exist on another site, add them to the current site.
* FIX - run migrations on all multisite sites.

= 2.14.10 =

* ADD - Add filter `iwp/importer/template/process_attachment/resize` to allow resizing of downloaded attachments before they are imported.
* ADD - Add filter `iwp/template/process_attachment/enable_file_size_hash` to compare file size with remote attachments instead of just the filename.

= 2.14.9 =

* ADD - Add dismissible notices to dashboard, plugins, and importer screen alerting users to available addons.
* FIX - Notices not display correctly on import wp screen.

= 2.14.8 =

* ADD - Add `iwp/mapper/session_importer_ids` filter to allow grouping of importers during delete.
* FIX - Fix issue when importer config data is corrupt and cant be unserialized.
* FIX - Issue relating to certain CDATA not being ignored correctly.

= 2.14.7 =

* FIX - Fix activation error caused when mbstring module is not installed.

= 2.14.6 =

* FIX - Add security recommendations for CVE-2024-13562 to prevent unauthenticated sensitive information being leaked via access to the raw importer csv/xml files as reported by Tim Coen via WordFence.

= 2.14.5 = 

* ADD - Add iwp/http/remote_get_args filter to override wp_remote_get importer args

= 2.14.4 =

* FIX - bulk update post taxonomy terms, instead of individual wp_set_object_terms calls.

= 2.14.3 =

* FIX - searching for files in media library returns the best matched filename instead of the first matched.
* FIX - Add blm file to allowed of files contained in zip archives.
* FIX - update term export template to export parent fields, instead of relying on the ancestor column.
* ADD - Add option to prepend UTF-8 BOM to csv exports.

= 2.14.2 =

* ADD - Add `iwp/ftp/disable_size_check` filter to allow compatibility with FTP servers returning file size of -1.

= 2.14.1 =

* ADD - AddonAPI before_row receives new $data argument.
* ADD - Expand something went wrong screen to show js error.
* FIX - Update some class references in comments.

= 2.14.0 =

* FIX - PHP Deprecated notice: Implicit conversion from float to int loses precision
* ADD - Add new hooks to trigger before and after a record has been imported.
* FIX - Update export filter, to force the selected field to return a string.
* FIX - Display message when trying to download via ftp without the ftp PHP extension enabled.
* FIX - Fix UI error cause when an empty condition is set in the importer template.
* FIX - Update continue banner to display when the importer has the status: timeout.
* FIX - Capture previously missing errors in status file.
* ADD - Add new addon api via ImporterAddon or ExporterAddon.

= 2.13.5 =

* ADD - Switch attachment to extend post template, allowing for importing related taxonomies.
* FIX - Rename comment parent field type label.

= 2.13.4 =

* ADD - Include the phpseclib to reduce the server requirements of sftp transfers

= 2.13.3 =

* FIX - Undefined file type error when importing XML/CSV from local zip file.

= 2.13.2 =

* FIX - Add extra data sanitization to imported importer configs via Settings / Tools > Import / Export.

= 2.13.1 =

* ADD - Added option to skip importing of data when records have not changed.
* FIX - Fixed SSRF vulnerability issue that was only exploitable by administrators.

= 2.13.0 =

* ADD - Add option on importer create screen to create from existing exporter.
* ADD - Add option on importer create screen to create from exporter config.
* ADD - Display unique identifier on importer log messages.
* ADD - Add csv file settings to exporter (escape, delimiter, and separator).
* ADD - Add csv escape character field to importer.
* ADD - Option when importing taxonomy terms onto a post type, to search via custom field.
* ADD - Add `iwp/importer/template/post_create_term` filter to disable creation of terms when importing taxonomies onto a post type.
* FIX - Update importer / exporter temp path to use wp_upload_dir instead of WP_CONTENT_DIR.
* FIX - Fix enable field dropdown from overflowing screen.
* FIX - Page Header and Footer responsiveness.

= 2.12.0 =

* ADD - New unique identifier UI, allowing you to select from template fields or create a custom identifier. 
* ADD - Add filter to enable custom delete actions
* ADD - Add action to override default delete behavior with custom code.
* ADD - Add post taxonomy field labelled Hierarchy relationship, allowing you to choose from connecting all to just last term.

= 2.11.8 =

* FIX - Fix issue TypeError: count() in xml parser.

= 2.11.7 =

* FIX - Fix issue causing csv exporter to show empty screen after clicking "Add fields".

= 2.11.6 =

* ADD - Allow for addons to register panel settings, currently only supports toggle fields.
* FIX - Fix error if id passed to attachment is not of an attachment type.
* FIX - Reduce database calls during import.

= 2.11.5 =

* ADD - Add pot translation file.
* ADD - Add mapper exist query filters.
* ADD - Add post template term filter.
* FIX - Importing Post parent field, if searching by name make sure it is not an empty value.

= 2.11.4 =

* ADD - Add missing `iwp/exporter/user/fields` filter when generating user exporter field list.

= 2.11.3 =

* FIX - remove status ajax request during manual import, instead add max record cap per request.
* FIX - fix layer ordering issue where wp footer was in front of import interface.
* FIX - If importing a remote attachment, if it starts with the uploads url search media library first.

= 2.11.2 =

* ADD - Add exporter rest points to compatibility module.
* FIX - Add second argument to `iwp/exporter/user/setup_data` filter, to avoid conflict with ACF module in Pro.

= 2.11.1 =

* FIX - fix missing error when an importer is unable to download the source file.

= 2.11.0 =

* ADD - Add compatibility panel do attempt to disable plugins during import process.
* FIX - Reduce plugin header size.

= 2.10.1 =

* ADD - Add documentation links to each section heading.
* FIX - Add tooltip to unique identifier field.

= 2.10.0 =

* FIX - Reduce database calls during import.
* ADD - during import define WP_IMPORTING flag.

= 2.9.1 =

* ADD - Enable post_status field by default.

= 2.9.0 =

* ADD - Add new Permission Field user interface.
* FIX - Issue with deleting objects when template uses multiple post types.

= 2.8.3 =

* FIX - unique identifier field now correctly display's previously selected custom values.

= 2.8.2 =

* FIX - Improve Exporter speed by lazy loading only what is needed.

= 2.8.1 =

* FIX - Error displayed when removing custom field during loading of its data.
* ADD - Show unique identifier field for term import template.
* ADD - Allow for term perents to reference a custom field.
* FIX - Normalize exporter file path to fix failed download of file on windows.

= 2.8.0 =

* FIX - Issue with parsing xml file swith duplicate node names.
* ADD - Allow importing of attachments from zip files using 'iwp_zip'.
* ADD - New Attachment template allows you to import and update attachment files and meta data.
* ADD - New url field when exporting attachment post type, contains full url of file.
* ADD - New term type field on post template.
* ADD - Merge zip archive import code into core plugin.
* ADD - Attachments can now be imported from the main zip file by setting the attachment base url/path to iwp_zip.
* FIX - Reduce plugin size by using @wordpress/scripts.
* ADD - New import setting to automatically download new files before being manually run.
* ADD - New Comment import template.

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