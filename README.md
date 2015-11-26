Epub-Creator-by-PHP
==================

Author: wendoscoo 
Email : wendosyi@gmail.com

### Create EPUB file by php .

simple to use, you should only commit your book data and chapter data. Here is a complete demo in demo.php

`Epub.class.php`       
 - Provides a number of public methods to initialize epub;

`EpubCore.class.php`   
 - Create core files (like opf and ncx file) and register chapter files. Also provides methods to pack these files;

`EpubPacker.class.php`
 - Create Chapter HTML files and pack the epub file;

`EpubZip.class.php`   
 - This class is one of the CodeIgniter libraries. Only a few changes, in order to ensure a perfect combination Epubpacker use.


Note :  you can use this tool to pack your data to a standard epub book file , whether these data were stored in your database or text files;


