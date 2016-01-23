<?php
/*
* Common functions
*
* @package Phamm
*/

/*
* Phamm - http://www.phamm.org - <team@phamm.org>
* Copyright (C) 2004,2008 Alessandro De Zorzi and Mirko Grava
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



/*
* Execute a command to multiple accounts/domains
*
* @param string $command The command
* @param array $values Lists of accounts/domains
*/

function group_actions ($command,$values)
{
    // Set initial return value
    $r = false;

    $ga = explode(';',$command);

    $mode = $ga[0];

    switch ($mode) :

    case "account" :

	foreach ($values as $key => $value)
        {
            // key contains mail
            $key1 = explode ('@',$key);

            // Create right DN for account and domain
            $dn = 'mail='.$key.',vd='.$key1[1].','.LDAP_BASE;
            $dn_domain = 'vd='.$key1[1].','.LDAP_BASE;
	    
	    // Pre-load values for account adn domain
	    $self_values = PhammLdap::phamm_self_values ($dn, $filter="(objectClass=*)");
	    $domain_values = PhammLdap::phamm_self_values ($dn_domain, $filter="(objectClass=*)");

            // (string) needed for TRUE and FALSE not real boolean...
            $entry[$ga[1]] = (string)$ga[2];
	    
	    $is_alias = (in_array('VirtualMailAlias',($self_values[0]["objectclass"])) ? true : false);
    

	    foreach ($entry as $k_entry => $v_entry)
	    {
	      if ($v_entry == 'TRUE') :

	      $max_key = strtolower('max'.$k_entry);

	      if (isset ($domain_values[0][$max_key][0]))
	      {
		$max_val = $domain_values[0][$max_key][0];
      
		$active_val = PhammLdap::phamm_self_values ($dn_domain,'(&(objectClass=*)('.$k_entry.'=TRUE))');
		
		if ($active_val["count"] >= $max_val)
		{
		  phamm_print_message('warning',sprintf(_("The maximum number of attribute %s (%s) has been reached or exceeded!"), $k_entry, $max_val));
		  return false;
		}
	      }
  
	      endif;
	    }

	    // Delete immediate if VirtualMailAlias
	    if (isset($entry["delete"]) && $is_alias)
		$r = PhammLdap::phamm_delete($dn,$recursive=false);
	    // Change single value
	    else
		$r = PhammLdap::phamm_modify ($dn,$entry);
        }

    break;

case "domain" :

    foreach ($values as $key => $value)
    {
        // Create right DN
        // $dn = 'cn=postmaster,vd='.$key.','.LDAP_BASE;
        $dn = 'vd='.$key.','.LDAP_BASE;

        // (string) needed for TRUE and FALSE not real boolean...
        $entry[$ga[1]]		= (string)$ga[2];

        // Change single value
        $r = PhammLdap::phamm_modify ($dn,$entry);
    }

    break;

case "postmaster" :

    foreach ($values as $key => $value)
    {
        // Create right DN
        // $dn = 'cn=postmaster,vd='.$key.','.LDAP_BASE;
        $dn = 'cn=postmaster,vd='.$key.','.LDAP_BASE;

        // (string) needed for TRUE and FALSE not real boolean...
        $entry[$ga[1]]		= (string)$ga[2];

        // Change single value
        $r = PhammLdap::phamm_modify ($dn,$entry);
    }

    break;

    endswitch;

    return $r;
}


/**
* Check if password do not match and...
*
* @param $password1
* @param $password2
* @return bool
**/

function wrong_pw ($password1,$password2,$length=PASSWORD_MIN_LENGHT)
{
    if ( $password1 != $password2 )
        $error_msg = _("Passwords don't match!");

    elseif ( strlen($password1) < $length )
	$error_msg = _("Password too short!");

    if (isset($error_msg))
        return $error_msg;

    return false;
}


/**
* Hashes a password and returns the hash based on the specified enc_type. 
*
* Original function from phpLDAPadmin project.
*
* @author The phpLDAPadmin development team
*
* @param string $password_clear The password to hash in clear text.
* @constant string $enc_type Standard LDAP encryption type which must be one of
*          crypt, md5 or clear.
* @return string The hashed password.
*/

function phamm_password_hash($password_clear)
{
    $enc_type = strtolower(ENC_TYPE);

    switch($enc_type)
    {
    case 'crypt':

        $salt = (defined(CRYPT_SALT) ? CRYPT_SALT : 'random');

        switch (strtolower($salt))
        {
        case 'password':
            $password_hash = '{CRYPT}'.crypt($password_clear, substr($password_clear,0,2));
            break;
        case 'random':
            $password_hash = '{CRYPT}'.crypt($password_clear, random_password(2));
            break;
        default:
            $password_hash = '{CRYPT}'.crypt($password_clear, $salt);
        }
        break;
        

    case 'md5':
	$password_hash = '{MD5}'.base64_encode(pack('H*',md5($password_clear)));
        break;

    case 'clear':
        $password_hash = $password_clear;
        break;

    default:
        $password_hash = '{CRYPT}'.crypt($password_clear);
        break;
    }

    return $password_hash;
}





/**
* Purge empty values
*
* @param array $data
* @param array $attribute
* @return array $res
**/

function purge_empty_values ($data,$attribute=null)
{
    // Empty array
    $res = array();

    if (is_array($data))
    {
	foreach ($data as $key => $val)

	if ($val)
	{
	    if (isset($attribute))
	    {
		// multiplies
		if (isset($attribute[$key]["MULTIPLIER"]))
		    $val = ( $val * $attribute[$key]["MULTIPLIER"] );

		// Append suffix
		if (isset($attribute[$key]["SUFFIX"]))
		    $val = $val.$attribute[$key]["SUFFIX"];
	    }

	    if (is_array($val))
		$res[$key] = $val;
	    else
		$res[$key] = strip_tags($val);
	}
    }

    return $res;
}










/**
* Various syntax check (IP address, domain, email address...)
*
* @author Alessandro De Zorzi <adezorzi@rhx.it>
* @todo Check if IP 0 < number <255
*
* @param string $type The kind of data
* @param string $arg The value
* @param int $length The min length of string
* @todo name
* @return bool
**/

function check_syntax ($type,$arg,$length="0")
{
    if (strlen($arg) < $length)
    {
        return false;
    }

    // IP Address
    if ($type == 'ip')
    {
        if (!ereg ("^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$", $arg))
        {
            return FALSE;
        }

        /*$numbers = explode('.',$arg);

        foreach ($numbers as $number)
        {
        	if ($number > 255)
        	return FALSE;
        } */

        else
        {
            return TRUE;
        }
    }


    // DOMAIN
    elseif ($type == 'domain')
    {
        if (!eregi("^([0-9a-z][0-9a-z-]+\.)+[a-z]{2,7}$", $arg))
        {
            return FALSE;
        }

        else
        {
            return TRUE;
        }
    }

    // ALIAS and ACCOUNT
    elseif ($type == 'account')
    {
        if (!eregi("^[\._a-z0-9-]+$", $arg))
        {
            return FALSE;
        }

        else
        {
            return TRUE;
        }
    }

    // Password
    elseif ($type == 'password')
    {
        if (!eregi("^[\._a-z0-9-]+$", $arg))
            return false;

        return true;
    }

    // Email
    elseif ($type == 'email')
    {
        if (!eregi("^[_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4}$", $arg))
            return false;

        return true;

    }

    // Name
    elseif ($type == 'name')
    {
        return true;
    }
}



function random_password($length = 8)
{
  $possibleChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";

  $password = '';

  for($i = 0; $i < $length; $i++)
  {
    $rand = rand(0, strlen($possibleChars) - 1);
    $password .= substr($possibleChars, $rand, 1);
  }

  return $password;
}
