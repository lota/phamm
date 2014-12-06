#!/bin/bash
# save all the base dn in a file .ldif
ROOTDN="cn=admin,dc=example,dc=tld"
ROOTPW="rhx"
BASEDN="dc=example,dc=tld"
LDAPSER="localhost"
LDAPATH="/root/ldap"
if [ ! -d $LDAPATH ] ; then
	mkdir $LDAPATH
fi

ldapsearch -LLL '(objectclass=*)' -D $ROOTDN -w $ROOTPW -b $BASEDN -v -x -h $LDAPSER > $LDAPATH/save-`date "+%d"`.ldif 2>/dev/null
