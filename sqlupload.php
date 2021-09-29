<?php
   2
   3// BigDump ver. 0.31b from 2009-11-12
   4// Staggered import of an large MySQL Dump (like phpMyAdmin 2.x Dump)
   5// Even through the webservers with hard runtime limit and those in safe mode
   6// Works fine with Internet Explorer 7.0 and Firefox 2.x
     13// This program is free software; you can redistribute it and/or modify it under the
  14// terms of the GNU General Public License as published by the Free Software Foundation;
  15// either version 2 of the License, or (at your option) any later version.
  16
  17// THIS SCRIPT IS PROVIDED AS IS, WITHOUT ANY WARRANTY OR GUARANTEE OF ANY KIND
  18
  19// USAGE
  20
  21// 1. Adjust the database configuration in this file
  22// 2. Remove the old tables on the target database if your dump doesn't contain "DROP TABLE"
  23// 3. Create the working directory (e.g. dump) on your web server
  24// 4. Upload bigdump.php and your dump files (.sql, .gz) via FTP to the working directory
  25// 5. Run the bigdump.php from your browser via URL like http://www.yourdomain.com/dump/bigdump.php
  26// 6. BigDump can start the next import session automatically if you enable the JavaScript
  27// 7. Wait for the script to finish, do not close the browser window
  28// 8. IMPORTANT: Remove bigdump.php and your dump files from the web server
  29
  30// If Timeout errors still occure you may need to adjust the $linepersession setting in this file
  31
  32// LAST CHANGES
  33
  34// *** Remove deprecated e reg()
  35// *** Add mysql module availability check
  36// *** Workaround for mysql_close() bug #48754 in PHP 5.3
  37// *** Fixing the timezone warning for date() in PHP 5.3
  38
  39
  40// Database configuration ************   UPDATED FOR PhreeBooks ****************
  41// file: /modules/phreedom/includes/bigdump/bigdump.php
  42$db_server   = DB_SERVER_HOST;
  43$db_name     = DB_DATABASE;
  44$db_username = DB_SERVER_USERNAME;
  45$db_password = DB_SERVER_PASSWORD;
  46
  47// Other settings (optional)
  48
  49$filename           = '';     // Specify the dump filename to suppress the file selection dialog
  50$csv_insert_table   = '';     // Destination table for CSV files
  51$csv_preempty_table = false;  // true: delete all entries from table specified in $csv_insert_table before processing
  52$ajax               = true;   // AJAX mode: import will be done without refreshing the website
  53$linespersession    = 3000;   // Lines to be executed per one import session
  54$delaypersession    = 0;      // You can specify a sleep time in milliseconds after each session
  55                              // Works only if JavaScript is activated. Use to reduce server overrun
  56
  57// Allowed comment delimiters: lines starting with these strings will be dropped by BigDump
  58
  59$comment[]='#';                       // Standard comment lines are dropped by default
  60$comment[]='-- ';
  61// $comment[]='---';                  // Uncomment this line if using proprietary dump created by outdated mysqldump
  62// $comment[]='CREATE DATABASE';      // Uncomment this line if your dump contains create database queries in order to ignore them
  63$comment[]='/*!';                  // Or add your own string to leave out other proprietary things
  64$comment[]='/*';  // **************** PhreeBooks Release 1.5 and earlier company backups *********************
  65
  66
  67
  68// Connection character set should be the same as the dump file character set (utf8, latin1, cp1251, koi8r etc.)
  69// See http://dev.mysql.com/doc/refman/5.0/en/charset-charsets.html for the full list
  70
  71$db_connection_charset = 'utf8'; // *************** Set to utf8 for PhreeBooks *********************
  72$finished = false; // *************** added to logout on success for PhreeBooks *********************
  73
  74// *******************************************************************************************
  75// If not familiar with PHP please don't change anything below this line
  76// *******************************************************************************************
  77
  78if ($ajax)
  79  ob_start();
  80
  81define ('VERSION','0.31b');
  82define ('DATA_CHUNK_LENGTH',16384);  // How many chars are read per time
  83define ('MAX_QUERY_LINES',300);      // How many lines may be considered to be one query (except text lines)
  84define ('TESTMODE',false);           // Set to true to process the file without actually accessing the database
  85
  86header("Expires: Mon, 1 Dec 2003 01:00:00 GMT");
  87header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
  88header("Cache-Control: no-store, no-cache, must-revalidate");
  89header("Cache-Control: post-check=0, pre-check=0", false);
  90header("Pragma: no-cache");
  91
  92@ini_set('auto_detect_line_endings', true);
  93@set_time_limit(0);
  94
  95if (function_exists("date_default_timezone_set") && function_exists("date_default_timezone_get"))
  96  @date_default_timezone_set(@date_default_timezone_get());
  97
  98// Clean and strip anything we don't want from user's input [0.27b]
  99
 100foreach ($_REQUEST as $key => $val) 
 101{
 102  $val = preg_replace("/[^_A-Za-z0-9-\.&= ]/i",'', $val);
 103  $_REQUEST[$key] = $val;
 104}
 105
 106?>
 107<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
 108<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US" xml:lang="en-US">
 109 <head>
 110<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
 111<!--  <title>BigDump ver. <?php echo (VERSION); ?></title> ***********  Mod for PhreeBooks ************ -->
 112<meta http-equiv="CONTENT-LANGUAGE" content="EN"/>
 113
 114<meta http-equiv="Cache-Control" content="no-cache/"/>
 115<meta http-equiv="Pragma" content="no-cache"/>
 116<meta http-equiv="Expires" content="-1"/>
 117
 118<?php // ****************************** BOF - Mods by PhreeSoft **************************************** ?>
 119  <title><?php echo PAGE_TITLE; ?></title>
 120  <link rel="stylesheet" type="text/css" href="<?php echo DIR_WS_THEMES . 'css/stylesheet.css'; ?>" />
 121  <link rel="shortcut icon" type="image/ico" href="favicon.ico" />
 122  <script type="text/javascript">
 123  // Variables for script generated combo boxes
 124  var pbBrowser       = (document.all) ? 'IE' : 'FF';
 125  var icon_path       = '<?php echo DIR_WS_ICONS; ?>';
 126  var combo_image_on  = '<?php echo DIR_WS_ICONS . '16x16/phreebooks/pull_down_active.gif'; ?>';
 127  var combo_image_off = '<?php echo DIR_WS_ICONS . '16x16/phreebooks/pull_down_inactive.gif'; ?>';
 128  </script>
 129  <script type="text/javascript" src="includes/common.js"></script>
 130  <script type="text/javascript" src="modules/phreedom/includes/jquery/jquery-1.4.3.min.js"></script>
 131  <?php 
 132  if (!$_SESSION['admin_prefs']['theme']) $_SESSION['admin_prefs']['theme'] = 'default';
 133  require_once(DIR_FS_ADMIN . 'themes/' . $_SESSION['admin_prefs']['theme'] . '/config.php');
 134  // load the language file
 135  include_once(DIR_FS_MODULES . 'phreedom/includes/bigdump/language/'.$_SESSION['language'].'/language.php');
 136  // load the javascript specific, required
 137  $js_include_path = DIR_FS_WORKING . 'pages/' . $page . '/js_include.php';
 138  if (file_exists($js_include_path)) { require($js_include_path); } else die('No js_include file');
 139// ****************************** EOF - Mods by PhreeSoft **************************************** ?>
 140
 141<style type="text/css">
 142<!--
 143
 144/****************************** BOF - Mods by PhreeSoft ****************************************
 145body
 146{ background-color:#FFFFF0;
 147}
 148
 149h1 
 150{ font-size:20px;
 151  line-height:24px;
 152  font-family:Arial,Helvetica,sans-serif;
 153  margin-top:5px;
 154  margin-bottom:5px;
 155}
 156
 157p,td,th
 158{ font-size:14px;
 159  line-height:18px;
 160  font-family:Arial,Helvetica,sans-serif;
 161  margin-top:5px;
 162  margin-bottom:5px;
 163  text-align:justify;
 164  vertical-align:top;
 165}
 166****************************** EOF - Mods by PhreeSoft ****************************************/
 167
 168p.centr
 169{ 
 170  text-align:center;
 171}
 172
 173p.smlcentr
 174{ font-size:10px;
 175  line-height:14px;
 176  text-align:center;
 177}
 178
 179p.error
 180{ color:#FF0000;
 181  font-weight:bold;
 182}
 183
 184p.success
 185{ color:#00DD00;
 186  font-weight:bold;
 187}
 188
 189p.successcentr
 190{ color:#00DD00;
 191  background-color:#DDDDFF;
 192  font-weight:bold;
 193  text-align:center;
 194}
 195
 196/****************************** BOF - Mods by PhreeSoft ****************************************
 197td
 198{ background-color:#F8F8F8;
 199  text-align:left;
 200}
 201/****************************** EOF - Mods by PhreeSoft ****************************************/
 202
 203td.transparent
 204{ background-color:#FFFFF0;
 205}
 206
 207/****************************** BOF - Mods by PhreeSoft ****************************************
 208th
 209{ font-weight:bold;
 210  color:#FFFFFF;
 211  background-color:#AAAAEE;
 212  text-align:left;
 213}
 214/****************************** EOF - Mods by PhreeSoft ****************************************/
 215
 216td.right
 217{ text-align:right;
 218}
 219
 220/****************************** BOF - Mods by PhreeSoft ****************************************
 221form
 222{ margin-top:5px;
 223  margin-bottom:5px;
 224}
 225/****************************** EOF - Mods by PhreeSoft ****************************************/
 226
 227div.skin1
 228{
 229  border-color:#3333EE;
 230  border-width:5px;
 231  border-style:solid;
 232  background-color:#AAAAEE;
 233  text-align:center;
 234  vertical-align:middle;
 235  padding:3px;
 236  margin:1px;
 237}
 238
 239td.bg3
 240{ background-color:#EEEE99;
 241  text-align:left;
 242  vertical-align:top;
 243  width:20%;
 244}
 245
 246th.bg4
 247{ background-color:#EEAA55;
 248  text-align:left;
 249  vertical-align:top;
 250  width:20%;
 251}
 252
 253td.bgpctbar
 254{ background-color:#EEEEAA;
 255  text-align:left;
 256  vertical-align:middle;
 257  width:80%;
 258}
 259
 260-->
 261</style>
 262
 263</head>
 264
 265<body>
 266<?php // ****************************** BOF - Mods by PhreeSoft ****************************************
 267//if ($include_header) { require(DIR_FS_INCLUDES . 'header.php'); }
 268
 269echo html_form('restore', FILENAME_DEFAULT, gen_get_all_get_params(array('action','delete','start','fn','foffset','totalqueries')) . 'action=restore', 'post', 'enctype="multipart/form-data"', true) . chr(10);
 270
 271// include hidden fields
 272echo html_hidden_field('todo', '') . chr(10);
 273
 274// customize the toolbar actions
 275$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action','delete','start','fn','foffset','totalqueries')), 'SSL') . '\'"';
 276$toolbar->icon_list['open']['show']     = false;
 277$toolbar->icon_list['save']['show']     = false;
 278$toolbar->icon_list['delete']['show']   = false;
 279$toolbar->icon_list['print']['show']    = false;
 280
 281// pull in extra toolbar overrides and additions
 282if (count($extra_toolbar_buttons) > 0) {
 283	foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
 284}
 285
 286// add the help file index and build the toolbar
 287$toolbar->add_help('01');
 288echo $toolbar->build_toolbar(); 
 289
 290echo '<h1>' . BOX_HEADING_RESTORE . '</h1>';
 291// ****************************** EOF - Mods by PhreeSoft **************************************** ?>
 292
 293<center>
 294
 295<table width="780" cellspacing="0" cellpadding="0">
 296<tr><td class="transparent">
 297
 298<!-- <h1>BigDump: Staggered MySQL Dump Importer ver. <?php echo (VERSION); ?></h1> -->
 299
 300<?php
 301
 302function skin_open() {
 303echo ('<div class="skin1">');
 304}
 305
 306function skin_close() {
 307echo ('</div>');
 308}
 309
 310skin_open();
 311// ****************************** BOF - Mods by PhreeSoft ****************************************
 312echo BIGDUMP_INTRO;
 313//echo ('<h1>BigDump: Staggered MySQL Dump Importer v'.VERSION.'</h1>');
 314// ****************************** EOF - Mods by PhreeSoft ****************************************
 315skin_close();
 316
 317$error = false;
 318$file  = false;
 319
 320// Check PHP version
 321
 322if (!$error && !function_exists('version_compare'))
 323{ echo ("<p class=\"error\">PHP version 4.1.0 is required for BigDump to proceed. You have PHP ".phpversion()." installed. Sorry!</p>\n");
 324  $error=true;
 325}
 326
 327// Check if mysql extension is available
 328
 329if (!$error && !function_exists('mysql_connect'))
 330{ echo ("<p class=\"error\">There is no mySQL extension available in your PHP installation. Sorry!</p>\n");
 331  $error=true;
 332}
 333
 334// Calculate PHP max upload size (handle settings like 10M or 100K)
 335
 336if (!$error)
 337{ $upload_max_filesize=ini_get("upload_max_filesize");
 338  if (preg_match("/([0-9]+)K/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024;
 339  if (preg_match("/([0-9]+)M/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024*1024;
 340  if (preg_match("/([0-9]+)G/i",$upload_max_filesize,$tempregs)) $upload_max_filesize=$tempregs[1]*1024*1024*1024;
 341}
 342
 343// Get the current directory
 344
 345// ****************************** BOF - Mods by PhreeSoft ****************************************
 346/*
 347if (isset($_SERVER["CGIA"]))
 348  $upload_dir=dirname($_SERVER["CGIA"]);
 349else if (isset($_SERVER["ORIG_PATH_TRANSLATED"]))
 350  $upload_dir=dirname($_SERVER["ORIG_PATH_TRANSLATED"]);
 351else if (isset($_SERVER["ORIG_SCRIPT_FILENAME"]))
 352  $upload_dir=dirname($_SERVER["ORIG_SCRIPT_FILENAME"]);
 353else if (isset($_SERVER["PATH_TRANSLATED"]))
 354  $upload_dir=dirname($_SERVER["PATH_TRANSLATED"]);
 355else 
 356  $upload_dir=dirname($_SERVER["SCRIPT_FILENAME"]);
 357*/
 358$upload_dir = DIR_FS_MY_FILES . 'backups';
 359$web_dir    = DIR_FS_MY_FILES . 'backups';
 360// ****************************** EOF - Mods by PhreeSoft ****************************************
 361
 362// Handle file upload
 363
 364if (!$error && isset($_REQUEST["uploadbutton"]))
 365{ if (is_uploaded_file($_FILES["dumpfile"]["tmp_name"]) && ($_FILES["dumpfile"]["error"])==0)
 366  { 
 367    $uploaded_filename=str_replace(" ","_",$_FILES["dumpfile"]["name"]);
 368    $uploaded_filename=preg_replace("/[^_A-Za-z0-9-\.]/i",'',$uploaded_filename);
 369    $uploaded_filepath=str_replace("\\","/",$upload_dir."/".$uploaded_filename);
 370
 371    if (file_exists($uploaded_filename))
 372    { echo ('<p class="error">' . sprintf(BIGDUMP_FILE_EXISTS, $uploaded_filename) . "</p>\n");
 373    }
 374    else if (!preg_match("/(\.(sql|gz|csv))$/i", $uploaded_filename))
 375    { echo ('<p class="error">' . BIGDUMP_UPLOAD_TYPES . "</p>\n");
 376    }
 377    else if (!@move_uploaded_file($_FILES["dumpfile"]["tmp_name"],$uploaded_filepath))
 378    { echo ("<p class=\"error\">" . sprintf(BIGDUMP_ERROR_MOVE, $_FILES["dumpfile"]["tmp_name"], $uploaded_filepath) . "</p>\n");
 379      echo ("<p>" . sprintf(BIGDUMP_ERROR_PERM, $upload_dir) . "</p>\n");
 380    }
 381    else
 382    { echo ("<p class=\"success\">" . sprintf(BIGDUMP_FILE_SAVED, $uploaded_filename) . "</p>\n");
 383    }
 384  }
 385  else
 386  { echo ("<p class=\"error\">" . BIGDUMP_ERROR_UPLOAD . $_FILES["dumpfile"]["name"] . "</p>\n");
 387  }
 388}
 389
 390
 391// Handle file deletion (delete only in the current directory for security reasons)
 392
 393if (!$error && isset($_REQUEST["delete"]) && $_REQUEST["delete"]!=basename($_SERVER["SCRIPT_FILENAME"]))
 394// ****************************** BOF - Mods by PhreeSoft ****************************************
 395//{ if (preg_match("/(\.(sql|gz|csv))$/i",$_REQUEST["delete"]) && @unlink(basename($_REQUEST["delete"])))
 396// ****************************** EOF - Mods by PhreeSoft ****************************************
 397{ if (preg_match("/(\.(sql|gz|csv))$/i",$_REQUEST["delete"]) && @unlink($upload_dir . '/' . $_REQUEST["delete"]))
 398    echo ('<p class="success">'.$_REQUEST["delete"] . BIGDUMP_REMOVED . "</p>\n");
 399  else
 400    echo ('<p class="error">' . BIGDUMP_FAIL_REMOVE . $_REQUEST["delete"]."</p>\n");
 401}
 402
 403// Connect to the database
 404
 405if (!$error && !TESTMODE)
 406{ $dbconnection = @mysql_connect($db_server,$db_username,$db_password);
 407  if ($dbconnection) 
 408    $db = mysql_select_db($db_name);
 409  if (!$dbconnection || !$db) 
 410  { echo ("<p class=\"error\">Database connection failed due to ".mysql_error()."</p>\n");
 411    echo ("<p>Edit the database settings in ".$_SERVER["SCRIPT_FILENAME"]." or contact your database provider.</p>\n");
 412    $error=true;
 413  }
 414  if (!$error && $db_connection_charset!=='')
 415    @mysql_query("SET NAMES $db_connection_charset", $dbconnection);
 416}
 417else
 418{ $dbconnection = false;
 419}
 420
 421// List uploaded files in multifile mode
 422
 423if (!$error && !isset($_REQUEST["fn"]) && $filename=="")
 424{ if ($dirhandle = opendir($upload_dir)) 
 425  { $dirhead=false;
 426    while (false !== ($dirfile = readdir($dirhandle)))
 427    { if ($dirfile != "." && $dirfile != ".." && $dirfile!=basename($_SERVER["SCRIPT_FILENAME"]))
 428      { if (!$dirhead)
 429        { echo ("<table width=\"100%\" cellspacing=\"2\" cellpadding=\"2\">\n");
 430          echo ("<tr><th>".TEXT_FILENAME."</th><th>".TEXT_SIZE."</th><th>".TEXT_DATE."&amp;".TEXT_TIME."</th><th>".TEXT_TYPE."</th><th>&nbsp;</th><th>&nbsp;</th></tr>\n");
 431          $dirhead=true;
 432        }
 433// ****************************** BOF - Mods by PhreeSoft ****************************************
 434//        echo ("<tr><td>$dirfile</td><td class=\"right\">".filesize($dirfile)."</td><td>".date ("Y-m-d H:i:s", filemtime($dirfile))."</td>");
 435        echo ("<tr><td>$dirfile</td><td class=\"right\">".filesize($upload_dir.'/'.$dirfile)."</td><td>".date ("Y-m-d H:i:s", filemtime($upload_dir.'/'.$dirfile))."</td>");
 436// ****************************** EOF - Mods by PhreeSoft ****************************************
 437
 438        if (preg_match("/\.sql$/i",$dirfile))
 439          echo ("<td>SQL</td>");
 440        elseif (preg_match("/\.gz$/i",$dirfile))
 441          echo ("<td>GZip</td>");
 442        elseif (preg_match("/\.csv$/i",$dirfile))
 443          echo ("<td>CSV</td>");
 444        else
 445          echo ("<td>".TEXT_MISC."</td>");
 446
 447        if ((preg_match("/\.gz$/i",$dirfile) && function_exists("gzopen")) || preg_match("/\.sql$/i",$dirfile) || preg_match("/\.csv$/i",$dirfile))
 448// ****************************** BOF - Mods by PhreeSoft ****************************************
 449//          echo ("<td><a href=\"".$_SERVER["PHP_SELF"]."?start=1&amp;fn=".urlencode($dirfile)."&amp;foffset=0&amp;totalqueries=0\">Start Import</a> into $db_name at $db_server</td>\n <td><a href=\"".$_SERVER["PHP_SELF"]."?delete=".urlencode($dirfile)."\">Delete file</a></td></tr>\n");
 450          echo "<td>" . 
 451		   "<a href=\"" . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('delete','start','fn','foffset','totalqueries')) . 'action=restore', 'SSL') . "&amp;start=1&amp;fn=".urlencode($dirfile)."&amp;foffset=0&amp;totalqueries=0\">" . BIGDUMP_START_IMP . "</a> " . sprintf(BIGDUMP_START_LOC, $db_name, $db_server) . 
 452		   "</td>\n<td>" . 
 453		   "<a href=\"" . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('delete','start','fn','foffset','totalqueries')) . 'action=restore', 'SSL') . "&amp;delete=".urlencode($dirfile)."\">".BIGDUMP_DEL_FILE."</a>" . 
 454		   "</td></tr>\n";
 455// ****************************** EOF - Mods by PhreeSoft ****************************************
 456        else
 457          echo ("<td>&nbsp;</td>\n <td>&nbsp;</td></tr>\n");
 458      }
 459
 460    }
 461    if ($dirhead) echo ("</table>\n");
 462    else echo ("<p>".BIGDUMP_NO_FILES."</p>\n");
 463    closedir($dirhandle); 
 464  }
 465  else
 466  { echo ("<p class=\"error\">".sprintf(BIGDUMP_ERROR_DIR, $upload_dir)."</p>\n");
 467    $error=true;
 468  }
 469}
 470
 471
 472// Single file mode
 473
 474if (!$error && !isset ($_REQUEST["fn"]) && $filename!="")
 475{ echo ("<p><a href=\"".$_SERVER["PHP_SELF"]."?start=1&amp;fn=".urlencode($filename)."&amp;foffset=0&amp;totalqueries=0\">".BIGDUMP_START_IMP."</a> ".sprintf(BIGDUMP_FROM_LOC, $filename, $db_name, $db_server)."</p>\n");
 476}
 477
 478
 479// File Upload Form
 480
 481if (!$error && !isset($_REQUEST["fn"]) && $filename=="")
 482{ 
 483
 484// Test permissions on working directory
 485
 486// ****************************** BOF - Mods by PhreeSoft ****************************************
 487//  do { $tempfilename=time().".tmp"; } while (file_exists($tempfilename));
 488  do { $tempfilename=$upload_dir.'/'.time().".tmp"; } while (file_exists($tempfilename));
 489// ****************************** EOF - Mods by PhreeSoft ****************************************
 490  if (!($tempfile=@fopen($tempfilename,"w")))
 491  { echo "<p>".sprintf(BIGDUMP_UPLOAD_A, $upload_dir);
 492// ****************************** BOF - Mods by PhreeSoft ****************************************
 493//    echo ("to upload files from here. Alternatively you can upload your dump files via FTP.</p>\n");
 494    echo (BIGDUMP_UPLOAD_B . $web_dir . "</p>\n");
 495// ****************************** EOF - Mods by PhreeSoft ****************************************
 496  }
 497  else
 498  { fclose($tempfile);
 499    unlink ($tempfilename);
 500 
 501    echo "<p>" . sprintf(BIGDUMP_UPLOAD_C, $upload_max_filesize, round ($upload_max_filesize/1024/1024));
 502// ****************************** BOF - Mods by PhreeSoft ****************************************
 503//    echo ("directly from your browser to the server. Alternatively you can upload your dump files of any size via FTP.</p>\n");
 504    echo (BIGDUMP_UPLOAD_D . $web_dir . "</p>\n");
 505//<form method="POST" action="< ?php echo ($_SERVER["PHP_SELF"]); ? >" enctype="multipart/form-data">
 506// ****************************** EOF - Mods by PhreeSoft ****************************************
 507?>
 508<input type="hidden" name="MAX_FILE_SIZE" value="$upload_max_filesize" />
 509<p>Dump file: <input type="file" name="dumpfile" accept="*/*" size="60" /></p>
 510<p><input type="submit" name="uploadbutton" value="Upload" /></p>
 511<?php // ************************ BOF - Mods by PhreeSoft ****************************************
 512// </form>
 513// ****************************** EOF - Mods by PhreeSoft **************************************** ?>
 514<?php
 515  }
 516}
 517
 518// Print the current mySQL connection charset
 519
 520if (!$error && !TESTMODE && !isset($_REQUEST["fn"]))
 521{ 
 522  $result = mysql_query("SHOW VARIABLES LIKE 'character_set_connection';");
 523  $row = mysql_fetch_assoc($result);
 524  if ($row) 
 525  { $charset = $row['Value'];
 526    echo ("<p>Note: The current mySQL connection charset is <i>$charset</i>. Your dump file must be encoded in <i>$charset</i> in order to avoid problems with non-latin characters. You can change the connection charset using the \$db_connection_charset variable in bigdump.php</p>\n");
 527  }
 528}
 529
 530// Open the file
 531
 532if (!$error && isset($_REQUEST["start"]))
 533{ 
 534
 535// Set current filename ($filename overrides $_REQUEST["fn"] if set)
 536
 537  if ($filename!="")
 538    $curfilename=$filename;
 539  else if (isset($_REQUEST["fn"]))
 540    $curfilename=urldecode($_REQUEST["fn"]);
 541  else
 542    $curfilename="";
 543
 544// Recognize GZip filename
 545
 546  if (preg_match("/\.gz$/i",$curfilename)) 
 547    $gzipmode=true;
 548  else
 549    $gzipmode=false;
 550
 551// ****************************** BOF - Mods by PhreeSoft ****************************************
 552//  if ((!$gzipmode && !$file=@fopen($curfilename,"rt")) || ($gzipmode && !$file=@gzopen($curfilename,"rt")))
 553  if ((!$gzipmode && !$file=@fopen($upload_dir . '/' . $curfilename,"rt")) || ($gzipmode && !$file=@gzopen($upload_dir . '/' . $curfilename,"rt")))
 554// ****************************** EOF - Mods by PhreeSoft ****************************************
 555  { echo ("<p class=\"error\">" . sprintf(BIGDUMP_OPEN_FAIL, $curfilename) . "</p>\n");
 556    echo ("<p>" . sprintf(BIGDUMP_BAD_NAME, $curfilename, $filename, $curfilename) . "</p>\n");
 557    $error=true;
 558  }
 559
 560// Get the file size (can't do it fast on gzipped files, no idea how)
 561
 562  else if ((!$gzipmode && @fseek($file, 0, SEEK_END)==0) || ($gzipmode && @gzseek($file, 0)==0))
 563  { if (!$gzipmode) $filesize = ftell($file);
 564    else $filesize = gztell($file);                   // Always zero, ignore
 565  }
 566  else
 567  { echo ("<p class=\"error\">" . sprintf(BIGDUMP_NO_SEEK, $curfilename) . "</p>\n");
 568    $error=true;
 569  }
 570}
 571
 572// *******************************************************************************************
 573// START IMPORT SESSION HERE
 574// *******************************************************************************************
 575
 576if (!$error && isset($_REQUEST["start"]) && isset($_REQUEST["foffset"]) && preg_match("/(\.(sql|gz|csv))$/i",$curfilename))
 577{
 578
 579// Check start and offset are numeric values
 580
 581  if (!is_numeric($_REQUEST["start"]) || !is_numeric($_REQUEST["foffset"]))
 582  { echo ("<p class=\"error\">".BIGDUMP_IMPORT_MSG_1."</p>\n");
 583    $error=true;
 584  }
 585
 586// Empty CSV table if requested
 587
 588  if (!$error && $_REQUEST["start"]==1 && $csv_insert_table != "" && $csv_preempty_table)
 589  { 
 590    $query = "DELETE FROM $csv_insert_table";
 591    if (!TESTMODE && !mysql_query(trim($query), $dbconnection))
 592    { echo ("<p class=\"error\">".sprintf(BIGDUMP_IMPORT_MSG_2, $csv_insert_table)."</p>\n");
 593      echo ("<p>".TEXT_QUERY.trim(nl2br(htmlentities($query)))."</p>\n");
 594      echo ("<p>".TEXT_MYSQL.mysql_error()."</p>\n");
 595      $error=true;
 596    }
 597  }
 598
 599  
 600// Print start message
 601
 602  if (!$error)
 603  { $_REQUEST["start"]   = floor($_REQUEST["start"]);
 604    $_REQUEST["foffset"] = floor($_REQUEST["foffset"]);
 605    skin_open();
 606    if (TESTMODE) echo ("<p class=\"centr\">TEST MODE ENABLED</p>\n");
 607    echo ("<p class=\"centr\">".TEXT_PROCESSING_FILE."<b>".$curfilename."</b></p>\n");
 608    echo ("<p class=\"smlcentr\">".TEXT_STARTING_LINE.$_REQUEST["start"]."</p>\n");	
 609    skin_close();
 610  }
 611
 612// Check $_REQUEST["foffset"] upon $filesize (can't do it on gzipped files)
 613
 614  if (!$error && !$gzipmode && $_REQUEST["foffset"]>$filesize)
 615  { echo ("<p class=\"error\">".BIGDUMP_IMPORT_MSG_3."</p>\n");
 616    $error=true;
 617  }
 618
 619// Set file pointer to $_REQUEST["foffset"]
 620
 621  if (!$error && ((!$gzipmode && fseek($file, $_REQUEST["foffset"])!=0) || ($gzipmode && gzseek($file, $_REQUEST["foffset"])!=0)))
 622  { echo ("<p class=\"error\">".BIGDUMP_IMPORT_MSG_4.$_REQUEST["foffset"]."</p>\n");
 623    $error=true;
 624  }
 625
 626// Start processing queries from $file
 627
 628  if (!$error)
 629  { $query="";
 630    $queries=0;
 631    $totalqueries=$_REQUEST["totalqueries"];
 632    $linenumber=$_REQUEST["start"];
 633    $querylines=0;
 634    $inparents=false;
 635
 636// Stay processing as long as the $linespersession is not reached or the query is still incomplete
 637
 638    while ($linenumber<$_REQUEST["start"]+$linespersession || $query!="")
 639    {
 640
 641// Read the whole next line
 642
 643      $dumpline = "";
 644      while (!feof($file) && substr ($dumpline, -1) != "\n" && substr ($dumpline, -1) != "\r")
 645      { if (!$gzipmode)
 646          $dumpline .= fgets($file, DATA_CHUNK_LENGTH);
 647        else
 648          $dumpline .= gzgets($file, DATA_CHUNK_LENGTH);
 649      }
 650      if ($dumpline==="") break;
 651
 652
 653// Stop if csv file is used, but $csv_insert_table is not set
 654      if (($csv_insert_table == "") && (preg_match("/(\.csv)$/i",$curfilename)))
 655      {
 656        echo "<p class=\"error\">".sprintf(BIGDUMP_IMPORT_MSG_5, $linenumber)."</p>";
 657        echo '<p>'.sprintf(BIGDUMP_IMPORT_MSG_6, $csv_insert_table);
 658        echo BIGDUMP_IMPORT_MSG_7."</p>\n";
 659        $error=true;
 660        break;
 661      }
 662     
 663// Create an SQL query from CSV line
 664
 665      if (($csv_insert_table != "") && (preg_match("/(\.csv)$/i",$curfilename)))
 666        $dumpline = 'INSERT INTO '.$csv_insert_table.' VALUES ('.$dumpline.');';
 667
 668// Handle DOS and Mac encoded linebreaks (I don't know if it will work on Win32 or Mac Servers)
 669
 670      $dumpline=str_replace("\r\n", "\n", $dumpline);
 671      $dumpline=str_replace("\r", "\n", $dumpline);
 672            
 673// DIAGNOSTIC
 674// echo ("<p>Line $linenumber: $dumpline</p>\n");
 675
 676// Skip comments and blank lines only if NOT in parents
 677
 678      if (!$inparents)
 679      { $skipline=false;
 680        reset($comment);
 681        foreach ($comment as $comment_value)
 682        { if (!$inparents && (trim($dumpline)=="" || strpos ($dumpline, $comment_value) === 0))
 683          { $skipline=true;
 684            break;
 685          }
 686        }
 687        if ($skipline)
 688        { $linenumber++;
 689          continue;
 690        }
 691      }
 692
 693// Remove double back-slashes from the dumpline prior to count the quotes ('\\' can only be within strings)
 694      
 695      $dumpline_deslashed = str_replace ("\\\\","",$dumpline);
 696
 697// Count ' and \' in the dumpline to avoid query break within a text field ending by ;
 698// Please don't use double quotes ('"')to surround strings, it wont work
 699
 700      $parents=substr_count ($dumpline_deslashed, "'")-substr_count ($dumpline_deslashed, "\\'");
 701      if ($parents % 2 != 0)
 702        $inparents=!$inparents;
 703
 704// Add the line to query
 705
 706      $query .= $dumpline;
 707
 708// Don't count the line if in parents (text fields may include unlimited linebreaks)
 709      
 710      if (!$inparents)
 711        $querylines++;
 712      
 713// Stop if query contains more lines as defined by MAX_QUERY_LINES
 714
 715      if ($querylines>MAX_QUERY_LINES)
 716      {
 717        echo ("<p class=\"error\">".sprintf(BIGDUMP_IMPORT_MSG_5, $linenumber)."</p>");
 718        echo ("<p>".sprintf(BIGDUMP_IMPORT_MSG_8, MAX_QUERY_LINES)."</p>\n");
 719        $error=true;
 720        break;
 721      }
 722
 723// Execute query if end of query detected (; as last character) AND NOT in parents
 724
 725      if (preg_match("/;$/",trim($dumpline)) && !$inparents)
 726      { if (!TESTMODE && !mysql_query(trim($query), $dbconnection))
 727        { echo ("<p class=\"error\">".sprintf(BIGDUMP_IMPORT_MSG_9, $linenumber). trim($dumpline)."</p>\n");
 728          echo ("<p>".TEXT_QUERY.trim(nl2br(htmlentities($query)))."</p>\n");
 729          echo ("<p>".TEXT_MYSQL.mysql_error()."</p>\n");
 730          $error=true;
 731          break;
 732        }
 733        $totalqueries++;
 734        $queries++;
 735        $query="";
 736        $querylines=0;
 737      }
 738      $linenumber++;
 739    }
 740  }
 741
 742// Get the current file position
 743
 744  if (!$error)
 745  { if (!$gzipmode) 
 746      $foffset = ftell($file);
 747    else
 748      $foffset = gztell($file);
 749    if (!$foffset)
 750    { echo ("<p class=\"error\">".BIGDUMP_IMPORT_MSG_10."</p>\n");
 751      $error=true;
 752    }
 753  }
 754
 755// Print statistics
 756
 757skin_open();
 758
 759// echo ("<p class=\"centr\"><b>Statistics</b></p>\n");
 760
 761  if (!$error)
 762  { 
 763    $lines_this   = $linenumber-$_REQUEST["start"];
 764    $lines_done   = $linenumber-1;
 765    $lines_togo   = ' ? ';
 766    $lines_tota   = ' ? ';
 767    
 768    $queries_this = $queries;
 769    $queries_done = $totalqueries;
 770    $queries_togo = ' ? ';
 771    $queries_tota = ' ? ';
 772
 773    $bytes_this   = $foffset-$_REQUEST["foffset"];
 774    $bytes_done   = $foffset;
 775    $kbytes_this  = round($bytes_this/1024,2);
 776    $kbytes_done  = round($bytes_done/1024,2);
 777    $mbytes_this  = round($kbytes_this/1024,2);
 778    $mbytes_done  = round($kbytes_done/1024,2);
 779   
 780    if (!$gzipmode)
 781    {
 782      $bytes_togo  = $filesize-$foffset;
 783      $bytes_tota  = $filesize;
 784      $kbytes_togo = round($bytes_togo/1024,2);
 785      $kbytes_tota = round($bytes_tota/1024,2);
 786      $mbytes_togo = round($kbytes_togo/1024,2);
 787      $mbytes_tota = round($kbytes_tota/1024,2);
 788      
 789      $pct_this   = ceil($bytes_this/$filesize*100);
 790      $pct_done   = ceil($foffset/$filesize*100);
 791      $pct_togo   = 100 - $pct_done;
 792      $pct_tota   = 100;
 793
 794      if ($bytes_togo==0) 
 795      { $lines_togo   = '0'; 
 796        $lines_tota   = $linenumber-1; 
 797        $queries_togo = '0'; 
 798        $queries_tota = $totalqueries; 
 799      }
 800
 801      $pct_bar    = "<div style=\"height:15px;width:$pct_done%;background-color:#000080;margin:0px;\"></div>";
 802    }
 803    else
 804    {
 805      $bytes_togo  = ' ? ';
 806      $bytes_tota  = ' ? ';
 807      $kbytes_togo = ' ? ';
 808      $kbytes_tota = ' ? ';
 809      $mbytes_togo = ' ? ';
 810      $mbytes_tota = ' ? ';
 811      
 812      $pct_this    = ' ? ';
 813      $pct_done    = ' ? ';
 814      $pct_togo    = ' ? ';
 815      $pct_tota    = 100;
 816      $pct_bar     = str_replace(' ','&nbsp;','<tt>[         Not available for gzipped files          ]</tt>');
 817    }
 818    
 819    echo ("
 820    <center>
 821    <table width=\"520\" border=\"0\" cellpadding=\"3\" cellspacing=\"1\">
 822    <tr><th class=\"bg4\"> </th><th class=\"bg4\">".TEXT_SESSION."</th><th class=\"bg4\">".TEXT_DONE."</th><th class=\"bg4\">".TEXT_TO_GO."</th><th class=\"bg4\">".TEXT_TOTAL."</th></tr>
 823    <tr><th class=\"bg4\">".TEXT_LINES."</th><td class=\"bg3\">$lines_this</td><td class=\"bg3\">$lines_done</td><td class=\"bg3\">$lines_togo</td><td class=\"bg3\">$lines_tota</td></tr>
 824    <tr><th class=\"bg4\">".TEXT_QUERIES."</th><td class=\"bg3\">$queries_this</td><td class=\"bg3\">$queries_done</td><td class=\"bg3\">$queries_togo</td><td class=\"bg3\">$queries_tota</td></tr>
 825    <tr><th class=\"bg4\">".TEXT_BYTES."</th><td class=\"bg3\">$bytes_this</td><td class=\"bg3\">$bytes_done</td><td class=\"bg3\">$bytes_togo</td><td class=\"bg3\">$bytes_tota</td></tr>
 826    <tr><th class=\"bg4\">".TEXT_KB."</th><td class=\"bg3\">$kbytes_this</td><td class=\"bg3\">$kbytes_done</td><td class=\"bg3\">$kbytes_togo</td><td class=\"bg3\">$kbytes_tota</td></tr>
 827    <tr><th class=\"bg4\">".TEXT_MB."</th><td class=\"bg3\">$mbytes_this</td><td class=\"bg3\">$mbytes_done</td><td class=\"bg3\">$mbytes_togo</td><td class=\"bg3\">$mbytes_tota</td></tr>
 828    <tr><th class=\"bg4\">".TEXT_PERCENT."</th><td class=\"bg3\">$pct_this</td><td class=\"bg3\">$pct_done</td><td class=\"bg3\">$pct_togo</td><td class=\"bg3\">$pct_tota</td></tr>
 829    <tr><th class=\"bg4\">".TEXT_PERCENT_BAR."</th><td class=\"bgpctbar\" colspan=\"4\">$pct_bar</td></tr>
 830    </table>
 831    </center>
 832    \n");
 833
 834// Finish message and restart the script
 835
 836    if ($linenumber < $_REQUEST["start"] + $linespersession)
 837
 838// ****************************** BOF - Mods by PhreeSoft ****************************************
 839//    { echo ("<p class=\"successcentr\">".BIGDUMP_READ_SUCCESS."</p>\n");
 840    { echo ("<p class=\"successcentr\">" . html_button_field('logout', BIGDUMP_IMPORT_MSG_17, 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&page=main&action=logout', 'SSL') . '\'"') . "</p>\n");
 841/*
 842      echo ("<p class=\"centr\">Thank you for using this tool! Please rate <a href=\"http://www.hotscripts.com/Detailed/20922.html\" target=\"_blank\">Bigdump at Hotscripts.com</a></p>\n");
 843      echo ("<p class=\"centr\">You can send me some bucks or euros as appreciation via PayPal. Thank you!</p>\n");
 844?>
 845
 846<!-- Start Paypal donation code -->
 847
 848<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
 849<input type="hidden" name="cmd" value="_xclick" />
 850<input type="hidden" name="business" value="alexey@ozerov.de" />
 851<input type="hidden" name="item_name" value="BigDump Donation" />
 852<input type="hidden" name="no_shipping" value="1" />
 853<input type="hidden" name="no_note" value="0" />
 854<input type="hidden" name="tax" value="0" />
 855<input type="hidden" name="bn" value="PP-DonationsBF" />
 856<input type="hidden" name="lc" value="US" />
 857<input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but04.gif" name="submit" alt="Make payments with PayPal - it's fast, free and secure!" />
 858<img alt="" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
 859</form>
 860<!-- End Paypal donation code -->
 861
 862<?php      
 863*/
 864	  $finished = true;
 865// ****************************** EOF - Mods by PhreeSoft ****************************************
 866      $error = true;
 867    }
 868    else
 869    { if ($delaypersession!=0)
 870// ****************************** BOF - Mods by PhreeSoft ****************************************
 871/*
 872        echo ("<p class=\"centr\">Now I'm <b>waiting $delaypersession milliseconds</b> before starting next session...</p>\n");
 873        if (!$ajax) 
 874          echo ("<script language=\"JavaScript\" type=\"text/javascript\">window.setTimeout('location.href=\"".$_SERVER["PHP_SELF"]."?start=$linenumber&fn=".urlencode($curfilename)."&foffset=$foffset&totalqueries=$totalqueries\";',500+$delaypersession);</script>\n");
 875        echo ("<noscript>\n");
 876        echo ("<p class=\"centr\"><a href=\"".$_SERVER["PHP_SELF"]."?start=$linenumber&amp;fn=".urlencode($curfilename)."&amp;foffset=$foffset&amp;totalqueries=$totalqueries\">Continue from the line $linenumber</a> (Enable JavaScript to do it automatically)</p>\n");
 877        echo ("</noscript>\n");
 878   
 879      echo ("<p class=\"centr\">Press <b><a href=\"".$_SERVER["PHP_SELF"]."\">STOP</a></b> to abort the import <b>OR WAIT!</b></p>\n");
 880*/
 881        echo ("<p class=\"centr\">".sprintf(BIGDUMP_IMPORT_MSG_11, $delaypersession)."</p>\n");
 882        if (!$ajax) 
 883          echo ("<script language=\"JavaScript\" type=\"text/javascript\">window.setTimeout('location.href=\"".html_href_link(FILENAME_DEFAULT, js_get_all_get_params(array('delete','start','fn','foffset','totalqueries')), 'SSL')."&amp;start=$linenumber&amp;fn=".urlencode($curfilename)."&amp;foffset=$foffset&amp;totalqueries=$totalqueries\";',500+$delaypersession);</script>\n");
 884        echo ("<noscript>\n");
 885        echo ("<p class=\"centr\"><a href=\"".html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('delete','start','fn','foffset','totalqueries')), 'SSL') . "&amp;start=$linenumber&amp;fn=".urlencode($curfilename)."&amp;foffset=$foffset&amp;totalqueries=$totalqueries\">".sprintf(BIGDUMP_IMPORT_MSG_12, $linenumber)."</a></p>\n");
 886        echo ("</noscript>\n");
 887   
 888      echo ("<p class=\"centr\">".TEXT_PRESS."<b><a href=\"" . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('delete','start','fn','foffset','totalqueries')), 'SSL') . "\">".TEXT_STOP."</a></b>".BIGDUMP_IMPORT_MSG_13."</p>\n");
 889// ****************************** EOF - Mods by PhreeSoft ****************************************
 890    }
 891  }
 892  else 
 893    echo ("<p class=\"error\">".BIGDUMP_IMPORT_MSG_14."</p>\n");
 894
 895skin_close();
 896
 897}
 898
 899if ($error)
 900// ****************************** BOF - Mods by PhreeSoft ****************************************
 901//  echo ("<p class=\"centr\"><a href=\"".$_SERVER["PHP_SELF"]."\">Start from the beginning</a> (DROP the old tables before restarting)</p>\n");
 902  echo ("<p class=\"centr\"><a href=\"".html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('delete','start','fn','foffset','totalqueries')), 'SSL')."\">".BIGDUMP_IMPORT_MSG_15."</a>".BIGDUMP_IMPORT_MSG_16."</p>\n");
 903// ****************************** EOF - Mods by PhreeSoft ****************************************
 904
 905if ($dbconnection) mysql_close($dbconnection);
 906if ($file && !$gzipmode) fclose($file);
 907else if ($file && $gzipmode) gzclose($file);
 908
 909?>
 910
 911<?php // ************************ BOF - Mods by PhreeSoft ****************************************
 912/*
 913<p class="centr">Â 2003-2009 <a href="mailto:alexey@ozerov.de">Alexey Ozerov</a></p>
 914*/
 915// ****************************** EOF - Mods by PhreeSoft **************************************** ?>
 916
 917</td></tr></table>
 918
 919</center>
 920<?php
 921// ****************************** BOF - Mods by PhreeSoft ****************************************
 922echo '</form>' . chr(10);
 923//echo '</div></div>' . chr(10);
 924// ****************************** EOF - Mods by PhreeSoft ****************************************
 925
 926// *******************************************************************************************
 927// 				AJAX functionality starts here
 928// *******************************************************************************************
 929
 930// Handle special situations (errors, and finish)
 931
 932if ($error) 
 933{
 934  $out1 = ob_get_contents();
 935  ob_end_clean();
 936  echo $out1;
 937  die;
 938}
 939
 940// Creates responses  (XML only or web page)
 941
 942if (($ajax) && isset($_REQUEST['start']))
 943{
 944  if (isset($_REQUEST['ajaxrequest'])) 
 945  {	ob_end_clean();
 946		create_xml_response();
 947		die;
 948	} 
 949	else 
 950	{
 951	  create_ajax_script();	  
 952	}  
 953}
 954ob_flush();
 955
 956// *******************************************************************************************
 957// 				AJAX utilities
 958// *******************************************************************************************
 959
 960function create_xml_response() 
 961{
 962  global $linenumber, $foffset, $totalqueries, $curfilename,
 963				 $lines_this, $lines_done, $lines_togo, $lines_tota,
 964				 $queries_this, $queries_done, $queries_togo, $queries_tota,
 965				 $bytes_this, $bytes_done, $bytes_togo, $bytes_tota,
 966				 $kbytes_this, $kbytes_done, $kbytes_togo, $kbytes_tota,
 967				 $mbytes_this, $mbytes_done, $mbytes_togo, $mbytes_tota,
 968				 $pct_this, $pct_done, $pct_togo, $pct_tota,$pct_bar;
 969
 970	//echo "Content-type: application/xml; charset='iso-8859-1'";
 971	header('Content-Type: application/xml');
 972	header('Cache-Control: no-cache');
 973	/*	
 974	echo '<?xml version="1.0"?>'."\n";
 975	echo '<root>'."\n";
 976	echo 'cos'."\n";
 977	echo '</root>'."\n";
 978	*/
 979	
 980	echo '<?xml version="1.0" encoding="ISO-8859-1"?>';
 981	echo "<root>";
 982	// data - for calculations
 983	echo "<linenumber>";
 984	echo "$linenumber";
 985	echo "</linenumber>";
 986	echo "<foffset>";
 987	echo "$foffset";
 988	echo "</foffset>";
 989	echo "<fn>";
 990	echo '"'.$curfilename.'"';
 991	echo "</fn>";
 992	echo "<totalqueries>";
 993	echo "$totalqueries";
 994	echo "</totalqueries>";
 995	// results - for form update
 996	echo "<elem1>";
 997	echo "$lines_this";
 998	echo "</elem1>";
 999	echo "<elem2>";
1000	echo "$lines_done";
1001	echo "</elem2>";
1002	echo "<elem3>";
1003	echo "$lines_togo";
1004	echo "</elem3>";
1005	echo "<elem4>";
1006	echo "$lines_tota";
1007	echo "</elem4>";
1008	
1009	echo "<elem5>";
1010	echo "$queries_this";
1011	echo "</elem5>";
1012	echo "<elem6>";
1013	echo "$queries_done";
1014	echo "</elem6>";
1015	echo "<elem7>";
1016	echo "$queries_togo";
1017	echo "</elem7>";
1018	echo "<elem8>";
1019	echo "$queries_tota";
1020	echo "</elem8>";
1021	
1022	echo "<elem9>";
1023	echo "$bytes_this";
1024	echo "</elem9>";
1025	echo "<elem10>";
1026	echo "$bytes_done";
1027	echo "</elem10>";
1028	echo "<elem11>";
1029	echo "$bytes_togo";
1030	echo "</elem11>";
1031	echo "<elem12>";
1032	echo "$bytes_tota";
1033	echo "</elem12>";
1034			
1035	echo "<elem13>";
1036	echo "$kbytes_this";
1037	echo "</elem13>";
1038	echo "<elem14>";
1039	echo "$kbytes_done";
1040	echo "</elem14>";
1041	echo "<elem15>";
1042	echo "$kbytes_togo";
1043	echo "</elem15>";
1044	echo "<elem16>";
1045	echo "$kbytes_tota";
1046	echo "</elem16>";
1047	
1048	echo "<elem17>";
1049	echo "$mbytes_this";
1050	echo "</elem17>";
1051	echo "<elem18>";
1052	echo "$mbytes_done";
1053	echo "</elem18>";
1054	echo "<elem19>";
1055	echo "$mbytes_togo";
1056	echo "</elem19>";
1057	echo "<elem20>";
1058	echo "$mbytes_tota";
1059	echo "</elem20>";
1060	
1061	echo "<elem21>";
1062	echo "$pct_this";
1063	echo "</elem21>";
1064	echo "<elem22>";
1065	echo "$pct_done";
1066	echo "</elem22>";
1067	echo "<elem23>";
1068	echo "$pct_togo";
1069	echo "</elem23>";
1070	echo "<elem24>";
1071	echo "$pct_tota";
1072	echo "</elem24>";
1073	
1074	// converting html to normal text
1075	$pct_bar    = htmlentities($pct_bar);	  
1076	echo "<elem_bar>";
1077	echo "$pct_bar";
1078	echo "</elem_bar>";
1079				
1080	echo "</root>";		
1081	
1082}
1083
1084function create_ajax_script() 
1085{
1086  global $linenumber, $foffset, $totalqueries, $delaypersession, $curfilename;
1087	?>
1088	<script type="text/javascript">			
1089
1090	// creates next action url (upload page, or XML response)
1091	function get_url(linenumber,fn,foffset,totalqueries) {
1092// ****************************** BOF - Mods by PhreeSoft ****************************************
1093//		return "<?php echo $_SERVER['PHP_SELF'] ?>?start="+linenumber+"&amp;fn="+fn+"&amp;foffset="+foffset+"&amp;totalqueries="+totalqueries+"&amp;ajaxrequest=true";
1094		return "<?php echo html_href_link(FILENAME_DEFAULT, js_get_all_get_params(array('delete','start','fn','foffset','totalqueries')), 'SSL') ?>&start="+linenumber+"&fn="+fn+"&foffset="+foffset+"&totalqueries="+totalqueries+"&ajaxrequest=true";
1095// ****************************** EOF - Mods by PhreeSoft ****************************************
1096	}
1097	
1098	// extracts text from XML element (itemname must be unique)
1099	function get_xml_data(itemname,xmld) {
1100		return xmld.getElementsByTagName(itemname).item(0).firstChild.data;
1101	}
1102	
1103	// action url (upload page)
1104	var url_request =  get_url(<?php echo $linenumber.',"'.urlencode($curfilename).'",'.$foffset.','.$totalqueries;?>);
1105	var http_request = false;
1106	
1107	function makeRequest(url) {
1108		http_request = false;
1109		if (window.XMLHttpRequest) { 
1110		// Mozilla,...
1111			http_request = new XMLHttpRequest();
1112			if (http_request.overrideMimeType) {
1113				http_request.overrideMimeType("text/xml");
1114			}
1115		} else if (window.ActiveXObject) { 
1116		// IE
1117			try {
1118				http_request = new ActiveXObject("Msxml2.XMLHTTP");
1119			} catch(e) {
1120				try {
1121					http_request = new ActiveXObject("Microsoft.XMLHTTP");
1122				} catch(e) {}
1123			}
1124		}
1125		if (!http_request) {
1126				alert("Cannot create an XMLHTTP instance");
1127				return false;
1128		}
1129		http_request.onreadystatechange = server_response;
1130		http_request.open("GET", url, true);
1131		http_request.send(null);
1132	}
1133	
1134	function server_response() 
1135	{
1136
1137	  // waiting for correct response
1138	  if (http_request.readyState != 4)
1139			return;
1140	  if (http_request.status != 200) {
1141	    alert("Page unavailable, or wrong url!")
1142			return;
1143		}
1144		
1145		// r = xml response
1146		var r = http_request.responseXML;
1147		
1148		//if received not XML but HTML with new page to show
1149		if (r.getElementsByTagName('root').length == 0) {                   	//*
1150			var text = http_request.responseText;
1151			document.open();
1152			document.write(text);		
1153			document.close();	
1154			return;		
1155		}
1156		
1157		// update "Starting from line: "
1158		document.getElementsByTagName('p').item(1).innerHTML = 
1159			"Starting from line: " + 
1160			   r.getElementsByTagName('linenumber').item(0).firstChild.nodeValue;
1161		
1162		// update table with new values
1163		for(i = 1; i <= 24; i++) {						
1164			document.getElementsByTagName('td').item(i).firstChild.data = 
1165				get_xml_data('elem'+i,r);
1166		}				
1167		
1168		// update color bar
1169		document.getElementsByTagName('td').item(25).innerHTML = 
1170			r.getElementsByTagName('elem_bar').item(0).firstChild.nodeValue;
1171			 
1172		// action url (XML response)	 
1173		url_request =  get_url(
1174			get_xml_data('linenumber',r),
1175			get_xml_data('fn',r),
1176			get_xml_data('foffset',r),
1177			get_xml_data('totalqueries',r));
1178		
1179		// ask for XML response	
1180		window.setTimeout("makeRequest(url_request)",500+<?php echo $delaypersession; ?>);
1181	}
1182	// ask for upload page
1183	window.setTimeout("makeRequest(url_request)",500+<?php echo $delaypersession; ?>);
1184	</script>
1185	<?php
1186}
1187
1188?>
1189
1190</body>
1191</html>
