#!/bin/bash
# Makefile to generate POT
#

LOCALDIR = ../../locale

all:
	@echo Please, specify a target [pot,all-mo, all-po]

all-mo:
	for lang in `ls -1 ??_??.po` ; do \
	  DESTDIR=`echo $$lang| cut -d \. -f 1`; \
	  msgfmt -v $$lang -o ../locales/$$DESTDIR/LC_MESSAGES/messages.mo; \
	done
all-po:
	for lang in `ls -1 ??_??.po` ; do \
	  msgmerge -vU $$lang phamm.pot; \
	done
pot:
	# Generate updated phamm.pot file
	# cp messages_header phamm.pot

	po4a-gettextize -o tags="<prettyName>" -o tagsonly=1 -f xml \
		-m ../plugins/alias.xml \
		-m ../plugins/ftp.xml \
		-m ../plugins/mail.xml \
		-m ../plugins/person.xml \
		-m ../plugins/proxy.xml \
		-p phamm.pot

	find ../ -name *.php -exec xgettext --keyword=_ -L PHP -j -o phamm.pot --omit-header -s {} \;

