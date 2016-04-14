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

class PhammView
{

	/**
	 * Display Phamm Navigator menu and plugins_menu
	 *
	 * @author slebodniK at maranveart dot com
	 *
	 * @param string $action
	 * @param string $initial
	 **/
	static function display_menu($action,$initial)
	{
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
	}


	static function default_action($initial,$pv,$pn) 
	{

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

	}


	static function group_actions()
	{

		// List of accounts/domains to modify
		if (isset($_REQUEST["accounts"]))
			$values = $_REQUEST["accounts"];
		if (isset($_REQUEST["domains"]))
			$values = $_REQUEST["domains"];
		if (isset($_REQUEST["command"]))
			$command = $_REQUEST["command"];

		foreach ($values as $k => $v)
			phamm_print_message('success',sprintf(_("modifying %s"),$k));

		$r = group_actions ($command, $values);

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

	}
	//
}
 
