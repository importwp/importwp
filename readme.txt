=== ImportWP - Import any XML or CSV File into WordPress ===
Contributors: jcollings  
Tags: import, wordpress xml importer, wordpress csv importer, xml, csv
Requires at least: 4.0  
Tested up to: 4.9
Stable tag: 1.0.5-alpha.2
License: GPLv3  
License URI: http://www.gnu.org/licenses/gpl.html  
Donate link: https://www.importwp.com/

ImportWP is a powerful importer that allows you to import WordPress posts, pages, users and custom post types from any XML or CSV file.

== Description ==

ImportWP is an Advanced Wordpress CSV/XML Importer, allowing you to easily drag and drop data into import templates. ImportWP has been built with developers in mind allowing import templates to be easily created and mapped to post_types, taxonomies, users, or tables.

= Features =

* Import from XML and CSV files.
* Import WordPress Users.
* Import WordPress Posts.
* Import WordPress Pages.
* Import WordPress Taxonomies.
* Import WordPress Attachments.
* Import Custom Fields **(Requires Pro)**
* Import to Custom Post Types  **(Requires Pro)**
* Schedule Imports  **(Requires Pro)**

ImportWP is a base for importing files into wordpress which can be extended upon to create custom templates and parsers specific to your needs, for more details [view the documentation](https://www.importwp.com/documentation/).

== Installation ==

1. Upload 'jc-importer' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Click on the new menu item "ImportWP" and start importing.

For further documentation on installing and using ImportWP features can be found [here](https://www.importwp.com/documentation/).

== Frequently Asked Questions ==

= How do i create an importer =

Documentation on adding an importer can be viewed [here](https://www.importwp.com/documentation/adding-an-importer/)

= What settings does each importer have =

Documentation on using the importer settings can be found [here](https://www.importwp.com/documentation/importer-settings/)

= How to import from an XML file =

A guide to importing data from an xml file can be viewed [here](https://www.importwp.com/documentation/importing-from-xml-file/)

= How to import from an CSV file =

A guide to importing data from an csv file can be viewed [here](https://www.importwp.com/documentation/importing-from-csv-file/)

= How do i run an importer once it is setup =

A guide to running and pausing an import can be viewed [here](https://www.importwp.com/documentation/running-an-import/)

== Screenshots ==

1. ImportWP, New importer screen
2. ImportWP, Post template setup screen
3. ImportWP, Run import page
4. ImportWP, Import history page

== Changelog ==

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
* Add Tax Template and Maper

= 0.1 =

* JC Importer