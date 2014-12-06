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

LDAP_BASE="o=hosting,dc=example,dc=tld"
admin="cn=admin"
SUFFIX="dc=example,dc=tld"
ROOTPW="rhx"
BINDDN="cn=phamm,o=hosting,dc=example,dc=tld"
BINDPW="rhx"
OUTPUT="$HOME/bin/lista"

if [ ! -d ~/bin ]; then
    mkdir ~/bin
fi

if [ ! -d ~/tmp ]; then
    mkdir ~/tmp
fi

touch $OUTPUT
# find mail to delete
ldapsearch -D $BINDDN -w $BINDPW -b $LDAP_BASE -x -LLL "(&(objectClass=VirtualMailAccount)(delete=TRUE))" mailbox > ~/tmp/mb$$
# create file for awk
sed \
-e ':a' \
-e '$!N;s/\n //;ta' \
-e 'P;D' \
~/tmp/mb$$ > ~/tmp/mb$$.1

cat ~/tmp/mb$$.1 | awk '{
			if ($1 == "dn:")
			{ print "ldapdelete -D \"'$admin','$SUFFIX'\" -w \"'$ROOTPW'\" -x \""$2"\"" > "'$OUTPUT'" }
			if ($1 == "mailbox:")
			{ print "rm -rf ~/domains/" $2 > "'$OUTPUT'" } 
			}'

# find domain to delete
ldapsearch -D $BINDDN -w $BINDPW -b $LDAP_BASE -x -LLL "(&(objectClass=VirtualDomain)(delete=TRUE))" vd > ~/tmp/vd$$
# create file for awk
sed \
-e ':a' \
-e '$!N;s/\n //;ta' \
-e 'P;D' \
~/tmp/vd$$ > ~/tmp/vd$$.1

cat ~/tmp/vd$$ | awk '{
			if ($1 == "dn:")
			{ print "ldapdelete -D \"'$admin','$SUFFIX'\" -w \"'$ROOTPW'\" -x -r \""$2"\"" > "'$OUTPUT'" }
			if ($1 == "vd:")
			{ print "rm -rf ~/domains/" $2 > "'$OUTPUT'" } 
			}'

# execute and delete temporary files
chmod 700 $OUTPUT
$OUTPUT
rm -rf ~/tmp/mb* ~/tmp/vd* $OUTPUT
