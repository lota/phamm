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
    global $skip_max_count_prefix;

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
      
                if (isset($skip_max_count_prefix))
                {       
                        $skip_prefix_filter = '';
                        foreach ($skip_max_count_prefix as $skip_prefix)
                                $skip_prefix_filter .= '(!(mail='.$skip_prefix.'@*))';
                        $active_val = PhammLdap::phamm_self_values ($dn_domain,'(&(objectClass=*)('.$k_entry.'=TRUE)'.$skip_prefix_filter.')');
                }
                else
                        $active_val = PhammLdap::phamm_self_values ($dn_domain,'(&(objectClass=*)('.$k_entry.'=TRUE))');

		if ($active_val["count"] >= $max_val)
		{
		  phamm_print_message('warning',sprintf(_("The maximum number of attribute %s (%s) has been reached or exceeded!"), $k_entry, $max_val));
		  return false;
		}
	      }
  
	      endif;
	    }

	    // DELETE_ACCOUNT_IMMEDIATELY backward compatibility
	    if (!defined(DELETE_ACCOUNT_IMMEDIATELY))
		define('DELETE_ACCOUNT_IMMEDIATELY', false);

	    // Delete immediate if VirtualMailAlias
	    if (isset($entry["delete"]) && ($is_alias || true == DELETE_ACCOUNT_IMMEDIATELY))
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
	
	// DELETE_ACCOUNT_IMMEDIATELY backward compatibility
	if (!defined(DELETE_ACCOUNT_IMMEDIATELY))
	    define('DELETE_ACCOUNT_IMMEDIATELY', false);

	// Delete immediate if VirtualMailAlias
	if (isset($entry["delete"]) && true == DELETE_ACCOUNT_IMMEDIATELY)
	    $r = PhammLdap::phamm_delete($dn,true);
        // Change single value
	else
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

function wrong_pw ($password1,$password2,$length=PASSWORD_MIN_LENGTH)
{
    if ( $password1 != $password2 )
        $error_msg = _("Passwords don't match!");

    elseif ( strlen($password1) < $length )
	$error_msg = _("Password too short!");

    elseif ( CHECK_PASSWORD_COMPLEXITY == 1 ) {
        if ( !check_syntax ('password',$password1) && $length > 0)
            $error_msg = _("Password does not meet complexity requirements.");
    }

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
 * @param string The password to hash in clear text.
 * @constant string Standard LDAP encryption type which must be one of
 *        crypt, ext_des, md5crypt, blowfish, md5, sha, smd5, ssha, sha512,
 *        sha256crypt, sha512crypt, or clear.
 * @return string The hashed password.
 */

function phamm_password_hash($password_clear,$enc_type) {

	//if (DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
	//	debug_log('Entered (%%)',1,0,__FILE__,__LINE__,__METHOD__,$fargs);

	$enc_type = strtolower(ENC_TYPE);

	switch($enc_type) {
		case 'blowfish':
			if (! defined('CRYPT_BLOWFISH') || CRYPT_BLOWFISH == 0)
				error(_('Your system crypt library does not support blowfish encryption.'),'error','index.php');

			# Hardcoded to second blowfish version and set number of rounds
			$new_value = sprintf('{CRYPT}%s',crypt($password_clear,'$2a$12$'.random_salt(13)));

			break;

		case 'crypt':
			if ("password" == strtolower(CRYPT_SALT))
				$new_value = sprintf('{CRYPT}%s',crypt($password_clear,substr($password_clear,0,2)));
			elseif ("random" == strtolower(CRYPT_SALT))
				$new_value = sprintf('{CRYPT}%s',crypt($password_clear,random_salt(2)));
			elseif (strlen(CRYPT_SALT) > 0)
				$new_value = sprintf('{CRYPT}%s',crypt($password_clear,CRYPT_SALT));
			else
				$new_value = sprintf('{CRYPT}%s',crypt($password_clear,random_salt(2)));
			break;

		case 'ext_des':
			# Extended des crypt. see OpenBSD crypt man page.
			if (! defined('CRYPT_EXT_DES') || CRYPT_EXT_DES == 0)
				error(_('Your system crypt library does not support extended DES encryption.'),'error','index.php');

			$new_value = sprintf('{CRYPT}%s',crypt($password_clear,'_'.random_salt(8)));

			break;

		case 'k5key':
			$new_value = sprintf('{K5KEY}%s',$password_clear);

			system_message(array(
				'title'=>_('Unable to Encrypt Password'),
				'body'=>'phpLDAPadmin cannot encrypt K5KEY passwords',
				'type'=>'warn'));

			break;

		case 'md5':
			$new_value = sprintf('{MD5}%s',base64_encode(pack('H*',md5($password_clear))));
			break;

		case 'md5crypt':
			if (! defined('CRYPT_MD5') || CRYPT_MD5 == 0)
				error(_('Your system crypt library does not support md5crypt encryption.'),'error','index.php');

			$new_value = sprintf('{CRYPT}%s',crypt($password_clear,'$1$'.random_salt(9)));

			break;

		case 'sha':
			# Use php 4.3.0+ sha1 function, if it is available.
			if (function_exists('sha1'))
				$new_value = sprintf('{SHA}%s',base64_encode(pack('H*',sha1($password_clear))));
			elseif (function_exists('mhash'))
				$new_value = sprintf('{SHA}%s',base64_encode(mhash(MHASH_SHA1,$password_clear)));
			else
				error(_('Your PHP install does not have the mhash() function. Cannot do SHA hashes.'),'error','index.php');

			break;

		case 'ssha':
			if (function_exists('mhash') && function_exists('mhash_keygen_s2k')) {
				mt_srand((double)microtime()*1000000);
				$salt = mhash_keygen_s2k(MHASH_SHA1,$password_clear,substr(pack('h*',md5(mt_rand())),0,8),4);
				$new_value = sprintf('{SSHA}%s',base64_encode(mhash(MHASH_SHA1,$password_clear.$salt).$salt));

			} else {
				error(_('Your PHP install does not have the mhash() or mhash_keygen_s2k() function. Cannot do S2K hashes.'),'error','index.php');
			}

			break;

		case 'smd5':
			if (function_exists('mhash') && function_exists('mhash_keygen_s2k')) {
				mt_srand((double)microtime()*1000000);
				$salt = mhash_keygen_s2k(MHASH_MD5,$password_clear,substr(pack('h*',md5(mt_rand())),0,8),4);
				$new_value = sprintf('{SMD5}%s',base64_encode(mhash(MHASH_MD5,$password_clear.$salt).$salt));

			} else {
				error(_('Your PHP install does not have the mhash() or mhash_keygen_s2k() function. Cannot do S2K hashes.'),'error','index.php');
			}

			break;

		case 'sha512':
			if (function_exists('openssl_digest') && function_exists('base64_encode')) {
				$new_value = sprintf('{SHA512}%s', base64_encode(openssl_digest($password_clear, 'sha512', true)));

			} else {
				error(_('Your PHP install doest not have the openssl_digest() or base64_encode() function. Cannot do SHA512 hashes. '),'error','index.php');
			}

			break;

		case 'sha256crypt':
			if (! defined('CRYPT_SHA256') || CRYPT_SHA256 == 0)
				error(_('Your system crypt library does not support sha256crypt encryption.'),'error','index.php');
			$new_value = sprintf('{CRYPT}%s',crypt($password_clear,'$5$'.random_salt(8)));

			break;

		case 'sha512crypt':
			if (! defined('CRYPT_SHA512') || CRYPT_SHA512 == 0)
				error(_('Your system crypt library does not support sha512crypt encryption.'),'error','index.php');
			$new_value = sprintf('{CRYPT}%s',crypt($password_clear,'$6$'.random_salt(8)));

			break;

		case 'clear':
		default:
			$new_value = $password_clear;
	}

	return $new_value;
}


/**
 * Used to generate a random salt for crypt-style passwords. Salt strings are used
 * to make pre-built hash cracking dictionaries difficult to use as the hash algorithm uses
 * not only the user's password but also a randomly generated string. The string is
 * stored as the first N characters of the hash for reference of hashing algorithms later.
 *
 * Original function from phpLDAPadmin project.
 *
 * @author The phpLDAPadmin development team
 *
 * @param int The length of the salt string to generate.
 * @return string The generated salt string.
 */

function random_salt($length) {
	//if (DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
	//	debug_log('Entered (%%)',1,0,__FILE__,__LINE__,__METHOD__,$fargs);

	$possible = '0123456789'.
		'abcdefghijklmnopqrstuvwxyz'.
		'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.
		'./';
	$str = '';
	mt_srand((double)microtime() * 1000000);

	while (strlen($str) < $length)
		$str .= substr($possible,(rand()%strlen($possible)),1);

	return $str;
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
	if (!preg_match ("/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/", $arg))
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
	if (!preg_match ("/^([0-9a-z][0-9a-z-]+\.)+[a-z]{2,7}$/i", $arg))
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
	if (!preg_match ("/^[\._a-z0-9-]+$/i", $arg))
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
        // does the entered password meet our requirements?
        //
        // Requirements:
        //   1. Must be at least PASSWORD_MIN_LENGTH characters long.
        //   2. Must contain at least 1 character from 3 of the following 4 categories:
        //      + English uppercase characters (A through Z)
        //      + English lowercase characters (a through z)
        //      + Base 10 digits (0 through 9)
        //      + Non-alphabetic "special" characters --- for example: ~,`,!,@,#,$,%,^,&,*,(,),_,-,+,=,{,
        //        [,},],|,\,:,;,",',<,[comma],>,.,?,/,[space]
        //   3. Must NOT contain the username.  This check is case-insensitive.

        // note: see PHP manual entry for Pattern Syntax for explanation of the patterns used.
        $lc_password = strtolower($arg);
        $length = strlen($arg);

        $matches = array();
        $numberOfUppercase = preg_match_all("/\p{Lu}/",$arg,$matches);
        $numberOfLowercase = preg_match_all("/\p{Ll}/",$arg,$matches);
        $numberOfDigits = preg_match_all("/\d/",$arg,$matches);
        $numberOfSpcChar = preg_match_all('/[~`!@#$%\^&*()\-_+={[}\]|\\\\:;"\'<,>.?\/ ]/',$arg,$matches);

        // calculate initial password quality by determining how many of our 4 categories have at least
        // one character in the password.
        $quality = 0;
        if( $numberOfUppercase > 0 )
            $quality++;
        if( $numberOfLowercase > 0 )
            $quality++;
        if( $numberOfDigits > 0 )
            $quality++;
        if( $numberOfSpcChar > 0 )
            $quality++;

        // does it contain the username?  (not case-sensitive)
	/* I am not sure use $_POST is a good way, $username seems not a good name and seems overwriten
        if ( isset($_POST["givenname"]) ) $username=strtolower($_POST["givenname"]);
        if ( isset($_POST["domain_new"]) ) $username=strtolower($_POST["domain_new"]);
        if( substr_count( $lc_password, $username ) > 0 )
        //    $quality = 0;
	*/

        if( $length >= PASSWORD_MIN_LENGTH && $quality > 2 )
            return true;
        else
            return false;
    }

    // Email
    elseif ($type == 'email')
    {
	if (!preg_match ("/^[_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4}$/i", $arg))
	{
            return false;
	}
	else
	{
	    return true;
	}
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
