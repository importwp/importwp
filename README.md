#JC-Importer
JC Importer is an Advanced Wordpress CSV/XML Importer, allowing you to easily drag and drop data into import templates. JC Importer has been built with developers in mind allowing import templates to be easily created and mapped to post_types, taxonomies, users, or tables.

##Installation
1. Clone this repository into your wordpress plugins folder
* Activate the plugin from within the wordpress administration area.
* Click on the JC Importer menu item and start importing your data.

##Upcoming Features
* Fetch csv/xml via ftp to be imported
* Recieve csv/xml via POST
* Allow recurring importing FTP, POST, and Remote datasources
* Visual Template Editor, allow non developers to easily create templates without writing code

##Documentation
[View JC Importer documenation on jamescollings.co.uk](http://jamescollings.co.uk/docs/v1/jc-importer/)

##Templates
* Post
* Page
* Taxonomies
* User

##FAQ

**How do i create an importer**

Documentation on adding an importer can be viewed [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/adding-an-importer/)

**What settings does each importer have**

Documentation on using the importer settings can be found [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/importer-settings/)

**How to import from an XML file**

A guide to importing data from an xml file can be viewed [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/importing-from-an-xml-file/)

**How to import from an CSV file**

A guide to importing data from an csv file can be viewed [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/importing-from-a-csv-file/)

**How do i run an importer once it is setup**

A guide to running and pausing an import can be viewed [here](http://jamescollings.co.uk/docs/v1/jc-importer/getting-started/running-an-import/)

##Changelog

**0.1.3**

* Add file session storage for CSV import
* Save session between imports to keep track of file pointer position
* Allow multiple records to be imported per ajax request

**0.1.2**

* Improved import speed
* Fixed attachment import error message
* Added importer permissions to add screen

**0.1.1**

* Allow page authors to use username, or ID
* Fix post author and post name
* Add Tax Template and Maper

**0.1** 

* Initial Release