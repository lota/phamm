<?php

class PhammLdap
{
    /**
    * Open the LDAP connection
    *
    * @author Alessandro De Zorzi <adezorzi AT rhx DOT it>
    **/

    static function phamm_connect ()
    {
        // Open LDAP connection to server
        $connect = @ldap_connect(LDAP_HOST_NAME,LDAP_PORT)
                   or die ("LDAP connection Failed!");

        ldap_set_option($connect,LDAP_OPT_PROTOCOL_VERSION,LDAP_PROTOCOL_VERSION);

        // Start TLS session
        if (LDAP_TLS == 1)
        {
            @ldap_start_tls($connect)
            or die ("Could not start TLS. Please check your LDAP server configuration.");
        }

        return $connect;
    }

    /**
    * Generic LDAP Single-level search
    *
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @todo add attrsonly, sizelimit, timelimit
    *
    * @param string $base_dn
    * @param string $filter
    * @param array $attributes
    * @param array $attributes
    * @param array $short Sort Attributes
    **/

    static function phamm_list ($base_dn,$filter,$attributes=null,$sort=null)
    {
        global $connect;

        // Do a LDAP search
        if ($attributes)
            $search = ldap_list($connect,$base_dn,$filter,$attributes);
        else
            $search = ldap_list($connect,$base_dn,$filter);

        // Order the results if possible
        if (version_compare(phpversion(), "4.2.0", ">="))
            ldap_sort($connect,$search,$sort);

        // Get entries
        $entries = ldap_get_entries($connect, $search);

        // Free the memory
        ldap_free_result($search);

        // Return the entry
        return $entries;
    }


    /**
    * Generic LDAP search
    *
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @todo add attrsonly, sizelimit, timelimit
    *
    * @param string $base_dn
    * @param string $filter
    * @param array $attributes
    * @return array $entries
    **/

    static function phamm_search ($base_dn,$filter,$attributes=null,$sort=null)
    {
        global $connect;

        // Do a LDAP search
        if (isset($attributes))
            $search = ldap_search($connect,$base_dn,$filter,$attributes);
        else
            $search = ldap_search($connect,$base_dn,$filter);

        // Order the results if possible
        if (version_compare(phpversion(), "4.2.0", ">="))
            ldap_sort($connect, $search,$sort);

        // Get entries
        $entries = ldap_get_entries($connect, $search);

        // Free the memory
        ldap_free_result($search);

        // Return the entry
        return $entries;
    }

    /**
    * Create new LDAP entry
    *
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @param string $dn
    * @param array $entry The attributes info
    * @return bool $r
    **/

    static function phamm_add ($dn,$entry)
    {
        global $connect;

        $r = ldap_add ($connect, $dn, $entry);

        return $r;
    }

    /**
    * Modify a LDAP entry
    *
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @param string $dn
    * @param array $entry The attributes info
    * @return bool $r
    **/

    static function phamm_modify ($dn,$entry)
    {
        global $connect;

        $r = ldap_modify ($connect, $dn, $entry );

        return $r;
    }

    /**
    * Get the values of a DN
    *
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @param string $dn
    * @param string $filter
    * @return array $results
    **/

    static function phamm_self_values ($dn, $filter="(cn=*)")
    {
        global $connect;

        $search = ldap_search($connect,$dn,$filter);

        $results = ldap_get_entries($connect, $search);

        return $results;
    }

    /**
    * Add new attribute (multiple)
    *
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @param string $dn
    * @param array $entry The attributes info
    * @return bool $r
    **/

    static function phamm_mod_add ($dn,$entry)
    {
        global $connect;

        $r = ldap_mod_add ($connect, $dn, $entry);

        return $r;
    }

    /**
    * Delete attribute (multiple)
    *
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @param string $dn
    * @param array $entry The attributes info
    * @return bool $r
    **/

    static function phamm_mod_del ($dn,$entry)
    {
        global $connect;

        $r = ldap_mod_del ($connect, $dn, $entry);

        return $r;
    }

    /**
    * Delete LDAP entry recursive
    *
    * @author gabriel at hrz dot uni-marburg dot de
    * http://it2.php.net/manual/it/static function.ldap-delete.php
    *
    * @param $ds
    * @param string $dn The DN
    * @param bool $recursive
    * @return bool
    **/

    static function phamm_delete($dn,$recursive=false)
    {
        global $connect;

        if($recursive == false)
        {
            return(ldap_delete($connect,$dn));
        }
        else
        {
            //searching for sub entries
            $sr=ldap_list($connect,$dn,"ObjectClass=*",array(""));
  
            $info = ldap_get_entries($connect, $sr);

	    if ($info['count'] > 0) :
            for($i=0; $i<$info['count']; $i++)
            {
                //deleting recursively sub entries
                //$result=self::phamm_delete($connect,$info[$i]['dn'],false);
		// Delete only the first child level
                $result=ldap_delete($connect,$info[$i]['dn']);

                if(!$result)
                {
                    //return result code, if delete fails
                    return($result);
                }
            }
	    endif;

            return(ldap_delete($connect,$dn));
        }
    }

        /**
        * Simple LDAP error
        **/

        static function phamm_error ()
        {
            global $connect;

            return _("LDAP Error: ").ldap_error($connect).' ('._("Code ").ldap_errno($connect).')';
        }

//
}
