#!/usr/bin/python
# A script to read current temperature
#
# Command line useage
#    read_temp  returns current temperature in degrees F
#    read_temp -C return current temperature in degrees C (use -F for F which is default)
#

import sys

TEMP_FILE = '/sys/bus/w1/devices/28-0000052f7e7c/w1_slave'

def read_temp(units='F'):
    with open(TEMP_FILE) as f:
        dat = f.readlines()
        if dat[0].split()[-1] == 'YES':
            t = float(dat[1].split('=')[-1])/1000
            if units=='F':
                # Convert to F
                t = t * 1.8 + 32
            elif units == 'K':
                # Convert to K
                t = t + 273.15
            elif units != 'C':
                print 'Unknown units: ' + units
                t = None
        else:
            t = None
    return t

def main():
    u = 'F'
    if len(sys.argv) > 1:
        u = sys.argv[1][-1]
    t = read_temp(units=u)
    if t is not None:
        print t

if __name__ == "__main__":
    main()










