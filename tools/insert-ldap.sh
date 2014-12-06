#!/bin/bash
# this is script help you to populate the basedn 
# use it with example ldif in this order
# sample-main.ldif
# sample-mail.ldif
# sample-pdns.ldif
# 
ldapadd -v -x -D "cn=admin,dc=example,dc=tld" -W -h localhost -f $1
