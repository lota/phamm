#!/bin/bash
# Makefile to generate POT
#

LOCALDIR = ../../locale

all:
	@echo Please, specify a target [pot,all-mo, all-po]

all-mo:
	@for dir in $(LOCALDIR) ; \
	do \
	    # msgcat l10n/pl_PL/*.po | msgfmt -vo l10n/pl_PL/civicrm.mo -
	    echo Doubled word check in... ; \
	done
all-po:
#for i in l10n/??_??; do msgmerge -vU $i/civicrm.po l10n/pot/civicrm.pot; done

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

