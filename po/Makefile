#!/bin/bash
# Makefile to generate POT
#

LOCALDIR = ../../locale

all:
	@echo Please, specify a target [pot,all-mo]

all-mo:
	@for dir in $(LOCALDIR) ; \
	do \
	    echo Doubled word check in... ; \
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

