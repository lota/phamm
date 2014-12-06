<?php
/**
* Locale class
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

class PhammLocale
{
    private $language;

    function __construct()
    {
	if (!isset($_SESSION["language"]))
	    $this->language = DEFAULT_LANGUAGE;
	else
	    $this->language = $_SESSION["language"];
    }

    public function change_language($new_language)
    {
	global $supported_languages;

	if (array_key_exists($new_language, $supported_languages))
	{
	    $this->language = $new_language;
	    $_SESSION["language"] = $new_language;
	    return true;
	}
	else
	{
	    return false;
	}
    }

    public function get_language()
    {
	return $this->language;
    }
//
}
