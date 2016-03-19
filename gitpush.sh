#Purpose = ssh or sftp into ec2 instance
#Created on 1-NOV-2014
#Author = John Meah
#Version 1.0

echo -n "Enter the details of your deployment (i.e. 4-FEB-2014 Updating this script.) > "
read comment
echo "You entered $comment"
#set -v verbose #echo on

#copy fe settings to push to git...
#cp ./module/Application/src/Application/Model/MemreasConstants.bewQ.php ./module/Application/src/Application/Model/MemreasConstants.php

#Update composer.phar and files
#echo "Updating composer and vendor..."
#php composer.phar self-update
#php composer.phar update

#zero out php_errors.log
rm php_errors.log

#Push to AWS
echo "Committing to git..."
git add .
git commit -m "$comment"
echo "Pushing to github..."
set -v verbose #echo on
git push 

#cp module/Application/src/Application/Model/MemreasConstants.localhost.php module/Application/src/Application/Model/MemreasConstants.php

#
# curl url to pull latest on backend
#
curl https://memreasdev-backend.memreas.com/?action=clearlog
curl https://memreasdev-backend.memreas.com/?action=gitpull
