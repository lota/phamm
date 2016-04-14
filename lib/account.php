<?php
/*
* Account class
*
* @package Account 
*/

/*
* Phamm - http://www.phamm.org - <team@phamm.org>
* Copyright (C) 2004,2014 Alessandro De Zorzi and Mirko Grava
*
* This file is part of Phamm.
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class PhammAccount
{


	private $_domain;

	/**
	 * @author slebodnik at maranveart dot com
	 *
	 * @param string $domain
	 **/
	function __construct($domain)
	{
		$this->_domain=$domain;
	}

    /////////////
    // ACCOUNT //
    /////////////

    // Create dynamic form to add a new account
     function add($pv,$pn)
    {
	    $syntax = check_syntax('account',$_POST["account_new"]);
	    $account_exists = PhammLDAP::phamm_list('vd='.$this->_domain.','.LDAP_BASE,"(mail=".$_POST["account_new"]."@".$this->_domain.")",array('mail'));
	    $myObjectClass = (isset($pv[$pn]["ACCOUNT"]["OBJECTCLASSES"]) ? key($pv[$pn]["ACCOUNT"]["OBJECTCLASSES"]) : 'virtualMailAccount');
	    $accounts_val = PhammLdap::phamm_search ('vd='.$this->_domain.','.LDAP_BASE,'(&(objectClass='.$myObjectClass.')(!(cn=postmaster))(!(mail=abuse@*)))');
	    $domain_val = PhammLdap::phamm_self_values ('vd='.$this->_domain.','.LDAP_BASE, $filter="(vd=$this->_domain)");
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
		    phamm_print_xhtml ( form_add_account_2 ($_POST["account_new"].'@'.$this->_domain,'insert_account', array()) );
	    else
	    {
		    if ($account_exists["count"] > 0)
			    phamm_print_message('error',_("Account or Alias already exists"));
		    if (!$syntax)
			    phamm_print_message('error',_("Special Characters not Allowed in E-mail address"));
		    if ($exceeds_max_account)
			    phamm_print_message('warning',sprintf(_("The maximum number of accounts (%s) has been reached or exceeded!"), $max_accounts_allowed));
		    phamm_print_xhtml ( form_add_account ($_POST["account_new"]) );
	    }

    }

    // Form to modify account
    function modify($mail)
    {

	    $dn = 'mail='.$mail.',vd='.$this->_domain.','.LDAP_BASE;
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
    }

    // Insert a new account
    //
    function insert($pv,$log)
    {
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

		    // Normalize e-mail in Lower Case
		    $mail = strtolower($account_new);

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
			echo "<PRE>";print_r('mail='.$mail.',vd='.$this->_domain.','.LDAP_BASE);print_r($entry);echo "</PRE>";
		    $r = PhammLdap::phamm_add ('mail='.$mail.',vd='.$this->_domain.','.LDAP_BASE,$entry);

		    $log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' '.$mail,$r);

		    if ($r)
		    {
			    // Send Welcome message to new mail account if required
			    if (SEND_WELCOME)
			    {
				    $eol = "\n";

				    // Custom or default welcome message
				    $welcome_msg_custom = '../templates/welcome_'.$this->_domain.'.txt';

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
					    $headers .= "From: <postmaster@".$this->_domain.">".$eol;

				    if (isset($welcome_bcc))
					    $headers .= "Bcc: <".$welcome_bcc.">".$eol;

				    // Skip Welcome Message if is a Catch-all account
				    if ($mail != '@'.$this->_domain)
				    {
					    $safe_mode_var = ini_get('safe_mode');

					    // Skip 5th parameter if safe_mode=On in php.ini
					    if ($safe_mode_var)
						    $r = mail($mail,$welcome_subject,$msg,$headers);
					    else
						    $r = mail($mail,$welcome_subject,$msg,$headers,'-f postmaster@'.$this->_domain);
				    }
				    $log->phamm_log ($pn,$_SESSION["login"]["username"],'sent welcome message to '.$mail,$r);
			    }

			    phamm_print_message('success',sprintf(_("Account %s successfully added"),$account_new));

			    refresh ('main.php?action=domain_view&amp;domain='.$this->_domain, $force_meta = false, REFRESH_TIME);
		    }
		    else
		    {
			    phamm_print_message('error',sprintf(_("%s not added!"), $account_new));
			    phamm_print_message('error', PhammLdap::phamm_error());
		    }
	    }

    }

    // Update account
    //
    function update($mail,$log)
    {
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

		    return;
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
				    $rd = PhammLdap::phamm_mod_del ('mail='.$mail.',vd='.$this->_domain.','.LDAP_BASE,$values_multi_del[$p_name]);
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
			    $ra = PhammLdap::phamm_mod_add ('mail='.$mail.',vd='.$this->_domain.','.LDAP_BASE,$entry_add);
		    }

	    }

	    // This swith action to change mail or postmaster value
	    if ($mail)
	    {
		    $entry["lastChange"] = time();

		    if ($password1)
			    $entry["userPassword"] = phamm_password_hash($password1);

		    $r = PhammLdap::phamm_modify ('mail='.$mail.',vd='.$this->_domain.','.LDAP_BASE,$entry);

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

		    $r = PhammLdap::phamm_modify ('cn=postmaster,vd='.$this->_domain.','.LDAP_BASE,$entry);

		    $log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' postmaster@'.$this->_domain,$r);

		    // Update domain values
		    $entry_domain = purge_empty_values($values["domain"]);
		    $r_d = PhammLdap::phamm_modify ('vd='.$this->_domain.','.LDAP_BASE,$entry_domain);

		    $log->phamm_log ($pn,$_SESSION["login"]["username"],'update_domain '.$this->_domain,$r);
	    }

	    if ($r)
	    {
		    phamm_print_message('success',sprintf("Account %s succesfully updated",$account));
		    refresh ('main.php?action=domain_view&amp;domain='.$this->_domain, $force_meta = false, REFRESH_TIME);
	    }
	    else
		    phamm_print_message('error',sprintf(_("account %s not updated!"),$account));

    }


//
}
