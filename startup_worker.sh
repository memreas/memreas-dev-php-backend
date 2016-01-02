#!/bin/bash
#Purpose = tail php_errors.log file
#Created on 30-DEC-2015-2015

# Define a timestamp function
timestamp() {
  date +"%T"
}


SERVICE=httpd
WAIT=true
OUTPUTFILE=/var/www/memreas-dev-php-backend/wakeup.log
CLEARURL=https://memreasdev-backend.memreas.com/?action=clearlog
GITPULLURL=https://memreasdev-backend.memreas.com/?action=gitpull
WAKEUPURL=https://memreasdev-backend.memreas.com/?action=wakeup

rm $OUTPUTFILE
while [  $WAIT == true ]; do

	if ps ax | grep -v grep | grep $SERVICE > /dev/null
	then
	    echo "$(timestamp) $SERVICE service running, everything is fine\n" >> $OUTPUTFILE
	    echo "calling $CLEARURL"
	    curl $CLEARURL >> $OUTPUTFILE
	    echo "calling $GITPULLURL"
		curl $GITPULLURL	>> $OUTPUTFILE
	    echo "calling $WAKEUPURL"
		curl $WAKEUPURL	>> $OUTPUTFILE
 	    break; 
	else
	    echo "$(timestamp) $SERVICE is not running will sleep until started..." >> $OUTPUTFILE
	    sleep 5
	fi

done
echo "$(timestamp) startup_worker.sh complete" >> $OUTPUTFILE 

