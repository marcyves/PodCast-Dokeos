<?php
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004-2009 Dokeos SPRL

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
* 	@version $Id: $
*/
?>
<HTML>
	<HEAD>
	<TITLE>Titre</TITLE>
	<META HTTP-EQUIV="Refresh" CONTENT="0; URL=webincast.xml"> 
	</HEAD>
	<body>
<?php
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
		//$tool_name = get_lang('StudentPublications');
		$tool_name = "podcast";
		$user_id = api_get_user_id();
		$course_code = $_course['sysCode'];
		$is_course_member = $is_courseMember || api_is_platform_admin();
		$currentCourseRepositorySys = api_get_path(SYS_COURSE_PATH) . $_course["path"] . "/";
		$currentCourseRepositoryWeb = api_get_path(WEB_COURSE_PATH) . $_course["path"] . "/";
		$currentUserFirstName = $_user['firstName'];
		$currentUserLastName = $_user['lastName'];

		$authors = Database :: escape_string($_POST['authors']);
		$delete = Database :: escape_string($_REQUEST['delete']);
		$description = Database :: escape_string($_REQUEST['description']);
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

		// If the POST's size exceeds 8M (default value in php.ini) the $_POST array is emptied
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
			Main code
		-----------------------------------------------------------
		*/

		$sql = "SELECT * FROM " .$work_table." WHERE 1";

		$result = mysql_query($sql);

		/*
		-----------------------------------------------------------
			XML generation
		-----------------------------------------------------------
		*/
        $filepath = "$currentCourseRepositorySys"."podcast/webincast.xml";
		if ($fp = fopen($filepath,"w")) {
		fputs($fp, '<?xml version="1.0" encoding="windows-1252"?>
		<rss version="2.0">
		<channel>
		<title>Le podCast de Andy</title>
		<description>Bienvenue sur le PodCast de Andy.</description>
		<link>http://localhost/main/podcast/rss2html.php</link>
		<category domain="">Education</category>
		<copyright>CERAM Sophia Antipolis</copyright>
		<docs>http://blogs.law.harvard.edu/tech/rss</docs>
		<language>fr</language>
		<lastBuildDate>Fri, 07 Apr 2006 15:00:00 +0100</lastBuildDate>
		<managingEditor>Learning Lab</managingEditor>
		<pubDate>Fri, 24 Mar 2006 15:00:00 +0100</pubDate>
		<skipDays>
		<day>Sunday</day>
		<day>Monday</day>
		<day>Tuesday</day>
		<day>Wednesday</day>
		<day>Thursday</day>
		<day>Saturday</day>
		</skipDays>
		<generator>Learning Lab - CERAM Sophia Antipolis</generator>');
		while ($data = mysql_fetch_array($result)){
			fputs($fp, '<item>
			<title>'.$data['title'].'</title>
			<description>'.$data['description'].'</description>
			<link>'.$currentCourseRepositoryWeb.$data['url'].'</link>
			<author>'.$data['author'].'</author>
			<enclosure url="'.$currentCourseRepositoryWeb.$data['url'].'" type="audio/mpeg"></enclosure>
			      <pubDate>Fri, 24 Mar 2006 12:06:25 +0100</pubDate>
			</item>');
		}
		fputs($fp, '</channel></rss>');
		}else echo "ça a echoué";
	?>
	</body>
</html>

