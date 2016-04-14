<?php

/*
* Domain class
*
* @package Domain 
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

class PhammDomain
{
	private $_domain;
	private $_initial;

	/**
	 * @author slebodnik at maranveart dot com
	 *
	 * @param string $domain
	 **/
	function __construct($domain,$initial)
	{
		$this->_domain=$domain;
		$this->_initial=$initial;
	}

	function getDomain()
	{
		return $this->_domain;
	}

	function add()
	{

		$syntax = check_syntax('domain',$_POST["domain_new"]);

		if ($syntax)
			phamm_print_xhtml ( form_add_domain_2 ($_POST["domain_new"],'insert_domain' , $values=array(), $values=array()));
		else
		{
			phamm_print_message('error',_("Syntax not valid or special chars found in domain name"));
			phamm_print_xhtml ( form_add_domain ($_POST["domain_new"]) );
		}

	}

	/**
	 * Insert a new domain data
	 * @author slebodnik at maranveart dot com
	 *
	 * @param PhammLog log
	 **/

	static function insert($log,$pv,$pn)
	{

		$values = $_POST["values"];
		$confirm = $_POST["confirm"];
		$domain_new = $_POST["domain_new"];
		$password1 = $_POST["password1"];
		$password2 = $_POST["password2"];

		$wrong_pw_msg = wrong_pw ($password1,$password2);

		if ($wrong_pw_msg)
		{

			phamm_print_message ('error',$wrong_pw_msg);
			phamm_print_xhtml ( form_add_domain_2 ($_POST["domain_new"], 'insert_domain', $values, $values ) );
			$log->phamm_log ($pn,$_SESSION["login"]["username"],$action.' '.$domain_new.' '.$wrong_pw_msg,0);
		}

		else
		{
			$entry["objectclass"][0]	= "top";
			$entry["objectclass"][1]	= "VirtualDomain";

			// Normalize domain in Lower Case
			$entry["vd"]			= strtolower($domain_new);
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

	}

	function view($plugins,$current_plugin)
	{

		// Add domain form
		phamm_print_xhtml ( form_add_account() );

		phamm_print_xhtml ('<ul class="nav nav-pills">');
		// Link to modify domain Property
		phamm_print_xhtml ( '<li><a href="?action=domain_property&amp;domain='.$this->_domain.'">'._("Domain property").'</a></li> ' );

		phamm_print_xhtml ('</ul>');

		//Special link for catch-all
		if (in_array($plugins,array('mail','alias')))
		{
			// Check if catch_all exists
			$catch_all_dn = 'vd='.$this->_domain.','.LDAP_BASE;
			$values = PhammLdap::phamm_search ($catch_all_dn,'mail=@'.$this->_domain,array('mail'));

			if (isset($values[0]["mail"][0]))
				phamm_print_message('warning',': '._("Catch all active"),null,'label');
			else
				phamm_print_xhtml ( '<p><a href="?action=catch_all&amp;domain='.$this->_domain.'">'._("Catch all").'</a></p>');
		}

		// Create domain Object
		if ($this->_domain)
			$domain_val = PhammLdap::phamm_self_values ('vd='.$this->_domain.','.LDAP_BASE, $filter="(vd=$this->_domain)");

		$values = array();

		// Attributes in the table
		if (isset($plugins[$current_plugin]["ACCOUNT"]))
			$values = array_merge($values,$plugins[$current_plugin]["ACCOUNT"]);

		phamm_print_xhtml ( accounts_list($this->_domain,$values,$this->_initial, $domain_val) );
		phamm_print_xhtml ( action_select('account') );

	}


	// Update account
	//

	function property()
	{

		$dn = 'vd='.$this->_domain.','.LDAP_BASE;
		$values = PhammLdap::phamm_search ($dn,$filter="objectClass=VirtualDomain");

		$dn_postmaster = 'cn=postmaster,vd='.$this->_domain.','.LDAP_BASE;
		$values_postmaster = PhammLdap::phamm_search ($dn_postmaster,$filter="cn=postmaster");

		phamm_print_xhtml ( form_add_domain_2 ($this->_domain, 'update_account', $values, $values_postmaster ) );
	}
	
	//
}
