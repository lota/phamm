#!/bin/bash
#
# Phamm - http://www.phamm.org - <team@phamm.org>
# Copyright (C) 2004-2008 Alessandro De Zorzi and Mirko Grava
# 
# Phamm is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# Phamm is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software

# This file MUST be in this path ~vmail/bin
# the dir bin MUST be in the vmail path
# something like this
#
# # set PATH so it includes user's private bin if it exists
# if [ -d ~/bin ] ; then
#    PATH=~/bin:"${PATH}"
# fi
#
# this script MUST be run as vmail user or the user
# that you want use to store all mail.
CREATEMAIL="$HOME/tmp/address"
CREATEPATH="$HOME/tmp/path"
CREATEDIR="$HOME/tmp/dir"

if [ ! -d ~/tmp ]; then
    mkdir ~/tmp
fi

touch $CREATEMAIL
touch $CREATEPATH
touch $CREATEDIR

/usr/bin/mailq > $HOME/tmp/mailq.$$
cat $HOME/tmp/mailq.$$ | awk 'BEGIN { FS = " " } ; { 
				if ($8 == "create") {
					getline 
					print $1 >> "'$CREATEMAIL'"
					}
				else
				{ }
			  }'

cat $CREATEMAIL | awk 'BEGIN { FS = "@" } ; { 
				print "/home/vmail/domains/"$2"/"$1 >> "'$CREATEPATH'"
				print "/home/vmail/domains/"$2 >> "'$CREATEDIR'"
			  }'
# 
for i in `cat $CREATEDIR`;
        do
		if [ ! -d $i ] ; then
			mkdir $i
		fi
	done

for i in `cat $CREATEPATH`;
        do
		if [ ! -d $i ] ; then
			/usr/bin/maildirmake $i
		fi
        done

rm $HOME/tmp/*
exit 0;
