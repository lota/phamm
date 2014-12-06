#!/bin/bash
#
for i in `ls -1 *po`; do
    DIR=`echo $i| cut -d "." -f 1`
    DIR1=`echo $DIR| cut -d "_" -f 1`

if test -d ../locales/$DIR/LC_MESSAGES; then
    echo $i
    msgfmt -v $i -o ../locales/$DIR/LC_MESSAGES/messages.mo
fi

if test -d ../locales/$DIR1/LC_MESSAGES/; then
    echo $i
    msgfmt -v $i -o ../locales/$DIR1/LC_MESSAGES/messages.mo
fi

done

exit 0
