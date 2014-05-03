#!/bin/sh
# This script automatically starts the XbeeReader on pi startup.
# To enable this feature, run this file from /etc/rc.local
# MAKE SURE YOU BACKGOUND THIS SCRIPT BY ADDING AN & AFTER THE COMMAND


# sleep for 5 minutes,  give the pi time to fully start up
sleep 600

# Start power loggers
python /home/pi/xbee/python/XbeeReader.py > /dev/null
