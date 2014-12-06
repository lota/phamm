WARNING THIS IS NOT TESTED YET
THANKS to Pavol Luptak
#!/bin/bash

LDAPPASS="rhx"
BASE="o=hosting,dc=example,dc=tld"
BINDDN="cn=admin,dc=example,dc=tld"
VACATION_USERS=`ldapsearch -L -x -w $LDAPPASS -b $BASE -D $BINDDN mail | grep ^mail | awk ' {print $2}'`
for USER in $VACATION_USERS; do
TMPFILE=`tempfile`
ldapsearch -L -x -w $LDAPPASS -b $BASE -D $BINDDN "(mail=$USER)" vacationStart vacationEnd >$TMPFILE

VACATION_START=`grep ^vacationStart: $TMPFILE | awk '{print $2}'`
VACATION_END=`grep ^vacationEnd: $TMPFILE | awk '{print $2}'`
DN=`grep ^dn: $TMPFILE`

# we consider only users who use vacation
if [ "x$VACATION_START" != "x" ] && [ "x$VACATION_END" != "x" ]; then

VACATION_START_SECONDS=`date --date="$VACATION_START" +%s`
VACATION_END_SECONDS=`date --date="$VACATION_END" +%s`
NOW_SECONDS=`date +%s`

# check for vacation dates

if (( $VACATION_START_SECONDS > $VACATION_END_SECONDS )); then
echo "Vacation End should not be earlier than Vacation Start" >&2
else

LDAPMODIFY=`tempfile -p "ldap"`

# if an user is on vacation
if (( $VACATION_START_SECONDS <= $NOW_SECONDS && $NOW_SECONDS <= $VACATION_END_SECONDS+86400 ));
then 
echo "$USER is on vacation ($VACATION_START-$VACATION_END)";
echo $DN > $LDAPMODIFY
echo "changetype: modify" >>$LDAPMODIFY
echo "replace: vacationActive" >>$LDAPMODIFY
echo "vacationActive: TRUE" >>$LDAPMODIFY
else 
echo "$USER is not on vacation ($VACATION_START-$VACATION_END)";
echo $DN > $LDAPMODIFY
echo "changetype: modify" >>$LDAPMODIFY
echo "replace: vacationActive" >>$LDAPMODIFY
echo "vacationActive: FALSE" >>$LDAPMODIFY
fi

ldapmodify -x -w $LDAPPASS -D $BINDDN -f $LDAPMODIFY

rm $LDAPMODIFY
fi
rm $TMPFILE
fi

done
