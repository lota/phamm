<?php
/*
* Autentication class
*
* @package Phamm
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

class PhammLogin
{
    /**
    * Try to generate DN from $login_username
    *
    * @author Alessandro De Zorzi <adezorzi AT rhx DOT it>
    *
    * @param string $login_username
    * @return string $dn
    **/
    function login_dn_costructor($login_username)
    {
        // @todo $proposed will be real DN and level
        if (strpos($login_username, '=') && strpos($login_username, ','))
        {
            $proposed["dn"] = $login_username;
            $proposed["login_username"] = $login_username;
        }

        elseif (strpos($login_username, '@'))
        {
            $login = explode ('@',$login_username);

            // Postmaster
            if ( $login[0] == PHAMM_DOMAIN_ADMIN_NAME )
            {
                $proposed["dn"] = 'cn='.$login[0].',vd='.$login[1].','.LDAP_BASE;
                $proposed["level"] = 4;
                $proposed["domain"] = $login[1];
                $proposed["login_username"] = $login_username;
            }

            // User
            else
            {
                $proposed["dn"] = 'mail='.$login_username.',vd='.$login[1].','.LDAP_BASE;
                $proposed["level"] = 2;
                $proposed["domain"] = $login[1];
                $proposed["login_username"] = $login_username;
            }
        }

        // Manager
        elseif (in_array('cn='.$login_username.','.SUFFIX, array(BINDDN,BINDDN_EXTRA)))
        {
            $proposed["dn"] = 'cn='.$login_username.','.SUFFIX;
            $proposed["level"] = 10;
            $proposed["domain"] = '';
            $proposed["login_username"] = $login_username;
        }

        // Login user without @DOMAIN
        elseif (defined('DEFAULT_DOMAIN'))
        {
            $proposed["dn"] = 'mail='.$login_username.'@'.DEFAULT_DOMAIN.',vd='.DEFAULT_DOMAIN.','.LDAP_BASE;
            $proposed["level"] = 2;
            $proposed["domain"] = DEFAULT_DOMAIN;
            $proposed["login_username"] = $login_username.'@'.DEFAULT_DOMAIN;
        }

        // Postmaster without postmaster@
        else
        {
            $proposed["dn"] = 'cn=postmaster,vd='.$login_username.','.LDAP_BASE;
            $proposed["level"] = 4;
            $proposed["domain"] = $login_username;
            $proposed["login_username"] = 'postmaster@'.$login_username;
        }

        return $proposed;
    }


    /**
    * Try login
    *
    * @todo Clean values with special functions
    *
    * @param string $dn
    * @param string $login_password
    * @return mixed
    **/

    function login_try($connect, $proposed, $login_password,$login_username)
    {
        $r = ldap_bind($connect, $proposed["dn"], $login_password);

        if ($r)
        {
            $_SESSION["login"]["dn"] = strtolower($proposed["dn"]);
            $_SESSION["phamm"]["domain"] = strtolower($proposed["domain"]);
            $_SESSION["login"]["username"] = strtolower($proposed["login_username"]);

            $_SESSION["login"]["level"] = $proposed["level"];
            $_SESSION["login"]["password"] = $login_password; // @todo crypt it

            return true;
        }

        return false;
    }

    /**
    * Try bind
    *
    * @todo Clean values with special functions
    *
    * @param string $dn
    * @param string $password
    * @return bool $r
    **/

    function bind_try($connect, $dn, $password)
    {
        //$r = @ldap_bind($connect, $proposed["dn"], $login_password);
        $r = ldap_bind($connect, $dn, $password);

        return $r;
    }


    /**
    * Check if user are logged
    *
    * @return mixed
    **/

    function login_check()
    {
        if (isset($_SESSION["login"]["dn"]))
            return true;
        else
            return false;
    }


    /**
    * Destroy user session
    *
    * @return mixed
    **/

    function logout()
    {
        session_destroy();
    }
//
}
