#!/bin/bash
#Purpose = tail php_errors.log file
#Created on 30-DEC-2015-2015

# Define a timestamp function
timestamp() {
  date +"%T"
}


SERVICE=httpd
WAIT=true

while [  $WAIT == true ]; do

	if ps ax | grep -v grep | grep $SERVICE > /dev/null
	then
	    echo timestamp + " $SERVICE service running, everything is fine\n" >> /var/www/memreas-dev-php-backend/wakeup.log
 	    echo curl https://memreasdev-backend.memreas.com/?action=wakeup >> /var/www/memreas-dev-php-backend/wakeup.log
 	    break; 
	else
	    echo timestamp + " $SERVICE is not running will sleep until started..." >> /var/www/memreas-dev-php-backend/wakeup.log
	    sleep 5
	fi

done
echo timestamp + " startup_worker.sh complete" >> /var/www/memreas-dev-php-backend/wakeup.log 

