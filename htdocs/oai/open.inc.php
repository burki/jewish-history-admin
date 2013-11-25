<?php

/**
 * @author Patzi
 * @copyright 2008
 */

/*

$server = "mysql.cms.hu-berlin.de:3306";
$user = "docupe01";
$pass = "%aqw%Tg8h";
$dbase = "docupedia";
*/

$server = "localhost";
$user = "root";
$pass = "";
$dbase = "docupedia";



$conn = @mysql_connect($server,$user,$pass);
if ($conn)
{
	mysql_select_db($dbase,$conn);
}
else
{
	die("<b>Verbindung zum MySql-Server konnte nicht hergestellt werden</b>");
}
