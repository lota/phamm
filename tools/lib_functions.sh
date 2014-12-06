#!/bin/sh
#
FILE='../LIB_FUNCTIONS'
echo "****************************************" > $FILE

for i in `ls ../lib/*.php`;
	do
		echo "$i" >> $FILE
		echo "" >> $FILE
		cat $i | grep " function " >> $FILE
		echo "---------------------------------------------------------" >> $FILE
	done
exit 0
