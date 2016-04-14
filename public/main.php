<?php
// Start PHP session
session_start();

// Set the HTTP Charset Parameter as UTF-8
header("Content-type: text/html; charset=utf-8");

// Tmp Helper functions @todo create separate viewes
require_once ('../views/helpers.php');
require_once ('../controllers/helpers.php');

// Require all functions
$mydir = opendir('../lib');
while($fn = readdir($mydir))
{
    if (substr($fn,-3) == 'php')
        require_once ("../lib/$fn");
}
closedir($mydir);

require_once ('../config.inc.php');

// Force HTTPS if required
if (FORCE_SSL == 1 && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on"))
{
    $url=$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
    header ("Location: https://$url");
}

// Set PHP error level
phamm_php_error_level();

$page_end = page_end();
$page_exit = page_exit();

// Set Log object
$log = new PhammLog();

/////////////////////////////////////////////////////////////////
// Set language and locale
$locale = new PhammLocale();

if (isset($_GET["language"]))
    $locale->change_language($_GET["language"]);

setlocale(LC_ALL, $locale->get_language());
bindtextdomain("messages", "../locales");
textdomain("messages");
bind_textdomain_codeset("messages","UTF-8");
//
/////////////////////////////////////////////////////////////////
    
/////////////////////////////////////////////////////////////////
// Set action
if (isset($_POST["action"]))
    $action = $_POST["action"];
elseif (isset($_GET["action"]))
    $action = $_GET["action"];
else
    $action = null;
/////////////////////////////////////////////////////////////////

phamm_print_xhtml (page_start());

$connect = PhammLDAP::phamm_connect ();

// Load Plugins values in $pv
$plugin = new PhammPlugin(); 
$pv = $plugin->plugins_load();

if (!$connect)
{
    phamm_print_message ('error',_("Connection to LDAP Server Failed!"));
    
    phamm_print_message('error', PhammLdap::phamm_error());

    echo $page_exit;
}

// Login section

$login = new PhammLogin();

// First login
if (!$login->login_check() && isset($_POST["login_username"]) && isset($_POST["login_password"]))
{
    $proposed = $login->login_dn_costructor($_POST["login_username"]);

    $r_bind = $login->login_try($connect, $proposed, $_POST["login_password"],$_POST["login_username"]);

    $log->phamm_log ('',$_POST["login_username"],'login',$r_bind);

    if (!$r_bind)
    {
	$error_code = ldap_errno($connect);

	if ($error_code == '49')
	    phamm_print_message('error',_("Login Username or Password not valid."));
	elseif ($error_code == '-1')
	    phamm_print_message('error',_("Can't contact LDAP server"));
	else   
	    print phamm_print_message('error',_("Generic Error: ").ldap_error($connect));
    }
}

// Logout
if (isset($_GET["exit"]))
{
    $log->phamm_log ($_SESSION["phamm"]["pn"],$_SESSION["login"]["username"],'logout',1);
    
    $login->logout();

    phamm_print_message ('message',_("Bye!"));

    refresh ( 'main.php', $force_meta = false, REFRESH_TIME );

    echo $page_exit;

    exit;
}

// Not logged
if (!$login->login_check())
{
    $login_form = login_form();

    echo $login_form;
    echo $page_exit;

    exit;
}

// Logged
elseif($login->login_check())
{
    // Bind
    $r_bind = $login->bind_try($connect, $_SESSION["login"]["dn"], $_SESSION["login"]["password"]);

    // When user or postmaster change password must exit
    if (!$r_bind)
	refresh ( 'main.php?exit=1', $force_meta = false, REFRESH_TIME );

    if (!isset($_SESSION["phamm"]["pn"]))
        $_SESSION["phamm"]["pn"] = DEFAULT_PLUGIN;

    // Set some variables
    $pn = phamm_set_var('pn');
    $initial = phamm_set_var('initial');
    
    if ($_SESSION["login"]["level"] > 2)
	$mail = phamm_set_var('mail');
    else
	$mail = $_SESSION["login"]["username"];

    if ($_SESSION["login"]["level"] > 4)
	$domain = phamm_set_var('domain');
    else
	$domain = $_SESSION["phamm"]["domain"];
   
    // Create domain object
    $myDomain=new PhammDomain($domain,$initial);	 

    if (!empty($myDomain->getDomain()))
    {
	print_r($myDomain->getDomain());
	$myAccount=new PhammAccount($myDomain->getDomain());
    }

    // Overwrite action for user login
    if (!in_array($action,array('update_account')) && $_SESSION["login"]["level"] == 2)
	    $action = 'modify_account';
    
    // Overwrite action for Postmaster
    elseif (!in_array($action,array('add_account','insert_account','modify_account','update_account','domain_property','catch_all','group_actions')) && $_SESSION["login"]["level"] == 4)
	$action = 'domain_view';

    PhammView::display_menu($action,$initial);

    switch ($action) :
	    // group_actions
	case "group_actions" :
	    $log->phamm_log ($pn,$_SESSION["login"]["username"],$action,$r);
	    PhammView::group_actions();
	    break;
	    // Create dynamic form to add a new domain
	    //
	case "add_domain" :
	    $myDomain->add();
	    break;
	    // Insert a new domain data
	    //
	case "insert_domain" :
	    $myDomain->insert($log,$pv,$pn);
	    break;
	case "domain_view" :
	    $myDomain->view($pv,$pn);
	    break;
	case "domain_property" :
	    $myDomain->property();
	    break;
	    // Insert a new account
	    //
	    /////////////
	    // ACCOUNT //
	    /////////////

	    // Create dynamic form to add a new account
	case "add_account" :
	    $myAccount->add($pv,$pn);
	    break;
	    // Form to modify account
	case "modify_account" :
	    $myAccount->modify($mail);
	    break;
	    // One domain selected - show domain's accounts and aliases
	case "insert_account" :
	    $myAccount->insert($pv,$log);
	    break;
	    // Update account
	    //
	case "update_account" :
	    $myAccount->update($mail,$log);
	    break;
	case "catch_all" :
	    // action update_account => postmaster
	    phamm_print_xhtml ( form_catch_all ($myDomain->getDomain()) );
	    break;
	default :
	    PhammView::default_action($initial,$pv,$pn);
	    break;
	endswitch;
}

echo $page_end;

