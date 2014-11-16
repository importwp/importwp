=== JC Importer ===
Contributors: jcollings
Tags: wordpress importer, xml importer, csv importer, import users, import posts, import taxonomies, import custom post types
Requires at least: 4.0
Tested up to: 4.1
Stable tag: trunk

JC Importer allows you to easily import users, posts, custom post types and taxonomies from XML and CSV files. 

== Description ==

JC Importer is an Advanced Wordpress CSV/XML Importer, allowing you to easily drag and drop data into import templates. JC Importer has been built with developers in mind allowing import templates to be easily created and mapped to post_types, taxonomies, users, or tables.

= Features =

* Import from XML and CSV files.
* Create importer templates to map data to users, taxonomies, and post / custom post types.
* Built in User, Taxonomy, Post and Page templates.

JC Importer is a base for importing files into wordpress which can be extended upon to create custom templates and parsers specific to your needs, for more details [view the documentation](http://jamescollings.co.uk/docs/v1/jc-importer/).

= Available Addons =

* JCI Custom Fields - Add custom fields to post/page/custom_post templates - [view](https://github.com/jcollings/jci-custom-fields)
* JCI Post Datasource - Recieve csv/xml files via POST request - [view](https://github.com/jcollings/jci-post-datasource)

= Upcoming Features =

* Fetch csv/xml via ftp to be imported
* Allow recurring importing FTP, POST, and Remote datasources
* Visual Template Editor, allow non developers to easily create templates without writing code

== Installation ==

1. Upload 'jc-importer' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Click on the new menu item "JC Importer" and start importing.

For further documentation on installing and using JC Importer features can be found [here](http://jamescollings.co.uk/plugins/jc-importer/).

== Frequently Asked Questions ==

= How do i create an importer =

Documentation on adding an importer can be viewed [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/adding-an-importer/)

= What settings does each importer have =

Documentation on using the importer settings can be found [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/importer-settings/)

= How to import from an XML file =

A guide to importing data from an xml file can be viewed [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/importing-from-an-xml-file/)

= How to import from an CSV file =

A guide to importing data from an csv file can be viewed [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/importing-from-a-csv-file/)

= How do i run an importer once it is setup =

A guide to running and pausing an import can be viewed [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/running-an-import/)

== Screenshots ==

1. JC Importer, New importer screen
2. JC Importer, Post template setup screen
3. JC Importer, Run import page
4. JC Importer, Import history page

== Changelog ==

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

== Upgrade Notice ==
