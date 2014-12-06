#!/usr/bin/python
#
# (c) 2010 Mirko Grava - RHX - www.rhx.it
#
# Usuful to activate the vacation
# must be set in cron with the following string
# 1 0 * * * /path/of/the/command/vacation.py
# 
# many thanks to Michele Baldessari for great job

import ldap, syslog, datetime, sys

# Michele: Requires python2.5 due to datetime.strptime
if sys.version_info < (2, 4):
    raise Exception("Must use python 2.5 or greater")

LDAPHOST = "localhost"
LDAPBASE = "dc=example,dc=tld"
LDAPADMIN = "cn=admin,dc=example,dc=tld"
LDAPPASSWD = "yourpasswordhere"
DEBUG = True

try:
    l = ldap.open(LDAPHOST)
    if DEBUG: syslog.syslog("simple_bind(%s, %s)" % (LDAPADMIN, "xxx"))
    l.simple_bind(LDAPADMIN, LDAPPASSWD)
    
    searchScope = ldap.SCOPE_SUBTREE
    retrieveAttributes = ['mail', 'vacationStart', 'vacationEnd', 
        'vacationActive']
    # Production search filter
    searchFilter = "(&(objectClass=VirtualMailAccount)(accountActive=TRUE)(delete=FALSE)(forwardActive=FALSE)(mail=*))"

    # Find account where activate Vacation
    if DEBUG: syslog.syslog("search(%s, %s, %s, %s)" % (LDAPBASE, searchScope, searchFilter, retrieveAttributes))
    ldap_result_id = l.search(LDAPBASE, searchScope, searchFilter, retrieveAttributes)

    today = datetime.datetime.utcnow()
 
    while 1:
        entry = None
        result_type, result_data = l.result(ldap_result_id, 0)
        if (result_data == []):
            break
        if result_type != ldap.RES_SEARCH_ENTRY:
            continue

        entry = result_data[0]
        mail = entry[1]['mail'][0]
        vacationStart = datetime.datetime.strptime(entry[1]['vacationStart'][0], "%Y%m%d%H%M")
        vacationEnd = datetime.datetime.strptime(entry[1]['vacationEnd'][0], "%Y%m%d%H%M")
        if entry[1]['vacationActive'][0].upper() == 'TRUE':
            vacationActive = True
        else:
            vacationActive = False

        if DEBUG: syslog.syslog("%s : (%s < %s < %s)" % (entry[0], vacationStart, today, vacationEnd))

        if vacationStart < today < vacationEnd:
            if DEBUG: syslog.syslog("Vacation is to be set active")
            if vacationActive:
                if DEBUG: syslog.syslog("Vacation is already active, ignoring")
                continue

            mod_attrs = [( ldap.MOD_REPLACE, 'vacationActive', 'TRUE' )]
            ddn = l.search_s(LDAPBASE, ldap.SCOPE_SUBTREE, searchFilter)
            for dn, entry in ddn:
                l.modify_s(dn, mod_attrs)
        else:
            if DEBUG: syslog.syslog("Vacation is to be set inactive")
            if not vacationActive:
                if DEBUG: syslog.syslog("Vacation is already inactive, ignoring")
                continue

            mod_attrs = [( ldap.MOD_REPLACE, 'vacationActive', 'FALSE' )]
            ddn = l.search_s(LDAPBASE, ldap.SCOPE_SUBTREE , searchFilter)
            for dn, entry in ddn:
                l.modify_s(dn, mod_attrs)

except ldap.LDAPError, error:
    print 'Problem with ldap', error

l.unbind()
if DEBUG: syslog.syslog("Unbind successful")
