<?php
/**
* Plugin class
*
* @package Phamm
**/

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


class PhammPlugin
{
    /**
    * Load plugins info from XML files into array
    *
    * @package Phamm
    * @author Alessandro De Zorzi <adezorzi@rhx.it>
    *
    * @return array $p_values
    **/
    public function plugins_load()
    {
        global $plugins;

        $pv = array();

        foreach ($plugins as $plugin)
        {
            $file = '../plugins/'.$plugin.'.xml';

            if (file_exists($file))
            {
                $xml2array = $this->xml2array($file);
                $pv = array_merge($pv, $xml2array);
            }
        }

        return $pv;
    }


    /**
    * Return PHP array from XML file
    *
    * original code from
    * mmustafa at vsnl dot com http://php.net/xml_parser_create
    *
    * @param mixed $file
    * @return array $params
    **/

    private function xml2array ($file)
    {
        $xml_parser = xml_parser_create();

        if (!($fp = fopen($file, "r"))) {
            die("could not open XML input");
        }

        $data = fread($fp, filesize($file));
        fclose($fp);

        xml_parse_into_struct($xml_parser, $data, $vals, $index);
        xml_parser_free($xml_parser);

        $params = array();
        $xml_elem = array();
        $level = array();

        foreach ($vals as $xml_elem) {
            if ($xml_elem['type'] == 'open') {
                if (array_key_exists('attributes',$xml_elem))
                {
                    $extra = array_values($xml_elem['attributes']);
                    $level[$xml_elem['level']] = $extra[0];
                }
                else
                {
                    $level[$xml_elem['level']] = $xml_elem['tag'];
                }
            }
            if ($xml_elem['type'] == 'complete')
            {
                $start_level = 1;
                $php_stmt = '$params';

                while($start_level < $xml_elem['level'])
                {
                    $php_stmt .= '[$level['.$start_level.']]';
                    $start_level++;
                }

                $php_stmt .= '[$xml_elem[\'tag\']] = isset($xml_elem[\'value\']) ?
                             $xml_elem[\'value\'] : "";';
                eval($php_stmt);
            }
        }

        return $params;

    }
//
}
