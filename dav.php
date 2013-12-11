<?php

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

// set some constants to avoid exceptions
if (!defined('DEBUG_SELECT')) define ('DEBUG_SELECT', FALSE);

// get kOOL config and api
require_once('lib/sabre/kool_vcard.php');
require_once('inc/ko.inc');

// settings
date_default_timezone_set('Europe/Berlin');

/* Database */
try {
	$dsn = 'mysql:dbname='.$mysql_db.';host='.$$mysql_server;
	$pdo = new PDO('mysql:dbname=usrdb_vmfredbb_kool;host=localhost', $mysql_user, $mysql_pass);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);


//Mapping PHP errors to exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler", E_ERROR);



// Autoloader
require_once ('lib/sabre/vendor/autoload.php');

// Backends
$authBackend      = new Sabre\DAV\Auth\Backend\kOOL($pdo);
$principalBackend = new Sabre\DAVACL\PrincipalBackend\kOOL($pdo);
$carddavBackend   = new Sabre\CardDAV\Backend\kOOL($pdo);
//$caldavBackend    = new Sabre\CalDAV\Backend\PDO($pdo);

// Setting up the directory tree //
$nodes = array(
    new Sabre\DAVACL\PrincipalCollection($principalBackend),
//    new Sabre\CalDAV\CalendarRootNode($authBackend, $caldavBackend),
    new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
);

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri(parse_url($BASE_URL, PHP_URL_PATH).basename(__FILE__).'/');

// Plugins
$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend,'kOOL CardDAV Server'));
$server->addPlugin(new Sabre\DAV\Browser\Plugin());
//$server->addPlugin(new Sabre\CalDAV\Plugin());
$server->addPlugin(new Sabre\CardDAV\Plugin());
//$server->addPlugin(new Sabre\DAVACL\Plugin());

// And off we go!
$server->exec();
