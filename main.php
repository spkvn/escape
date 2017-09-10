<?php
function __autoload($class)
{
    $parts = explode('\\', $class);
    require end($parts) . '.php';
}

use Escape\Database as Database;
use Escape\Table as Table;


$server = readline("Enter server name: ");
$user   = readline("Enter user name: ");
$pass   = readline("Enter password: ");
$db  	= readline("Enter DB name: ");
$output = readline("Enter Ouput file name: ");

$e = new Database($server, $user, $pass, $db, $output);

echo 	$e->fillTables();

$e->writeOutput();

?>