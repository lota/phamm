WARNING THIS IS NOT TESTED YET
THANKS to Trieste Team
#!/usr/bin/perl
#
# check_expiration.pl
#
# Expiration check for virtual mailboxes. 
# The activeAccount flag of the expired accounts is set to false.
# 
use Time::Local;  # for timelocal()
#
$lastlog_file="/mail/sysmgr/check_expiration/expire.lastlog";
$log_file="/mail/sysmgr/check_expiration/expire.log";
$message_file="/mail/sysmgr/check_expiration/message.txt";
$tmp_file="/mail/sysmgr/check_expiration/tmp.tmp";
$ldif_file="/mail/sysmgr/check_expiration/expire.ldif";
#
$manager_dn="cn=admin,dc=example,dc=tld";
$manager_pass="rhx";
#
chomp($today_readable=qx{date +"%Y%m%d"});
#
open(LASTLOG,">".$lastlog_file); close(LASTLOG);
#
# Scan list of expiration dates
#
$buffer=qx { /usr/bin/ldapsearch -x -b "vd=example.com,o=hosting,dc=example,dc=tld" -D "$manager_dn" -ZZ -w $manager_pass -LLL mailboxExpiration };

$n=0;

$today=int(time()/(24*60*60));

open(ENTRIES,"/usr/bin/ldapsearch -x -b \"vd=example.com,o=hosting,dc=example,dc=tld\" -D \"$manager_dn\" -ZZ -w $manager_pass -LLL mailboxExpiration |");
while (<ENTRIES>) {
  chomp();
  if (/^dn:/) {
    ($g1,$g2)=split(/=/,$_,2);
    ($user,$g3)=split(/\@/,$g2,2);
    next;
  }
  if (/^mailboxExpiration:/) {
    ($g1,$date)=split(/: /,$_,2);
    $expires_in=$date-$today;
    if ($expires_in < 0) {
      # Check that the account is still active
      chomp($is_active=qx { ldapsearch -x -b "vd=example.com,o=hosting,dc=example,dc=tld" -D "$manager_dn" -ZZ -w $manager_pass "(mail=$user\@example.com)" | grep "accountActive: TRUE" | wc -l });
      if ($is_active != 0) {
        open(LDIF,">$ldif_file") || die "Can't open $ldif_file";
        print LDIF sprintf "dn: mail=%s\@example.com,vd=example.com,o=hosting,dc=example,dc=tld\n",$user;
        printf LDIF "changetype: modify\n";
        printf LDIF "replace: accountActive\n";
        printf LDIF "accountActive: FALSE\n";
        close (LDIF);
        # perform changes
        qx { /usr/bin/ldapmodify -x -v -D "$manager_dn" -w $manager_pass -ZZ -f $ldif_file >> $lastlog_file 2>&1 };
        open (LOG,">>$log_file") || die "Can't open $log_file";
        print LOG sprintf("%s: \"%s\" expired\n",$today_readable, $user);
        close(LOG);
        next;
      }
    }

    # Notify people whose expiration is approaching
    if (($expires_in == 30) ||
        ($expires_in == 7) ||
        ($expires_in == 2)) {
      open (MSG,$message_file);
      open (TMP,">$tmp_file");
      while(<MSG>) {
        chomp();
        $_=~s/DAYS/$expires_in/;
        $_=~s/USER/$user/;
        print TMP sprintf ("%s\n",$_);
      }
      close (TMP);
      close (MSG);
      $cmd=sprintf("/bin/mail -s \"Account %s expiring (-%s)\" %s < %s", $user,$expires_in,$user,$tmp_file);
      qx { $cmd };
      open (LOG,">>$log_file") || die "Can't open $log_file";
      print LOG sprintf("%s: \"%s\" expiring in %s days. Notified.\n",$today_readable, $user,$expires_in);
      close(LOG);
      unlink $tmp_file;
    }
  }
}
close (ENTRIES);

exit;
