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
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');


// A personaliser
$dbHostForm = "localhost";
$dbUsernameForm = "root";
$dbPassForm = "";
$dbNameForm = "dokeos_main";
$root_course = "w:/www/courses/";



// A personaliser
$dbHostForm = "localhost";
$dbUsernameForm = "user_name";
$dbPassForm = "password";
$dbNameForm = "Dokeos_Main";
$root_course = "/var/www/courses/";

/*

*/
	@mysql_connect($dbHostForm, $dbUsernameForm, $dbPassForm);

	//if error on connection to the database, show error and exit
	if (mysql_errno() > 0)
	{
		$no = mysql_errno();
		$msg = mysql_error();

		echo '<hr>['.$no.'] - '.$msg.'';
		exit ();
	}

	mysql_select_db($dbNameForm);

	/*
	
	Balayage de la table catalogue pour vérification des liens et ajouts
	 	
	*/
	
	$sql = "SELECT code, db_name, directory FROM $dbNameForm.course ORDER BY code";
	$result = mysql_query($sql);
	
	while(list($code, $dbNameForm, $directory) = mysql_fetch_row($result)){
	
	$mkdir = mkdir($root_course.$directory.'/podcast');
		
		/*$result_select = mysql_query("SELECT podcast FROM $dbNameForm.tool WHERE 1");
		if(!mysql_num_rows($result_select))
		{*/
		$sql_insert = "INSERT INTO $dbNameForm.tool (name, 
			    link,
			    image, 
			    visibility, 
			    admin, 
			    address, 
	      	    added_tool, 
			    target, 
			    category)

		VALUES ('podcast', 
			    'podcast/podcast.php', 
			    'podcast.gif', 
			    1, 
			    0, 
			    'squaregrey.gif', 
			    1, 
			    '_self', 
			    'interaction')";
				
//				 mysql_query($sql_insert);
//	          $sql_drop = "DROP TABLE $dbNameForm.podcast"; 
//				 mysql_query($sql_drop);
		//}

	$sql_create = "CREATE TABLE $dbNameForm.podcast (id int(10) NOT NULL PRIMARY KEY AUTO_INCREMENT, 
			      url varchar(200), 
			      title varchar(200), 			      
			      description varchar(250), 
			      author varchar(200), 
			      active tinyint(4), 
			      accepted tinyint(4), 
			      post_group_id int(11), 			      
			      sent_date datetime, 
			      filetype varchar(50))";
				  
				 mysql_query($sql_create);
	}
	
	echo 'Installation réussie!';
	
?>