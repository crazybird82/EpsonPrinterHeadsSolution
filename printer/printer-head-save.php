<?php

/**
 * @autor Pascal Specht
 * 
 * a quick and dirty solution for Epson WorkFlow 7620 printerhead problems
 * let the printer always on! the standby-mode it costs 0.70 € with a price 0.28 € kilowatt hour in a year and only then the printer can try to not let the ink dry.
 * 
 * this script is asking the printer server with snmp how many color pages are printed with a cronjob once per day. That information put it into a mysql-db.
 * After 2 days without printing a color page this script will print a test page, so the printer heads can't get a problem with dried ink ...
 * 
 * I have it on a raspberry pi with raspbian stretch 
 * may it can work with other printers make sure that php-snmp is installed works with php 5.4 or higher
 * 
 * thx to php manual and gehaxelt, pedzed for the FileLogger
 */

/**
 * oid Epson 7620
 * prnCounterLive -> var=$(snmpget -v 1 -c "public" 192.168.178.27 1.3.6.1.2.1.43.10.2.1.4.1.1)
 * .1.3.6.1.4.1.1248.1.2.2.6.1.1.5.1.2 printed color pages
 * .1.3.6.1.4.1.1248.1.2.2.27.1.1.4.1.1 printed color pages
 * .1.3.6.1.4.1.1248.1.2.2.27.1.1.3.1.1 maybe printed b/w pages
 * .1.3.6.1.4.1.1248.1.2.2.27.6.1.4.1.1.2; may dopple printed pages
 * .1.3.6.1.4.1.1248.1.4.2.6.1.1.4.1.3; may dopple printed pages
 * 
 **/

 //config vars
 $documentDir =  $_SERVER["DOCUMENT_ROOT"].'/printer/'; // dir: at me -> /var/www/html/printer/
 $logAll = false;
 $documentLog = $documentDir.'logs/printer.log';
 $documentFileloger = $documentDir.'lib/fileLogger/';
 
 
 $maxCounterRows = 2; //if 2 then at the third day print the test site
 $printerCommand = 'lp -d WF-7620 -o media=a4 -o scaling=50 '.$documentDir.'Druckertest_alt.pdf'; //the shell command for the printer - I name those WF-7620
 $snmpOid = '.1.3.6.1.4.1.1248.1.2.2.6.1.1.5.1.2'; //snmpOid of printed color pages
 $snmpReplaceString = "Counter32: "; //snmp at Epson has not only the value, also the data form with it...

 $snmpHost = "192.168.178.27"; //Epson Printer IP
 $snmpC = "public"; //snmp community name

 $today = date("Y-m-d"); //

 $mysqlHost = "localhost";
 $mysqlUser = "dbuser";
 $mysqlPwd = "pwd";
 $mysqlDB = "printer";
 $mysqlTable = "printerCount";
 //end config vars
 
 require_once($documentFileloger.'Compatibility.php');
 require_once($documentFileloger.'FileLogger.php');
 require_once($documentFileloger.'PackageInfo.php');

 use fileLogger\Compatibility;
 use fileLogger\CompatibilityException;
 use fileLogger\FileLogger;
 use fileLogger\PackageInfo as FLPackageInfo;

 try {
  $compat = Compatibility::check();
 } catch(CompatibilityException $e){
  die($e->getMessage());
 }

 $log = new FileLogger($documentLog);
 $log->log("Start ===>  ", FileLogger::NOTICE);
 $session = new SNMP(SNMP::VERSION_1, $snmpHost, $snmpC);
 
 if(!@$session->get($snmpOid)) { // try is snmp answering or something if getting false, then die!
  $log->log(__LINE__.': snmp-error: '.$session->getError(), FileLogger::FATAL);
  die();
 }
 
 if($session) {
  $printedColorPages = $session->get($snmpOid);
  if($logAll) {
  	$log->log(__LINE__.": snmp-get(".$snmpOid.") -> ".$printedColorPages, FileLogger::NOTICE);
  }
  $printedColorPages = str_replace( $snmpReplaceString, "", $printedColorPages);
  if($logAll) {
  	$log->log(__LINE__.': replace: '.$printedColorPages, FileLogger::NOTICE);
  }

  if (settype ($printColorPages , "integer")) {
   $pagesColor = (int) $printedColorPages;
   $log->log(__LINE__.": printed color pages: $pagesColor with the type of ".gettype($pagesColor), FileLogger::NOTICE);

   $mysqli = new mysqli($mysqlHost, $mysqlUser, $mysqlPwd, $mysqlDB);
   if ($mysqli->connect_error || mysqli_connect_error()) {
   	$log->log(__LINE__.': MySQL Connect Error (' . $mysqli->connect_errno . ')'.$mysqli->connect_error , FileLogger::FATAL);
    die();
   }

   $result = $mysqli->query("SELECT `id` FROM `".$mysqlTable."` WHERE `counterColorPages` = '$pagesColor';");
   $rowsColorPages = $result->num_rows;
   
   $result2 = $mysqli->query("SELECT `id`, `counterColorPages` FROM `".$mysqlTable."` WHERE `counterDate` = '$today'");
   $rowsToday = $result2->num_rows;
   
   $result3 = $mysqli->query("SELECT `id` FROM `".$mysqlTable."` WHERE `counterColorPages` = '$pagesColor' AND counterDate = '$today';");
   $rowsTodayWithColorPages = $result3->num_rows;
   
   $result4 = $mysqli->query("SELECT `id` FROM `".$mysqlTable."` WHERE `counterColorPages` = '$pagesColor' AND counterDate = '$today' AND printedTestPage = 0;");
   $rowsTodayNotPrinted = $result4->num_rows;
   
   $result5 = $mysqli->query("SELECT `id` FROM `".$mysqlTable."` WHERE `counterColorPages` = '$pagesColor' AND counterDate = '$today' AND printedTestPage = 1;");
   $rowsTodayAlreadyPrinted = $result5->num_rows;
   
   if($logAll) {
   	$log->log(__LINE__.": rowsColorPages -> $rowsColorPages; rowsToday -> $rowsToday; rowsTodayWithColorPages -> $rowsTodayWithColorPages;  rowsTodayNotPrinted -> $rowsTodayNotPrinted ; rowsTodayAlreadyPrinted -> $rowsTodayAlreadyPrinted:", FileLogger::NOTICE);
   }
 
   if($rowsColorPages >= $maxCounterRows) {
   	if($rowsTodayWithColorPages == 0) {
   	 $log->log(__LINE__.": The printer didn't print the last ".$maxCounterRows." days, so today for the printer heads this script print a test site.", FileLogger::NOTICE);
   	 if($result = $mysqli->query("INSERT INTO `".$mysqlTable."` (`id`, `counterColorPages`, `counterDate`, `printedTestPage`) VALUES (NULL, '$pagesColor', '$today', '1');")) {
      $log->log(__LINE__.": Insert to DB Table is OK; send print command: ".$printerCommand, FileLogger::NOTICE);
   	  shell_exec($printerCommand);
   	 } else {
   	  $log->log(__LINE__.": Couldn't put new Data into the DB Table ... Something was wrong ...", FileLogger::ERROR);
   	 }
   	} else {
   	 $log->log(__LINE__.": This Day is already in the DB Table! rowsTodayNotPrinted -> $rowsTodayNotPrinted; rowsTodayAlreadyPrinted -> $rowsTodayAlreadyPrinted", FileLogger::WARNING);
   	}
   	
   } else {
    if($rowsToday == 0 && $rowsTodayWithColorPages == 0) {
   	 
   	 if($result = $mysqli->query("INSERT INTO `".$mysqlTable."` (`id`, `counterColorPages`, `counterDate`, `printedTestPage`) VALUES (NULL, '$pagesColor', '$today', '0');")) {
   	  $log->log(__LINE__.": No Data with $pagesColor printed color Pages today, try to put new Data into the Table ... OK", FileLogger::NOTICE);
   	 } else {
   	  $log->log(__LINE__.": No Data with $pagesColor printed color Pages today, try to put new Data into the Table ... Something was wrong ...", FileLogger::ERROR);
   	 }
    } else {
    	$log->log(__LINE__.": There is already something: rowsColorpages -> $rowsColorPages; rowsToday -> $rowsToday; rowsTodayWithColorPages -> $rowsTodayWithColorPages", FileLogger::ERROR);
   	}
   }
   
   $mysqli->close();
  } //settype

 $session->close();
 $log->log("END <===  ", FileLogger::NOTICE);
 } //session
?>
