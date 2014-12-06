#!/usr/bin/perl

# Copyright (c) 2005 Alessandro De Zorzi, Mirko Grava
# <phamm@rhx.it> http://phamm.rhx.it/
#
# Permission is granted to copy, distribute and/or modify this document
# under the terms of the GNU Free Documentation License, Version 1.2
# or any later version published by the Free Software Foundation;
# A copy of the license in DOCS.LICENSE file.

# many thanks to Marco Sanson for his time
# IMPORTANT IMPORTANT
# READ BEFORE USE IT!!
# at the moment you MUST change the line above the description
# CHANGE-THIS to set your correct parameters
# like basedn, binddn and gnarwl path
# Thanks to Pavol Luptak for some troubleshooting

use Net::LDAP;
use Getopt::Std;
 
sub generate_random_string
{
        my $length_of_randomstring=shift;
 
        my @chars=('a'..'z','A'..'Z','0'..'9','_');
        my $random_string;
        foreach (1..$length_of_randomstring)
        {
                $random_string.=$chars[rand @chars];
        }
        return $random_string;
}
 
my $random_string=&generate_random_string(32);
 
#open (LOG,">>/tmp/forkpl.log") || die ("Can't open LOG");

$ldap = Net::LDAP->new("localhost");
$ldap->bind("cn=phamm,o=hosting,dc=example,dc=tld", password=>"rhx");
$in="";
while (<STDIN>) {
$in=join '',$in,$_;
}
chomp($in);
#print LOG "BEFORE FORK: $in";

# due to the -m switch
shift; 

while ($#ARGV >=0) {

SWITCH: { 
        $mail=$ARGV[0];
	shift;
$path = '';
$vforward = '';
 
$mesg = $ldap->search(filter=>"(mail=$mail)", base=>"dc=example,dc=tld", attrs=> ['vdHome', 'mailbox', 'vacationForward'] ); 
 
@entries = $mesg->entries;
foreach $entry (@entries) {
        $path = $entry->get_value('vdHome') . "/" . $entry->get_value('mailbox');
	$vforward = $entry->get_value('vacationForward');
}
 
$cmd1="" . $path . "new/" . $random_string . "";
$cmd2="/usr/bin/gnarwl";
$cmd3="/usr/sbin/sendmail ". $vforward;


open (MAIL,">$cmd1");
 
# Fork cmd1
# do not touch double fork!!
unless ($pid = fork) {
  unless (fork) {

#	print LOG "AFTER FORK $cmd1: $in";
#    exec ("echo '$in' > $cmd1");
     print MAIL "$in";
     close (MAIL);
    die "exec failed!";
  }
  exit 0;
}
waitpid($pid,0);
 
# Fork cmd2
unless ($pid = fork) {
  unless (fork) {
    exec ("cat '$cmd1' | $cmd2");
    die "exec failed!";
  }
  exit 0;
}
waitpid($pid,0);

#Fork cmd3
# here we use the attribute VactionForward for the mail delivery
# but the original destination receive the mail
# only a copy will be sent
if($vforward  =~ /\@/){
unless ($pid = fork) {
  unless (fork) {
    exec ("cat '$cmd1' | $cmd3");
    die "exec failed!";
  }
  exit 0;
}
waitpid($pid,0);
}

    }
}
exit;
