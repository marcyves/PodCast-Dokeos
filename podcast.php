<?php

/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004-2009 Dokeos S.P.R.L

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact address: Dokeos, rue du Corbeau, 108, B-1030 Brussels, Belgium
	Mail: info@dokeos.com
==============================================================================
*/

/**
*	@package dokeos.studentpublications
* 	@author Marc Augier, Andy Martel - original version
* 	@author CERAM Business School
*  	@version $Id: podcast.php 15462 2008-05-27 16:34:55Z juliomontoya $
*
* 	@todo refactor more code into functions, use quickforms, coding standards, ...
*/
/**
==============================================================================
 * 	PODCAST MODULE
 *
 *
 * GOALS
 * *****
 * Allow the teacher to add audio and video files to a course and build a RSS feed
 * visible on the course website.
 *
 * The script does 6 things:
 *
 * 	1. Upload audio and video files
 * 	2. Give them a name and a description
 * 	3. Modify data about files
 * 	4. Delete link to files and simultaneously remove them
 * 	5. Show file list to students and visitors
 *  6. Create a RSS feed
 *
 * All files are sent to the address /$_configuration['root_sys']/$currentCourseID/podcast/
 * where $currentCourseID is the web directory for the course and $_configuration['root_sys']
 * usually /var/www/html
 *
==============================================================================
 */

/*
==============================================================================
		INIT SECTION
==============================================================================
*/

// name of the language file that needs to be included
$language_file = array (
	'podcast',
	'document',
	'admin'
);

// @todo why is this needed?
//session
if (isset ($_GET['id_session'])) {
	$_SESSION['id_session'] = Database::escape_string($_GET['id_session']);
}

$htmlHeadXtra[] = '<script>

function updateDocumentTitle(value){

	var temp = value.indexOf("/");
	
	//linux path
	if(temp!=-1){
		var temp=value.split("/");
	}
	else{
		var temp=value.split("\\\");
	}
	
	document.getElementById("file_upload").value=temp[temp.length-1];
}
</script>
';

/*
-----------------------------------------------------------
	Including necessary files
-----------------------------------------------------------
*/
require ('../inc/global.inc.php');

// Section (for the tabs)
$this_section = SECTION_COURSES;

require_once (api_get_path(LIBRARY_PATH) . "course.lib.php");
require_once (api_get_path(LIBRARY_PATH) . "debug.lib.inc.php");
require_once (api_get_path(LIBRARY_PATH) . "events.lib.inc.php");
require_once (api_get_path(LIBRARY_PATH) . "security.lib.php");
require_once(api_get_path(LIBRARY_PATH) . "formvalidator/FormValidator.class.php");
require_once ('podcast.lib.php');

/*
-----------------------------------------------------------
	Table definitions
-----------------------------------------------------------
*/
$main_course_table = Database :: get_main_table(TABLE_MAIN_COURSE);
$work_table = Database :: get_course_table(TABLE_PODCAST);
$iprop_table = Database :: get_course_table(TABLE_ITEM_PROPERTY);
/*
-----------------------------------------------------------
	Constants and variables
-----------------------------------------------------------
*/

$tool_name = "podcast";
$user_id = api_get_user_id();
$course_code = $_course['sysCode'];
$is_course_member = $is_courseMember || api_is_platform_admin();
$currentCourseRepositorySys = api_get_path(SYS_COURSE_PATH) . $_course["path"] . "/";
$currentCourseRepositoryWeb = api_get_path(WEB_COURSE_PATH) . $_course["path"] . "/";
$currentUserFirstName = $_user['firstName'];
$currentUserLastName = $_user['lastName'];

$authors = htmlentities(Database :: escape_string($_POST['authors']));
$delete = Database :: escape_string($_REQUEST['delete']);
$description = htmlentities(Database :: escape_string($_REQUEST['description']));
$display_tool_options = $_REQUEST['display_tool_options'];
$display_upload_form = $_REQUEST['display_upload_form'];
$edit = Database :: escape_string($_REQUEST['edit']);
$make_invisible = Database :: escape_string($_REQUEST['make_invisible']);
$make_visible = Database :: escape_string($_REQUEST['make_visible']);
$origin = Security :: remove_XSS($_REQUEST['origin']);
$submitGroupWorkUrl = Security :: remove_XSS($_REQUEST['submitGroupWorkUrl']);
$title = Database :: escape_string($_REQUEST['title']);
$uploadvisibledisabled = Database :: escape_string($_REQUEST['uploadvisibledisabled']);
$id = strval(intval($_REQUEST['id']));

//directories management
$sys_course_path = api_get_path(SYS_COURSE_PATH);
$course_dir = $sys_course_path . $_course['path'];
$base_work_dir = $course_dir . '/podcast';
$http_www = api_get_path('WEB_COURSE_PATH') . $_course['path'] . '/podcast';
$cur_dir_path = '';
if (isset ($_GET['curdirpath']) && $_GET['curdirpath'] != '') {
	//$cur_dir_path = preg_replace('#[\.]+/#','',$_GET['curdirpath']); //escape '..' hack attempts
	//now using common security approach with security lib
	$in_course = Security :: check_abs_path($base_work_dir . '/' . $_GET['curdirpath'], $base_work_dir);
	if (!$in_course) {
		$cur_dir_path = "/";
	} else {
		$cur_dir_path = $_GET['curdirpath'];
	}
}
elseif (isset ($_POST['curdirpath']) && $_POST['curdirpath'] != '') {
	//$cur_dir_path = preg_replace('#[\.]+/#','/',$_POST['curdirpath']); //escape '..' hack attempts
	//now using common security approach with security lib
	$in_course = Security :: check_abs_path($base_work_dir . '/' . $_POST['curdirpath'], $base_work_dir);
	if (!$in_course) {
		$cur_dir_path = "/";
	} else {
		$cur_dir_path = $_POST['curdirpath'];
	}
} else {
	$cur_dir_path = '/';
}
if ($cur_dir_path == '.') {
	$cur_dir_path = '/';
}
$cur_dir_path_url = urlencode($cur_dir_path);

//prepare a form of path that can easily be added at the end of any url ending with "work/"
$my_cur_dir_path = $cur_dir_path;
if ($my_cur_dir_path == '/')
 {
	$my_cur_dir_path = '';
}
elseif (substr($my_cur_dir_path, -1, 1) != '/') 
{
	$my_cur_dir_path = $my_cur_dir_path . '/';
}
/*
-----------------------------------------------------------
	Configuration settings
-----------------------------------------------------------
*/
$link_target_parameter = ""; //or e.g. "target=\"_blank\"";
$always_show_tool_options = false;
$always_show_upload_form = false;

if ($always_show_tool_options) {
	$display_tool_options = true;
}
if ($always_show_upload_form) {
	$display_upload_form = true;
}
api_protect_course_script(true);

/*
-----------------------------------------------------------
	More init stuff
-----------------------------------------------------------
*/

if (isset ($_POST['cancelForm']) && !empty ($_POST['cancelForm'])) {
	header('Location: ' . api_get_self() . "?origin=$origin");
	exit ();
}

if ($_POST['submitWork'] || $submitGroupWorkUrl) {
	// these libraries are only used for upload purpose
	// so we only include them when necessary
	include_once (api_get_path(INCLUDE_PATH) . "lib/fileUpload.lib.php");
	include_once (api_get_path(INCLUDE_PATH) . "lib/fileDisplay.lib.php"); // need format_url function
}

// If the POST's size exceeds 15M (default value in php.ini) the $_POST array is emptied
// If that case happens, we set $submitWork to 1 to allow displaying of the error message
// The redirection with header() is needed to avoid apache to show an error page on the next request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !sizeof($_POST)) {
	if (strstr($_SERVER['REQUEST_URI'], '?')) {
		header('Location: ' . $_SERVER['REQUEST_URI'] . '&submitWork=1');
		exit ();
	} else {
		header('Location: ' . $_SERVER['REQUEST_URI'] . '?submitWork=1');
		exit ();
	}
}
//toolgroup comes from group. the but of tis variable is to limit post to the group of the student
if (!api_is_course_admin()) {
	if (!empty ($_GET['toolgroup'])) {
		$toolgroup = Database::escape_string($_GET['toolgroup']);
		api_session_register('toolgroup');
	}
}
/*
-----------------------------------------------------------
	Header
-----------------------------------------------------------
*/

if ($origin != 'learnpath') {
	$interbreadcrumb[] = array (
		'url' => $url_dir,
		'name' => 'PodCast');
//		'name' => get_lang('StudentPublications'));

	//if (!$display_tool_options  && !$display_upload_form)
	//{
	//------interbreadcrumb for the current directory root path
	$dir_array = explode("/", $cur_dir_path);
	$array_len = count($dir_array);

	if ($array_len > 0) 
	{
		$url_dir = 'podcast.php?&curdirpath=/';
		$interbreadcrumb[] = array (
			'url' => $url_dir,
			'name' => get_lang('HomeDirectory'));
	}

	$dir_acum = '';
	for ($i = 0; $i < $array_len; $i++) {
		$url_dir = 'podcast.php?&curdirpath=' . $dir_acum . $dir_array[$i];
		$interbreadcrumb[] = array (
			'url' => $url_dir,
			'name' => $dir_array[$i]
		);
		$dir_acum .= $dir_array[$i] . '/';
	}
	//	}

	if ($display_upload_form) {
		//$tool_name = get_lang("UploadADocument");
		//$interbreadcrumb[] = array ("url" => "work.php", "name" => get_lang('StudentPublications'));
		$interbreadcrumb[] = array (
			"url" => "podcast.php",
			"name" => get_lang('podcastUploadADocument'));
	}

	if ($display_tool_options) {
		//$tool_name = get_lang("EditToolOptions");
		//$interbreadcrumb[] = array ("url" => "work.php", "name" => get_lang('StudentPublications'));
		$interbreadcrumb[] = array (
			"url" => "podcast.php",
			"name" => get_lang('EditToolOptions'));
	}
	//--------------------------------------------------
	Display :: display_header(null);
}
else 
{
	//we are in the learnpath tool
	include api_get_path(INCLUDE_PATH) . 'reduced_header.inc.php';
}

//stats
event_access_tool(TOOL_STUDENTPUBLICATION);

$is_allowed_to_edit = api_is_allowed_to_edit(); //has to come after display_tool_view_option();
//api_display_tool_title($tool_name);

/*
==============================================================================
		MAIN CODE
==============================================================================
*/

if (isset ($_POST['changeProperties'])) 
{
	$query = "UPDATE " . $main_course_table . " SET show_score='" . $uploadvisibledisabled . "' WHERE code='" . $_course['sysCode'] . "'";
	api_sql_query($query, __FILE__, __LINE__);

	$_course['show_score'] = $uploadvisibledisabled;
} 
else 
{
	$query = "SELECT * FROM " . $main_course_table . " WHERE code=\"" . $_course['sysCode'] . "\"";
	$result = api_sql_query($query, __FILE__, __LINE__);
	$row = mysql_fetch_array($result);
	$uploadvisibledisabled = $row["show_score"];
}

/*
-----------------------------------------------------------
	Introduction section
-----------------------------------------------------------
*/

Display :: display_introduction_section(TOOL_STUDENTPUBLICATION);

/*
-----------------------------------------------------------
	COMMANDS SECTION (reserved for course administrator)
-----------------------------------------------------------
*/

if (api_is_allowed_to_edit()) 
{
	/*-------------------------------------------
				TOGGLE PUBLISH PODCAST COMMAND
	-----------------------------------------*/
	if ($_REQUEST['toggle_publish']) 
	{
		toggle_publish($_course['sysCode'],$_REQUEST['new_status']);
	}
	/*-------------------------------------------
				DELETE PODCAST COMMAND
	-----------------------------------------*/
	if ($delete) 
	{
		if ($delete == "all") 
		{
			$queryString1 = "SELECT url FROM " . $work_table . "";
			$queryString2 = "DELETE FROM  " . $work_table . "";
		} 
		else
		{
			$queryString1 = "SELECT url FROM  " . $work_table . "  WHERE id = '$delete'";
			$queryString2 = "DELETE FROM  " . $work_table . "  WHERE id='$delete'";
		}

		$result1 = api_sql_query($queryString1, __FILE__, __LINE__);
		$result2 = api_sql_query($queryString2, __FILE__, __LINE__);
		
		
		if ($result1) 
		{
			while ($thisUrl = Database::fetch_array($result1)) {
				// check the url really points to a file in the podcast area
				// (some podcast links can come from groups area...)
				//if (substr (dirname($thisUrl['url']), -4) == "podcast")
				if (strstr($thisUrl['url'], "podcast/$my_cur_dir_path") !== false) 
				{
					@ unlink($currentCourseRepositorySys . $thisUrl['url']);
				}
			}			
		}	
		xml_generation($_course['path'], $currentCourseRepositorySys, $currentCourseRepositoryWeb, $_FILES['file']['type']);
	}

	/*-------------------------------------------
	           EDIT COMMAND PODCAST COMMAND
	  -----------------------------------------*/

	if ($edit) 
	{		
		$sql = "SELECT * FROM  " . $work_table . "  WHERE id='" . $edit . "'";
		$result = api_sql_query($sql, __FILE__, __LINE__);

		if ($result) 
		{
			$row = mysql_fetch_array($result);
			$workTitle = $row['title'];
			$workAuthor = $row['author'];
			$workDescription = $row['description'];
			$workUrl = $row['url'];
		}
		xml_generation($_course['path'], $currentCourseRepositorySys, $currentCourseRepositoryWeb, $_FILES['file']['type']);
	}

	/*-------------------------------------------
		MAKE INVISIBLE PODCAST COMMAND
	  -----------------------------------------*/

	if ($make_invisible) 
	{
		if ($make_invisible == "all") 
		{
			$sql = "ALTER TABLE " . $work_table . "
						        CHANGE accepted accepted TINYINT(1) DEFAULT '0'";

			api_sql_query($sql, __FILE__, __LINE__);

			$sql = "UPDATE  " . $work_table . "
						        SET accepted = 0";

			api_sql_query($sql, __FILE__, __LINE__);
		} 
		else 
		{
			$sql = "UPDATE  " . $work_table . "
						        SET accepted = 0
								WHERE id = '" . $make_invisible . "'";

			api_sql_query($sql, __FILE__, __LINE__);
		}
		xml_generation($_course['path'], $currentCourseRepositorySys, $currentCourseRepositoryWeb, $_FILES['file']['type']);		
	}

	/*-------------------------------------------
		MAKE VISIBLE PODCAST COMMAND
	  -----------------------------------------*/

	if ($make_visible) 
	{ 
		if ($make_visible == "all") 
		{
			$sql = "ALTER TABLE  " . $work_table . "
						        CHANGE accepted accepted TINYINT(1) DEFAULT '1'";

			api_sql_query($sql, __FILE__, __LINE__);

			$sql = "UPDATE  " . $work_table . "
						        SET accepted = 1";

			api_sql_query($sql, __FILE__, __LINE__);

		} 
		else 
		{
			$sql = "UPDATE  " . $work_table . "
						        SET accepted = 1
								WHERE id = '" . $make_visible . "'";

			api_sql_query($sql, __FILE__, __LINE__);
		}
				
		// update all the parents in the table item propery		
		$list_id=get_parent_directories($my_cur_dir_path);
		for ($i = 0; $i < count($list_id); $i++)
		{
			api_item_property_update($_course, /*'work'*/'podcast', $list_id[$i], 'FolderUpdated', $user_id);								
		}
		xml_generation($_course['path'], $currentCourseRepositorySys, $currentCourseRepositoryWeb, $_FILES['file']['type']);	
	}
}
/*
-----------------------------------------------------------
	COMMANDS SECTION (reserved for others - check they're authors each time)
-----------------------------------------------------------
*/
else 
{
	$iprop_table = Database :: get_course_table(TABLE_ITEM_PROPERTY);
	$user_id = api_get_user_id();
	
	/*-------------------------------------------
				DELETE PODCAST COMMAND
	-----------------------------------------*/
	if ($delete) 
	{
		if ($delete == "all") 
		{
			/*not authorized to this user */
		} 
		else 
		{
			//Get the author ID for that document from the item_property table
			$author_sql = "SELECT * FROM $iprop_table WHERE tool = 'podcast' AND insert_user_id='$user_id' AND ref=" . mysql_real_escape_string($delete);
			$author_qry = api_sql_query($author_sql, __FILE__, __LINE__);
			
			if (Database :: num_rows($author_qry) == 1)
			{
				//we found the current user is the author
				$queryString1 = "SELECT url FROM  " . $work_table . "  WHERE id = '$delete'";
				$queryString2 = "DELETE FROM  " . $work_table . "  WHERE id='$delete'";
							
				$result1 = api_sql_query($queryString1, __FILE__, __LINE__);
				$result2 = api_sql_query($queryString2, __FILE__, __LINE__);
				
				if ($result1) 
				{
					api_item_property_update($_course, 'podcast', $delete, 'DocumentDeleted', $user_id);
					while ($thisUrl = mysql_fetch_array($result1)) 
					{
						// check the url really points to a file in the work area
						// (some work links can come from groups area...)
						if (substr(dirname($thisUrl['url']), -4) == "podcast")
						{
							@ unlink($currentCourseRepositorySys . "podcast/" . $thisWork);
						}
					}
				}
			}
		}
	}
	/*-------------------------------------------
	           EDIT COMMAND PODCAST COMMAND
	  -----------------------------------------*/
	  
	if ($edit) 
	{		
		//Get the author ID for that document from the item_property table
		$author_sql = "SELECT * FROM $iprop_table WHERE tool = 'podcast' AND insert_user_id='$user_id' AND ref=" . $edit;
		$author_qry = api_sql_query($author_sql, __FILE__, __LINE__);
		if (Database :: num_rows($author_qry) == 1) 
		{
			//we found the current user is the author
			$sql = "SELECT * FROM  " . $work_table . "  WHERE id='" . $edit . "'";
			$result = api_sql_query($sql, __FILE__, __LINE__);
			if ($result)
			 {
				$row = mysql_fetch_array($result);
				$workTitle = $row['title'];
				$workAuthor = $row['author'];
				$workDescription = $row['description'];
				$workUrl = $row['url'];
			}
		}
	}
}

/*
==============================================================================
		FORM SUBMIT PROCEDURE
==============================================================================
*/
//echo $_FILES['file']['type'];
$error_message = "";

$check = Security :: check_token('post'); //check the token inserted into the form
if ($_POST['submitWork'] && $is_course_member && $check)
{
	if ($_FILES['file']['type'] =='audio/mpeg' || 
		$_FILES['file']['type'] =='video/mov'  ||
		$_FILES['file']['type'] =='video/x-m4v'  ||
		$_FILES['file']['type'] =='video/mp4'  ||
		$_FILES['file']['type'] =='application/octet-stream'  ||
		$_FILES['file']['type'] =='video/quicktime' ||
		$_FILES['file']['type'] =='video/avi' ||
		$_FILES['file']['type'] =='video/mpeg' ||
		$_FILES['file']['type'] =='video/x-ms-wmv'){
	if ($_FILES['file']['size']) 
	{
		$updir = $currentCourseRepositorySys . 'podcast/'; //directory path to upload

		// Try to add an extension to the file if it has'nt one
		//echo $_FILES['file']['type'];
		$new_file_name = add_ext_on_mime(stripslashes($_FILES['file']['name']), $_FILES['file']['type']);

		// Replace dangerous characters
		$new_file_name = replace_dangerous_char($new_file_name, 'strict');

		// Transform any .php file in .phps fo security
		$new_file_name = php2phps($new_file_name);
		//filter extension
		if (!filter_extension($new_file_name)) 
		{
			Display :: display_error_message(get_lang('UplUnableToSaveFileFilteredExtension'));
			$succeed = false;
		} 
		else 
		{
			if (!$title) 
			{
				$title = $_FILES['file']['name'];
			}

			if (!$authors)
			{
				$authors = $currentUserFirstName . " " . $currentUserLastName;
			}

			// compose a unique file name to avoid any conflict

			$new_file_name = uniqid('') . $new_file_name;

			if (isset ($_SESSION['toolgroup'])) 
			{
				$post_group_id = $_SESSION['toolgroup'];
			} 
			else 
			{
				$post_group_id = '0';
			}
			
			//if we come from the group tools the groupid will be saved in $work_table

			@move_uploaded_file($_FILES['file']['tmp_name'], $updir . $my_cur_dir_path . $new_file_name);

			$url = "podcast/" . $my_cur_dir_path . $new_file_name;
						
			$result = api_sql_query("SHOW FIELDS FROM " . $work_table . " LIKE 'sent_date'", __FILE__, __LINE__);
			
			if (!mysql_num_rows($result)) 
			{
				api_sql_query("ALTER TABLE " . $work_table . " ADD sent_date DATETIME NOT NULL");
			}			
			$current_date = date('Y-m-d H:i:s');
						
			$sql_add_publication = "INSERT INTO " . $work_table . " SET " .
									       "url         = '" . $url . "',
									       title       = '" . $title . "',
						                   description = '" . $description . "',
						                   author      = '" . $authors . "',
										   active		= '" . $active . "',
										   accepted		= '" . (!$uploadvisibledisabled) . "',
										   post_group_id = '" . $post_group_id . "',
										   sent_date	=  ' ".$current_date ."' ,
										   filetype     = '".$_FILES['file']['type']."' ";

			api_sql_query($sql_add_publication, __FILE__, __LINE__);

			$Id = mysql_insert_id();
			api_item_property_update($_course, 'podcast', $Id, 'DocumentAdded', $user_id);
			$succeed = true;
			
			// update all the parents in the table item propery
			$list_id=get_parent_directories($my_cur_dir_path);						
			for ($i = 0; $i < count($list_id); $i++)
			{
				api_item_property_update($_course, 'podcast', $list_id[$i], 'FolderUpdated', $user_id);								
			}	
			
		}
	}
	elseif ($newWorkUrl) 
	{	
		/*
		 * SPECIAL CASE ! For a work coming from another area (i.e. groups)
		 */		 
		$url = str_replace('../../' . $_course['path'] . '/', '', $newWorkUrl);

		if (!$title)
		{		
			$title = basename($workUrl);
		}

		$result = api_sql_query("SHOW FIELDS FROM " . $work_table . " LIKE 'sent_date'", __FILE__, __LINE__);

		if (!Database::num_rows($result)) {
			api_sql_query("ALTER TABLE " . $work_table . " ADD sent_date DATETIME NOT NULL");
		}

			$sql = "INSERT INTO  " . $work_table . "
				        SET url         = '" . $url . "',
				            title       = '" . $title . "',
				            description = '" . $description . "',
				            author      = '" . $authors . "',
				            sent_date     = NOW()";

		api_sql_query($sql, __FILE__, __LINE__);

		$insertId = Database::insert_id();
		api_item_property_update($_course, 'podcast', $insertId, 'DocumentAdded', $user_id);
		$succeed = true;
				
		// update all the parents in the table item propery
		$list_id=get_parent_directories($my_cur_dir_path);						
		for ($i = 0; $i < count($list_id); $i++)
		{
			api_item_property_update($_course, 'podcast', $list_id[$i], 'FolderUpdated', $user_id);								
		}		
	}

	/*
	 * SPECIAL CASE ! For a work edited
	 */

	else 
	{
		//Get the author ID for that document from the item_property table
		$is_author = false;
		$author_sql = "SELECT * FROM $iprop_table WHERE tool = 'podcast' AND insert_user_id='$user_id' AND ref=" . mysql_real_escape_string($id);
		$author_qry = api_sql_query($author_sql, __FILE__, __LINE__);
		if (Database :: num_rows($author_qry) == 1) {
			$is_author = true;
		}

		if ($id && ($is_allowed_to_edit or $is_author)) 
		{
			if (!$title) 
			{
				$title = basename($newWorkUrl);
			}

			$sql = "UPDATE  " . $work_table . "
						        SET	title       = '" . $title . "',
						            description = '" . $description . "',
						            author      = '" . $authors . "'
						        WHERE id        = '" . $id . "'";

			api_sql_query($sql, __FILE__, __LINE__);
			$insertId = $id;
			api_item_property_update($_course, 'podcast', $insertId, 'DocumentUpdated', $user_id);
			$succeed = true;			
		} 
		else 
		{
			$error_message = get_lang('TooBig');
		}
	}
	}else{
		echo "<h3><p style=\"font-weight:bold\"><img src='../img/warning.jpg'> " . get_lang("badPodcastType") ." ". $_FILES['file']['type'] . "</p></h3>";
	}

	Security :: clear_token(); //clear the token to prevent re-executing the request with back button
	xml_generation($_course['path'], $currentCourseRepositorySys, $currentCourseRepositoryWeb, $_FILES['file']['type']);
}

if ($_POST['submitWork'] && $succeed && !$id) //last value is to check this is not "just" an edit
{

	//YW Tis part serve to send a e-mail to the tutors when a new file is sent
	$send = api_get_course_setting('email_alert_manager_on_new_doc');
	
	if ($send > 0) 
	{
		// Lets predefine some variables. Be sure to change the from address!
		$table_course_user = Database :: get_main_table(TABLE_MAIN_COURSE_USER);
		$table_user = Database :: get_main_table(TABLE_MAIN_USER);
		$table_session = Database :: get_main_table(TABLE_MAIN_SESSION);
		$table_session_course = Database :: get_main_table(TABLE_MAIN_SESSION_COURSE);

		$emailto = array ();
		if (empty ($_SESSION['id_session'])) {
			$sql_resp = 'SELECT u.email as myemail FROM ' . $table_course_user . ' cu, ' . $table_user . ' u WHERE cu.course_code = ' . "'" . api_get_course_id() . "'" . ' AND cu.status = 1 AND u.user_id = cu.user_id';
			$res_resp = api_sql_query($sql_resp, __FILE__, __LINE__);
			while ($row_email = Database :: fetch_array($res_resp)) {
				if (!empty ($row_email['myemail'])) {
					$emailto[$row_email['myemail']] = $row_email['myemail'];
				}
			}
		} else {
			// coachs of the session
			$sql_resp = 'SELECT user.email as myemail 
									FROM ' . $table_session . ' session
									INNER JOIN ' . $table_user . ' user
										ON user.user_id = session.id_coach
									WHERE session.id = ' . intval($_SESSION['id_session']);
			$res_resp = api_sql_query($sql_resp, __FILE__, __LINE__);
			while ($row_email = Database :: fetch_array($res_resp)) {
				if (!empty ($row_email['myemail'])) {
					$emailto[$row_email['myemail']] = $row_email['myemail'];
				}
			}

			//coach of the course
			$sql_resp = 'SELECT user.email as myemail 
									FROM ' . $table_session_course . ' session_course
									INNER JOIN ' . $table_user . ' user
										ON user.user_id = session_course.id_coach
									WHERE session_course.id_session = ' . intval($_SESSION['id_session']);
			$res_resp = api_sql_query($sql_resp, __FILE__, __LINE__);
			while ($row_email = Database :: fetch_array($res_resp)) {
				if (!empty ($row_email['myemail'])) {
					$emailto[$row_email['myemail']] = $row_email['myemail'];
				}
			}

		}
		
		if (count($emailto) > 0) 
		{
			$emailto = implode(',', $emailto);
			$emailfromaddr = get_setting('emailAdministrator');
			$emailfromname = get_setting('siteName');
			$emailsubject = "[" . get_setting('siteName') . "] ";

			// The body can be as long as you wish, and any combination of text and variables

			//$emailbody=get_lang('SendMailBody').' '.api_get_path(WEB_CODE_PATH)."work/work.php?".api_get_cidreq()." ($title)\n\n".get_setting('administratorName')." ".get_setting('administratorSurname')."\n". get_lang('Manager'). " ".get_setting('siteName')."\nT. ".get_setting('administratorTelephone')."\n" .get_lang('Email') ." : ".get_setting('emailAdministrator');			
			$emailbody = get_lang('SendMailBody').' '.api_get_path(WEB_CODE_PATH)."podcast/podcast.php?".api_get_cidreq()."&amp;curdirpath=".$my_cur_dir_path." (" . stripslashes($title) . ")\n\n" . get_setting('administratorName') . " " . get_setting('administratorSurname') . "\n" . get_lang('Manager') . " " . get_setting('siteName') . "\n" . get_lang('Email') . " : " . get_setting('emailAdministrator');

			// Here we are forming one large header line
			// Every header must be followed by a \n except the last
			$emailheaders = "From: " . get_setting('administratorName') . " " . get_setting('administratorSurname') . " <" . get_setting('emailAdministrator') . ">\n";
			$emailheaders .= "Reply-To: " . get_setting('emailAdministrator');

			// Because I predefined all of my variables, this api_send_mail() function looks nice and clean hmm?
			@ api_send_mail($emailto, $emailsubject, $emailbody, $emailheaders);
		}
	}
	$message = get_lang('podcastDocAdd');
	if ($uploadvisibledisabled && !$is_allowed_to_edit) {
		$message .= "<br />" . get_lang('_doc_unvisible') . "<br />";
	}

	//stats
	if (!$Id) {
		$Id = $insertId;
	}
	event_upload($Id);
	$submit_success_message = $message . "<br />\n";
	Display :: display_normal_message($submit_success_message, false);
}

//{
/*=======================================
	 Display links to upload form and tool options
  =======================================
*/
display_action_links($currentCourseRepositoryWeb, $cur_dir_path, $always_show_tool_options, $always_show_upload_form);

/*=======================================
	 Display form to upload a file
  =======================================*/

if ($is_course_member) 
{
	if ($display_upload_form || $edit) 
	{
		$token = Security :: get_token(); //generate token to be used to check validity of request
		if ($edit) 
		{
			//Get the author ID for that document from the item_property table
			$is_author = false;
			$author_sql = "SELECT * FROM $iprop_table WHERE tool = 'podcast' AND insert_user_id='$user_id' AND ref=" . $edit;
			$author_qry = api_sql_query($author_sql, __FILE__, __LINE__);
			if (Database :: num_rows($author_qry) == 1) {
				$is_author = true;
			}
		}

		require_once (api_get_path(LIBRARY_PATH) . 'formvalidator/FormValidator.class.php');
		require_once (api_get_path(LIBRARY_PATH) . 'fileDisplay.lib.php');

		$form = new FormValidator('form', 'POST', api_get_self() . "?curdirpath=" . Security :: remove_XSS($cur_dir_path) . "&origin=$origin", '', 'enctype="multipart/form-data"');

		if (!empty ($error_message))
			Display :: display_error_message($error_message);

		if ($submitGroupWorkUrl) // For user comming from group space to publish his work
		{
			$realUrl = str_replace($_configuration['root_sys'], $_configuration['root_web'], str_replace("\\", "/", realpath($submitGroupWorkUrl)));
			$form->addElement('hidden', 'newWorkUrl', $submitGroupWorkUrl);
			$text_document = & $form->addElement('text', 'document', get_lang("Document"));
			$defaults["document"] = '<a href="' . format_url($submitGroupWorkUrl) . '">' . $realUrl . '</a>';
			$text_document->freeze();
		}

		elseif ($edit && ($is_allowed_to_edit or $is_author)) 
		{
			$workUrl = $currentCourseRepositoryWeb . $workUrl;
			$form->addElement('hidden', 'id', $edit);

			$html = '<div class="row">
								<div class="label">' . get_lang("podcast") . '
								</div>
								<div class="formw">
									<a href="' . $workUrl . '">' . $workUrl . '</a>
								</div>
							</div>';
			$form->addElement('html', $html);
		} 
		else // else standard upload option
		{
			$form->addElement('file', 'file', get_lang('DownloadFile'), 'size="40" onchange="updateDocumentTitle(this.value)"');
		}

		$titleWork = $form->addElement('text', 'title', get_lang("TitleWork"), 'id="file_upload"  style="width: 350px;"');
		$defaults["title"] = ($edit ? stripslashes($workTitle) : stripslashes($title));

		$titleAuthors = $form->addElement('text', 'authors', get_lang("Authors"), 'style="width: 350px;"');

		if (empty ($authors)) 
		{
			$authors = $_user['firstName'] . " " . $_user['lastName'];
		}

		$defaults["authors"] = ($edit ? stripslashes($workAuthor) : stripslashes($authors));

		$titleAuthors = $form->addElement('textarea', 'description', get_lang("Description"), 'style="width: 350px; height: 60px;"');
		$defaults["description"] = ($edit ? stripslashes($workDescription) : stripslashes($description));

		$form->addElement('hidden', 'active', 1);
		$form->addElement('hidden', 'accepted', 1);
		$form->addElement('hidden', 'sec_token', $token);
		
		// fix the Ok button when we see the tool in the learn path
		if ($origin== 'learnpath')
		{
			$form->addElement('html', '<div style="margin-left:137px">');		
			$form->addElement('submit', 'submitWork', get_lang('Ok'));		
			$form->addElement('html', '</div>');
		}
		else
		{
			$form->addElement('submit', 'submitWork', get_lang('Ok'));
		}
		
		if ($_POST['submitWork'] || $edit) 
		{
			$form->addElement('submit', 'cancelForm', get_lang('Cancel'));
		}

		$form->add_real_progress_bar('uploadWork', 'DownloadFile');
		$form->setDefaults($defaults);
		echo '<br /><br />';
		$form->display();			
	
			

	}
} 
else 
{
	//the user is not registered in this course
	echo "<p style=\"font-weight:bold\">" . get_lang("MustBeRegisteredUser") . "</p>";
}

/*
==============================================================================
		Display of tool options
==============================================================================
*/
if ($display_tool_options) 
{
	display_tool_options($uploadvisibledisabled, $origin, $base_work_dir, $cur_dir_path, $cur_dir_path_url);
}

/*
==============================================================================
		Display list of files
==============================================================================
*/
if ($cur_dir_path == '/') 
{
	$my_cur_dir_path = '';
}
else 
{
	$my_cur_dir_path = $cur_dir_path;
}

if (!$display_upload_form && !$display_tool_options) {
	display_podcast_list($base_work_dir . '/' . $my_cur_dir_path, 'podcast/' . $my_cur_dir_path, $currentCourseRepositoryWeb, $link_target_parameter, $dateFormatLong, $origin, $_FILES['file']['type']);
}

/*
==============================================================================
		Footer
==============================================================================
*/
if ($origin != 'learnpath')
{
	//we are not in the learning path tool
	Display :: display_footer();
}
?>