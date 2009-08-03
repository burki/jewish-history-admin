<?php

/**
 * @author Patzi
 * @copyright 2008
 */

/*
$server = "127.0.0.1";
$user = "h0451d5v";
$pass = "F8-HaS.2";
$dbase = "h0451d5v"; */

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
