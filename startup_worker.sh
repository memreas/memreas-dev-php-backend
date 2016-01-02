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

while [  $WAIT == true ]; do

	if ps ax | grep -v grep | grep $SERVICE > /dev/null
	then
	    echo timestamp + " $SERVICE service running, everything is fine\n" >> $OUTPUTFILE
	    curl https://memreasdev-backend.memreas.com/?action=clearlog >> $OUTPUTFILE
		curl https://memreasdev-backend.memreas.com/?action=gitpull	>> $OUTPUTFILE
 	    curl https://memreasdev-backend.memreas.com/?action=wakeup >> $OUTPUTFILE
 	    break; 
	else
	    echo timestamp + " $SERVICE is not running will sleep until started..." >> $OUTPUTFILE
	    sleep 5
	fi

done
echo timestamp + " startup_worker.sh complete" >> $OUTPUTFILE 

