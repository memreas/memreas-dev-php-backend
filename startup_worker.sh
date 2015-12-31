#!/bin/bash
#Purpose = tail php_errors.log file
#Created on 30-DEC-2015-2015


SERVICE=httpd
WAIT=true

while [  $WAIT == true ]; do

	if ps ax | grep -v grep | grep $SERVICE > /dev/null
	then
	    echo "$SERVICE service running, everything is fine"
	else
	    echo "$SERVICE is not running"
	    echo "$SERVICE is not running!" | mail -s "$SERVICE down" root
	fi

done

