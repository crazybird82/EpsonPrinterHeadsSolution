# EpsonPrinterHeadsSolution
This is especially for Epson WF-7620 Printer and may for other ink printers.

A PHP script to save the printer heads with a Linux system like Raspbian or Debian:
the task is to not let dry the ink at the printer heads.

This Script ask the printer server with snmp for the counter of the color pages (needs php snmp extension).
Also need a mysql DB or change the script ;) It use a FileLogger script and put every massage into a log-file.
I use a apache2 on a Rasbian with stretch and php7, but it's works also with php 5.4 and a other Webserver.

The default is to print every 2 days when isn't printed on this printer in color - for that is the counter of printed color pages.  





