#!/bin/bash
# this is a script useful to add sample-pureftpd.ldif and sample-squid.ldif
# from the example dir to basedn, those ldif dont works on phpldapadmin
# but could be a good sample
ldapmodify -r -v -x -D "cn=admin,dc=example,dc=tld" -W -h localhost -f $1
