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
    if (isset($domain))
    {
	$domain_obj = new PhammLdap();
	$domain_val = $domain_obj->phamm_self_values ('vd='.$domain.','.LDAP_BASE, $filter="(vd=$domain)");

	$myObjectClass = (isset($pv[$pn]["ACCOUNT"]["OBJECTCLASSES"]) ? key($pv[$pn]["ACCOUNT"]["OBJECTCLASSES"]) : 'virtualMailAccount');
	$accounts_obj = new PhammLdap();
	$accounts_val = $accounts_obj->phamm_search ('vd='.$domain.','.LDAP_BASE,'(&(objectClass='.$myObjectClass.')(!(cn=postmaster))(!(mail=abuse@*)))');
    }

    // Overwrite action for user login
    if (!in_array($action,array('update_account')) && $_SESSION["login"]["level"] == 2)
	    $action = 'modify_account';
    
    // Overwrite action for Postmaster
    elseif (!in_array($action,array('add_account','insert_account','modify_account','update_account','domain_property','catch_all','group_actions')) && $_SESSION["login"]["level"] == 4)
	$action = 'domain_view';

    // Link to logount and language select
    phamm_print_xhtml ('<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">');
    
    phamm_print_xhtml ('<ul class="nav navbar-nav">');
    phamm_print_xhtml ("<li><a href=\"?exit=1\">");
    printf (_("Logout %s"),$_SESSION["login"]["username"]);
    phamm_print_xhtml ('</a></li>');
    phamm_print_xhtml ('</ul>');

    phamm_print_xhtml ('<ul class="nav navbar-form navbar-right">');
    phamm_print_xhtml ('<li>'.locale_select().'</li>');
    phamm_print_xhtml ('</ul>');
    phamm_print_xhtml ('</div>');

    if (!in_array($action,array('modify_account','update_account','insert_account','add_account')))
	phamm_print_xhtml ( plugins_menu() );

    if ($_SESSION["login"]["level"] > 2)
	phamm_print_xhtml ( navigation($initial) );

    # echo $action;

    switch ($action) :

    // group_actions
    case "group_actions" :

    // List of accounts/domains to modify
    if (isset($_POST["accounts"]))
	$values = $_POST["accounts"];
    elseif (isset($_GET["accounts"]))
	$values = $_GET["accounts"];

    if (isset($_POST["domains"]))
	$values = $_POST["domains"];
    elseif (isset($_GET["domains"]))
	$values = $_GET["domains"];

    if (isset($_POST["command"]))
	$command = $_POST["command"];
    elseif (isset($_GET["command"]))
	$command = $_GET["command"];

    foreach ($values as $k => $v)
	phamm_print_message('success',sprintf(_("modifying %s"),$k));

    $r = group_actions ($command, $values);
    
    $log->phamm_log ($pn,$_SESSION["login"]["username"],$action,$r);

    if ($r)
    {
	$ga = explode(';',$command);

        if ($ga[0] == 'account')
            refresh ('main.php?action=domain_view&amp;domain='.$domain, $force_meta = false, REFRESH_TIME);
        else
            refresh ('main.php', $force_meta = false, REFRESH_TIME);
    }
    else
    {
        phamm_print_message('error',_("Changes have not performed"));
        
	phamm_print_message('error', PhammLdap::phamm_error());
    }

    break;

    // Create dynamic form to add a new domain
    //
case "add_domain" :

    $syntax = check_syntax('domain',$_POST["domain_new"]);

    if ($syntax)
        phamm_print_xhtml ( form_add_domain_2 ($_POST["domain_new"],'insert_domain' , $values=array(), $values=array()));
    else
    {
        phamm_print_message('error',_("Syntax not valid or special chars found in domain name"));
        phamm_print_xhtml ( form_add_domain ($_POST["domain_new"]) );
    }

    break;

    // Insert a new domain data
    //
case "insert_domain" :

    $values = $_POST["values"];
    $confirm = $_POST["confirm"];
    $domain_new = $_POST["domain_new"];
    $password1 = $_POST["password1"];
    $password2 = $_POST["password2"];

    $wrong_pw_msg = wrong_pw ($password1,$password2);

    if ($wrong_pw_msg)
    {
	/*echo '<pre>';
	print_r($values);
	echo '</pre>'; */

        phamm_print_message ('error',$wrong_pw_msg);
        phamm_print_xhtml ( form_add_domain_2 ($_POST["domain_new"], 'insert_domain', $values, $values ) );
	$log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' '.$domain_new.' '.$wrong_pw_msg,0);
    }


    else
    {
        $entry["objectclass"][0]	= "top";
        $entry["objectclass"][1]	= "VirtualDomain";
        $entry["objectclass"][2]	= "inetLocalMailRecipient";

        $entry["vd"]				= $domain_new;
        $entry["lastChange"]		= time();
    
	// Merge static values with domain values
	$entry = array_merge($entry,$values["domain"]);
    
	// Purge values
	$values_purged = purge_empty_values($entry); // @todo 2th value
	
        $r_d = PhammLdap::phamm_add ('vd='.$domain_new.','.LDAP_BASE,$values_purged);
    
	$log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' '.$domain_new,$r_d);

        unset($entry);

        $entry = array();

        // %%%%%%%%%%%%%%%%%%%%% tmp %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
        $entry["objectclass"][0]	= "top";
        $entry["objectclass"][1]	= "VirtualMailAlias";

        // Add required plugin objectClass
        foreach ( $confirm as $p_name )
        {
            if ($pv[$p_name]["ISPOSTMASTERRELATED"])
            {
                if(isset($pv[$p_name]["POSTMASTER"]["OBJECTCLASSES"]))
                {
                    $oc = $pv[$p_name]["POSTMASTER"]["OBJECTCLASSES"];

                    foreach ($oc as $oc_name => $oc_val)
			$entry["objectclass"][] = $oc_name;
                }
            }
        }

        $entry["cn"] = "Postmaster";
        $entry["sn"] = "Postmaster";
        $entry["mail"] = "postmaster@".$domain_new;
        $entry["userPassword"]  = phamm_password_hash($password1);
        $entry["maildrop"] = "postmaster";
        $entry["accountActive"]		= "TRUE";
        $entry["creationDate"]		= date('Ymd');
        $entry["lastChange"]		= time();
        // %%%%%%%%%%%%%%%%%%%%% tmp %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%

        foreach ( $confirm as $p_name )
        {
            $entry = array_merge($entry,$values[$p_name]);
        }

	// Add postmaster alias
        $r_p = PhammLdap::phamm_add ('cn=postmaster,vd='.$domain_new.','.LDAP_BASE,$entry);
        
	$entry_abuse["objectclass"][0]	= "top";
        $entry_abuse["objectclass"][1]	= "VirtualMailAlias";
        $entry_abuse["cn"] = "Abuse";
        $entry_abuse["sn"] = "Abuse";
        $entry_abuse["mail"] = "abuse@".$domain_new;
        $entry_abuse["maildrop"] = "postmaster";
        $entry_abuse["accountActive"] = "TRUE";
        $entry_abuse["creationDate"] = date('Ymd');
        $entry_abuse["lastChange"] = time();
	
	// Add abuse alias
        $r_a = PhammLdap::phamm_add ('mail=abuse@'.$domain_new.',vd='.$domain_new.','.LDAP_BASE,$entry_abuse);

        if ($r_d && $r_p && $r_a)
        {
            phamm_print_message('success',sprintf(_("Domain %s successfully added"),$domain_new));
            refresh ('main.php', $force_meta = false, REFRESH_TIME);
        }
        elseif (!$r_d)
	{
	    phamm_print_message('error',sprintf(_("domain %s not added!"),$domain_new));
	    phamm_print_message('error', PhammLdap::phamm_error());
	}
        else
	{
            phamm_print_message('error',sprintf(_("An Unexpected Error Occurred during %s Addition!"),$domain_new));
	    phamm_print_message('error', PhammLdap::phamm_error());
	}

    }

    break;

    /////////////
    // ACCOUNT //
    /////////////

    // Create dynamic form to add a new account
case "add_account" :

    $syntax = check_syntax('account',$_POST["account_new"]);
    
    $account_exists = PhammLDAP::phamm_list('vd='.$domain.','.LDAP_BASE,"(mail=".$_POST["account_new"]."@".$domain.")",array('mail'));

    $max_accounts_allowed = ($pn == 'alias' ? $domain_val[0]["maxalias"][0] : $domain_val[0]["maxmail"][0]);

    if (isset($max_accounts_allowed))
    {
	if ($accounts_val["count"] >= $max_accounts_allowed)
	    $exceeds_max_account = true;
	else
	    $exceeds_max_account = false;
    }
    else
    {
	$exceeds_max_account = false;
    }

    if ($syntax && $account_exists["count"] == 0 && $exceeds_max_account == false)
        phamm_print_xhtml ( form_add_account_2 ($_POST["account_new"].'@'.$domain,'insert_account', array()) );

    else
    {
	if ($account_exists["count"] > 0)
	    phamm_print_message('error',_("Account or Alias already exists"));
	if (!$syntax)
	    phamm_print_message('error',_("Special Characters not Allowed in E-mail address"));
	if ($exceeds_max_account)
	    phamm_print_message('warning',sprintf("The maximum number of accounts (%s) has been reached or exceeded!", $max_accounts_allowed));
        phamm_print_xhtml ( form_add_account ($_POST["account_new"]) );
    }

    break;

    // Form to modify account
case "modify_account" :

    $dn = 'mail='.$mail.',vd='.$domain.','.LDAP_BASE;

    $values = PhammLdap::phamm_self_values ($dn);

    if (isset($values[0]["sn"][0]))
	$sn = $values[0]["sn"][0];

    if (isset($values[0]["givenname"][0]))
	$givenName = $values[0]["givenname"][0];

    // ////////////////////////////////////////////////////////////
    // Static warning @todo
    if (!($values[0]["amavisspamkilllevel"][0] >= $values[0]["amavisspamtag2level"][0] && $values[0]["amavisspamtag2level"][0] >= $values[0]["amavisspamtaglevel"][0]))
        phamm_print_message ('warning',_("SPAM Tags level are not set correctly!"));
    if ($values[0]["forwardactive"][0] == "TRUE" && isset($values[0]["vacationforward"]["count"]))
        phamm_print_message ('warning',_("do not activate Forward and Vacation Forward in the same time!"));
    if ($values[0]["forwardactive"][0] == "TRUE" && !isset($values[0]["maildrop"]))
        phamm_print_message ('warning',_("please, insert a forward destination or disable Forward!"));
    //
    // ////////////////////////////////////////////////////////////
    
    phamm_print_xhtml ( form_add_account_2 ($values[0]["mail"][0], 'update_account', $values) );

    break;

    // One domain selected - show domain's accounts and aliases
case "domain_view" :

    // Add domain form
    phamm_print_xhtml ( form_add_account() );

    phamm_print_xhtml ('<ul class="nav nav-pills">');
    // Link to modify domain Property
    phamm_print_xhtml ( '<li><a href="?action=domain_property&amp;domain='.$domain.'">'._("Domain property").'</a></li> ' );
    
    phamm_print_xhtml ('</ul>');

    //Special link for catch-all
    if (in_array($pn,array('mail','alias')))
    {
	// Check if catch_all exists
	$catch_all_dn = 'vd='.$domain.','.LDAP_BASE;
	$values = PhammLdap::phamm_search ($catch_all_dn,'mail=@'.$domain,array('mail'));

	if (isset($values[0]["mail"][0]))
	    phamm_print_message('warning',': '._("Catch all active"),null,'label');
	else
	    phamm_print_xhtml ( '<p><a href="?action=catch_all&amp;domain='.$domain.'">'._("Catch all").'</a></p>');
    }

    // Create domain Object
    $domain_obj = new PhammLdap();
    $domain_val = $domain_obj->phamm_self_values ('vd='.$domain.','.LDAP_BASE, $filter="(vd=$domain)");

    $values = array();

    // Attributes in the table
    if (isset($pv[$pn]["ACCOUNT"]))
        $values = array_merge($values,$pv[$pn]["ACCOUNT"]);

    phamm_print_xhtml ( accounts_list($domain,$values,$initial, $domain_val) );
    phamm_print_xhtml ( action_select('account') );

    break;

    // Insert a new account
    //
case "insert_account" :

    $values = $_POST["values"];
    $values_date = (isset($_POST["values_date"]) ? $_POST["values_date"] : null);
    $values_multi = (isset($_POST["values_multi"]) ? $_POST["values_multi"] : null);
    $confirm = $_POST["confirm"];
    $account_new = $_POST["account_new"];
    $password1 = $_POST["password1"];
    $password2 = $_POST["password2"];
    $sn = $_POST["sn"];
    $givenName = $_POST["givenname"];

    
    // Create empty array
    $entry = array();
    $values_multi_purged_one = array();
    $values_date_purged_one = array();
    $values_multi_purged = array();
    $values_date_purged = array();

    $check_sn = check_syntax ('name',$sn,1);
    $check_givenName = check_syntax ('name',$givenName,1);

    // Is alias account ?
    $is_alias = in_array('alias',$confirm);
    
    if ($is_alias)
	$wrong_pw_msg = wrong_pw ($password1,$password2,0);
    else
	$wrong_pw_msg = wrong_pw ($password1,$password2);

    if ($wrong_pw_msg)
        phamm_print_message ('error',$wrong_pw_msg);
    if (!$check_givenName)
        phamm_print_message ('error',_("Name is not valid"));
    if (!$check_sn)
        phamm_print_message ('error',_("Surname is not valid"));

    if ($wrong_pw_msg || !$check_givenName || !$check_sn)
    {
        phamm_print_xhtml ( form_add_account_2 ($_POST["account_new"], 'insert_account', $values ));
	$log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' '.$account_new.' '.$wrong_pw_msg,0);
    }

    else
    {
        $entry["objectclass"][0]	= "top";

        // Add required plugin objectClass
        foreach ( $confirm as $p_name )
        {
            if ($pv[$p_name]["ISACCOUNTRELATED"])
            {
                if(isset($pv[$p_name]["ACCOUNT"]["OBJECTCLASSES"]))
                {
                    $oc = $pv[$p_name]["ACCOUNT"]["OBJECTCLASSES"];

                    foreach ($oc as $oc_name => $oc_val)
			$entry["objectclass"][] = $oc_name;
                }
            }
        }

        $mail = $account_new;
        
	// in mail.xml
	//$uid = str_replace ( '@', CHR_UID_ACCOUNT, $mail);
        //$entry["uid"] = $uid; // @todo

        $entry["mail"] = $mail;
        $entry["userPassword"] = phamm_password_hash($password1);
        $entry["sn"] = $sn;
        $entry["givenname"] = $givenName;
        $entry["cn"] = $givenName.' '.$sn;
        $entry["lastChange"] = time();
        $entry["creationDate"] = date('YmdHi');

	$entry = purge_empty_values($entry);

        // Purge empty values and multiplier
        foreach ( $confirm as $p_name )
        {
	    // Clear and purge values
            $values_purged = purge_empty_values($values[$p_name],$pv[$p_name]["ACCOUNT"]["ATTRIBUTES"]);
	    $values_multi_purged_one = purge_empty_values($values_multi[$p_name]);
	    $values_date_purged_one = purge_empty_values($values_date[$p_name]);

	    // Merge values for each plugin
            $entry = array_merge($entry,$values_purged);

            $values_multi_purged = array_merge($values_multi_purged,$values_multi_purged_one);
            $values_date_purged = array_merge($values_date_purged,$values_date_purged_one);
        }

	// Values from textarea...
	if ($values_multi_purged)
	{
	    foreach ($values_multi_purged as $mv_name => $mv_array)
	    {
		$delimitators = array(" ",";","\t",":","\n");
		$values_multi_string = str_replace($delimitators,",","$mv_array");

		// Purge persistent \r
		$values_multi_string = str_replace("\r","","$values_multi_string");

		$values_multi_array = explode (",",$values_multi_string);

		for ($i=0; $i < count($values_multi_array); $i++)
		    $entry[$mv_name][$i] = $values_multi_array[$i];
	    }
	}

        $r = PhammLdap::phamm_add ('mail='.$mail.',vd='.$domain.','.LDAP_BASE,$entry);
	
	$log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' '.$mail,$r);

        if ($r)
        {
	    // Send Welcome message to new mail account if required
	    if (SEND_WELCOME)
	    {
		$eol = "\n";

		// Custom or default welcome message
		$welcome_msg_custom = '../templates/welcome_'.$domain.'.txt';
		
		if (file_exists($welcome_msg_custom))
		    $msg = file_get_contents($welcome_msg_custom);
		else
		    $msg = file_get_contents($welcome_msg);
		
		$headers = '';
		$msg_id = time().rand(1,1000).'_'.md5(uniqid(microtime()));
		$headers .= "Message-ID: <".$msg_id."@".$_SERVER['SERVER_NAME'].">".$eol;
		$headers .= "X-Mailer: Phamm".$eol;
		$headers .= "MIME-Version: 1.0".$eol;
		$headers .= "Content-Type: text/plain; charset=UTF-8".$eol;
		$headers .= "Content-Transfer-Encoding: 8bit".$eol;

		if (isset($welcome_sender))
		    $headers .= "From: <".$welcome_sender.">".$eol;
		else
		    $headers .= "From: <postmaster@".$domain.">".$eol;

		if (isset($welcome_bcc))
		    $headers .= "Bcc: <".$welcome_bcc.">".$eol;

		// Skip Welcome Message if is a Catch-all account
		if ($mail != '@'.$domain)
		{
		    $safe_mode_var = ini_get('safe_mode');

		    // Skip 5th parameter if safe_mode=On in php.ini
		    if ($safe_mode_var)
			$r = mail($mail,$welcome_subject,$msg,$headers);
		    else
			$r = mail($mail,$welcome_subject,$msg,$headers,'-f postmaster@'.$domain);
		}
		$log->phamm_log ($pn,$_SESSION["login"]["username"],'sent welcome message to '.$mail,$r);
	    }

            phamm_print_message('success',sprintf(_("Account %s successfully added"),$account_new));

            refresh ('main.php?action=domain_view&amp;domain='.$domain, $force_meta = false, REFRESH_TIME);
        }
        else
	{
            phamm_print_message('error',sprintf(_("%s not added!"), $account_new));
            phamm_print_message('error', PhammLdap::phamm_error());
	}
    }

    break;

    // Update account
    //
case "update_account" :


    $values = $_POST["values"];
    $values_date = (isset($_POST["values_date"]) ? $_POST["values_date"] : null);
    $values_multi = (isset($_POST["values_multi"]) ? $_POST["values_multi"] : null);
    $values_multi_del = (isset($_POST["values_multi_del"]) ? $_POST["values_multi_del"] : null);

    $confirm = $_POST["confirm"];
    $account = (isset($_POST["account_new"]) ? $_POST["account_new"] : null);
    $password1 = $_POST["password1"];
    $password2 = $_POST["password2"];
    $sn = (isset($_POST["sn"]) ? $_POST["sn"] : null);
    $givenName = (isset($_POST["givenname"]) ? $_POST["givenname"] : null);

    // Create empty array
    $entry = array();
    $values_multi_purged_one = array();
    $values_date_purged_one = array();
    $values_multi_purged = array();
    $values_date_purged = array();

    $entry["sn"] = $sn;
    $entry["givenname"] = $givenName;
    $entry["cn"] = $givenName.' '.$sn;

    // Tag and others
    $entry = purge_empty_values($entry);

    if ($password1 || $password2)
        $wrong_pw_msg = wrong_pw ($password1,$password2);
    else
        $wrong_pw_msg = 0;

    if ($wrong_pw_msg)
    {

        phamm_print_message ('error',$wrong_pw_msg);
        phamm_print_xhtml ( form_add_account_2 ($account, 'update_account', $values ));
	
	$log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' '.$wrong_pw_msg,0);

        break;
    }

    if (is_array($confirm))
    {
	foreach ( $confirm as $p_name )
	{
	    // Purge empty values
	    if (isset($pv[$p_name]["ACCOUNT"]))
		$values_purged = purge_empty_values($values[$p_name],$pv[$p_name]["ACCOUNT"]["ATTRIBUTES"]);
	    else
		$values_purged = purge_empty_values($values[$p_name]);

	    if (isset($values_multi[$p_name]))
	    	$values_multi_purged_one = purge_empty_values($values_multi[$p_name]);
	    if (isset($values_date[$p_name]))
	    	$values_date_purged_one = purge_empty_values($values_date[$p_name]);

	    // Merge the values from each plugins confirmed
	    $entry = array_merge($entry,$values_purged);
	    $values_multi_purged = array_merge($values_multi_purged,$values_multi_purged_one);
	    $values_date_purged = array_merge($values_date_purged,$values_date_purged_one);
    
	    // Del Values from checkbox
	    if (isset($values_multi_del[$p_name]))
	    {
	        $rd = PhammLdap::phamm_mod_del ('mail='.$mail.',vd='.$domain.','.LDAP_BASE,$values_multi_del[$p_name]);
	    }
	}
    }

    $mail = $account;

    // Date Values (year-month-day)
    if (count($values_date_purged) > 0)
    {
	foreach ($values_date_purged as $k_date => $v_date)
	    $entry[$k_date] = $v_date["year"].$v_date["month"].$v_date["day"].'0000';


    }

    // Add Values from textarea...
    if (count($values_multi_purged) > 0)
    {
        foreach ($values_multi_purged as $mv_name => $mv_array)
        {
            $delimitators = array(" ", ";", "\t", "\n", ":");
            $values_multi_string = str_replace($delimitators,",","$mv_array");
	    
	    // Purge persistent \r
	    $values_multi_string = str_replace("\r","","$values_multi_string");
            
	    $values_multi_array = explode (",",$values_multi_string);

            for ($i=0; $i < count($values_multi_array); $i++)
                $entry_add[$mv_name][$i] = $values_multi_array[$i];
        }

	if (count($values_multi_array) > 0)
	{
	    $ra = PhammLdap::phamm_mod_add ('mail='.$mail.',vd='.$domain.','.LDAP_BASE,$entry_add);
	}

    }

    // This swith action to change mail or postmaster value
    if ($mail)
    {
        $entry["lastChange"] = time();

	if ($password1)
	    $entry["userPassword"] = phamm_password_hash($password1);

	$r = PhammLdap::phamm_modify ('mail='.$mail.',vd='.$domain.','.LDAP_BASE,$entry);
	
	$log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' '.$mail,$r);
    }
    else
    {
	// Skip static values cn and sn
	unset($entry["cn"]);
	unset($entry["sn"]);
	unset($entry["givenname"]);
        
	$entry["lastChange"] = time();

	if ($password1)
	    $entry["userPassword"] = phamm_password_hash($password1);

	// Clear creationdata
	if (isset($entry["creationdate"]))
	    unset($entry["creationdate"]);

	$r = PhammLdap::phamm_modify ('cn=postmaster,vd='.$domain.','.LDAP_BASE,$entry);
	
	$log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' postmaster@'.$domain,$r);
	
	// Update domain values
	$entry_domain = purge_empty_values($values["domain"]);
	$r_d = PhammLdap::phamm_modify ('vd='.$domain.','.LDAP_BASE,$entry_domain);
	
	$log->phamm_log ($pn,$_SESSION["login"]["username"],'update_domain '.$domain,$r);
    }

    if ($r)
    {
        phamm_print_message('success',sprintf("Account %s succesfully updated",$account));
	refresh ('main.php?action=domain_view&amp;domain='.$domain, $force_meta = false, REFRESH_TIME);
    }
    else
        phamm_print_message('error',sprintf(_("account %s not updated!"),$account));

    break;

case "domain_property" :
    
	$dn = 'vd='.$domain.','.LDAP_BASE;
	$values = PhammLdap::phamm_search ($dn,$filter="objectClass=VirtualDomain");
	
	$dn_postmaster = 'cn=postmaster,vd='.$domain.','.LDAP_BASE;
	$values_postmaster = PhammLdap::phamm_search ($dn_postmaster,$filter="cn=postmaster");

        phamm_print_xhtml ( form_add_domain_2 ($domain, 'update_account', $values, $values_postmaster ) );
    break;

case "catch_all" :
    
	// action update_account => postmaster
        phamm_print_xhtml ( form_catch_all ($domain) );

    break;


default :

    // Plugin body
    // @todo if (
    $values = array();
    $values_domain["ATTRIBUTES"] = array();
    $values_postmaster["ATTRIBUTES"] = array();

    // Attributes in the table
    if (isset($pv[$pn]["DOMAIN"]))
        $values_domain = $pv[$pn]["DOMAIN"];
    
    // Attributes in the table
    if (isset($pv[$pn]["POSTMASTER"]))
        $values_postmaster = $pv[$pn]["POSTMASTER"];

    $values["ATTRIBUTES"] = array_merge($values_domain["ATTRIBUTES"],$values_postmaster["ATTRIBUTES"]);

    // @todo only one query...
    if ($_SESSION["login"]["level"] == 10)
    {
	if ($initial)
	    $vds = PhammLdap::phamm_list(LDAP_BASE,"(&(objectClass=virtualDomain)(vd=$initial*))",array('vd'));
	else
	    $vds = PhammLdap::phamm_list(LDAP_BASE,'objectClass=virtualDomain',array('vd'));

	phamm_print_xhtml ( form_add_domain() );

	phamm_print_xhtml ( domains_list($vds,$values) );
	phamm_print_xhtml ( action_select('domain') );
    }

    break;

    endswitch;
}

echo $page_end;

