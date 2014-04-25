#!/usr/bin/python
# Adds current temperature to mysql database

import MySQLdb as mbd
from read_temp import read_temp
import datetime
import requests
import re
import time

# A simple web scraper to get current temp
def get_outside_temp(town='Wilmington', state='MA'):
    r = requests.get('http://forecast.weather.gov/MapClick.php?CityName=Wilmington&state=MA')
    if r.ok:
        a = re.search(r"lrg\"\>(\d+)\&deg",r.text)
        t = float(a.group(1))
    else:
        t = None
    return t


def main():

    t_in = read_temp(units='F')
    if t_in is None:
        t_in = "NULL"
    t_out = get_outside_temp()
    if t_out is None:
        t_out = "NULL"
    currTime = time.strftime('%Y-%m-%d %H:%M:%S')

    con = mbd.connect('localhost',
                      'templogger',
                      'templogger',
                      'temperature')
    cur = con.cursor()

    # append newest data
    cur.execute("INSERT INTO log (date_and_time,indoor_temp,outdoor_temp) VALUES (%s,%s,%s)",(currTime,str(t_in),str(t_out)))

    con.commit()
    con.close()




if __name__ == "__main__":
    main()
















