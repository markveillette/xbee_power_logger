#!/usr/bin/env python
import serial, time, datetime, sys, os
import numpy
from xbee import xbee
from socket import socket

# Import smtplib for the actual sending function
import smtplib
# Import the email modules we'll need
from email.mime.text import MIMEText


# This script is supposed to be run once every few minutes to log data.
# Data will be stored in .csv format
# each day, a new file will be created in LOGFILEHOME in the appropriate date directory

# Where data files are to be written
LOGFILEHOME = "/home/pi/xbee/data/"  

# How often would you like to record data (approximately)
# This records the wattage and amperage averaged over 1 second.
READ_FREQUENCY = 2 # seconds

# How often should data be logged to a file?
# This will log the average over this number of seconds
LOG_SECS = 5*60 # convert to seconds

DEFAULT_CARBON_SERVER = 'localhost'
DEFAULT_CARBON_PORT   = 2003

# Number of attempts before timing out..
MAX_ATTEMPTS = 1000
TIME_BETWEEN_ATTEMPTS = 2 # seconds

# Send an email if there's a problem?
SEND_EMAIL = False
EMAIL_RECIPIENT = "Mark4483@gmail.com"

# Details about reading serial data from xbee
SERIALPORT = "/dev/ttyUSB0"    # the com/serial port the XBee is connected to
BAUDRATE = 9600      # the baud rate we talk to the xbee
CURRENTSENSE = 4       # which XBee ADC has current draw data
VOLTSENSE = 0          # which XBee ADC has mains voltage data
MAINSVPP = 170 * 2     # +-170V is what 120Vrms ends up being (= 120*2sqrt(2))
vrefcalibration = [492,  # Calibration for sensor #0
                   484,  # Calibration for sensor #1
                   489,  # Calibration for sensor #2
                   492,  # Calibration for sensor #3
                   501,  # Calibration for sensor #4
                   493]  # etc... approx ((2.4v * (10Ko/14.7Ko)) / 3
CURRENTNORM = 15.5  # conversion to amperes from ADC

# open up the FTDI serial port to get data transmitted to xbee
ser = serial.Serial(SERIALPORT, BAUDRATE)
try:
    ser.open()
except:
    pass

def reopen_serial():
    if ser.isOpen():
        try:
            ser.close()
        except:
            pass
    try:
        ser.open()
    except:
        pass
    


# Get unix time in seconds since epoch
# Taken from http://stackoverflow.com/questions/6999726/python-converting-datetime-to-millis-since-epoch-unix-time
def unix_time(dt):
    epoch = datetime.datetime.utcfromtimestamp(0)
    delta = dt - epoch
    return delta.total_seconds()

# Simple class to keep a history and compute averages
class record_history(object):
    def __init__(self):
        self.data = numpy.array([])
        self.times = numpy.array([])
        self.max_age = 10*60 # seconds
    
    # returns current time in seconds since epoch
    def now(self):
        return unix_time(datetime.datetime.now())    
        
    # Add a new data point    
    def add_point(self,val):
        self.data = numpy.append(self.data,val)
        self.times = numpy.append( self.times, self.now() )
        self.remove_old()
        
    # remove data points older than self.max_age    
    def remove_old(self):
        t = self.now()
        old_t_idx = numpy.nonzero( self.times < t - self.max_age )
        self.data = numpy.delete(self.data, old_t_idx)
        self.times = numpy.delete(self.times, old_t_idx)
        
    # get the average value in data over the past num_secs    
    def get_avg(self,num_secs):
        domain = numpy.nonzero(self.now() - self.times < num_secs)[0]
        if len(domain) == 0:
            return float('nan')
        elif len(domain) == 1:
            return self.data[domain[0]]
        # Otherwise, compute the average using linear interpolation between recorded times    
        y = self.data[domain]
        x = self.times[domain]
        #return 1/(1.0*num_secs) * numpy.trapz(y,x=x)
        return numpy.mean(y)
 
# Make a directory if it doesn't exist   
def mkdir_p( directory ):
    if not os.path.exists(directory):
        os.makedirs(directory)       


def grab_packet():
    # grab one packet from the xbee, or timeout
    trial = 0
    max_trials = 100
    while trial < max_trials:
        packet = xbee.find_packet(ser)
        if not packet:
            trial = trial + 1
        else:
            break
    if trial == max_trials:
        # We couldn't get any data...
        return None,False
    else:
        return xbee(packet),True

def send_error_email():
    msg = MIMEText( "Unable to read data from xbee after " + str(MAX_ATTEMPTS) + " attempts." 
                    "\n Please troubleshoot and restart XbeeReader.")
    msg['Subject'] = 'FAILURE READING XBEE PACKETS'
    msg['From'] = 'tims_rpi.com'
    msg['To'] = EMAIL_RECIPIENT
    s = smtplib.SMTP(DEFAULT_CARBON_SERVER)
    s.sendmail('tims_rpi.com', [EMAIL_RECIPIENT], msg.as_string())
    s.quit()

# This processes the data in the packet obtained from the xbee
# This code is taken from https://github.com/adafruit/Tweet-a-Watt
def process_packet(xb):
    
    # we'll only store n-1 samples since the first one is usually messed up
    voltagedata = [-1] * (len(xb.analog_samples) - 1)
    ampdata = [-1] * (len(xb.analog_samples ) -1)
    # grab 1 thru n of the ADC readings, referencing the ADC constants
    # and store them in nice little arrays
    for i in range(len(voltagedata)):
        voltagedata[i] = xb.analog_samples[i+1][VOLTSENSE]
        ampdata[i] = xb.analog_samples[i+1][CURRENTSENSE]

    # get max and min voltage and normalize the curve to '0'
    # to make the graph 'AC coupled' / signed
    min_v = 1024     # XBee ADC is 10 bits, so max value is 1023
    max_v = 0
    for i in range(len(voltagedata)):
        if (min_v > voltagedata[i]):
            min_v = voltagedata[i]
        if (max_v < voltagedata[i]):
            max_v = voltagedata[i]

    # figure out the 'average' of the max and min readings
    avgv = (max_v + min_v) / 2
    # also calculate the peak to peak measurements
    vpp =  max_v-min_v

    for i in range(len(voltagedata)):
        #remove 'dc bias', which we call the average read
        voltagedata[i] -= avgv
        # We know that the mains voltage is 120Vrms = +-170Vpp
        voltagedata[i] = (voltagedata[i] * MAINSVPP) / vpp

    # normalize current readings to amperes
    for i in range(len(ampdata)):
        # VREF is the hardcoded 'DC bias' value, its
        # about 492 but would be nice if we could somehow
        # get this data once in a while maybe using xbeeAPI
        if vrefcalibration[xb.address_16]:
            ampdata[i] -= vrefcalibration[xb.address_16]
        else:
            ampdata[i] -= vrefcalibration[0]
        # the CURRENTNORM is our normalizing constant
        # that converts the ADC reading to Amperes
        ampdata[i] /= CURRENTNORM

    # calculate instant. watts, by multiplying V*I for each sample point
    wattdata = [0] * len(voltagedata)
    for i in range(len(wattdata)):
        wattdata[i] = voltagedata[i] * ampdata[i]

    # sum up the current drawn over one 1/60hz cycle
    avgamp = 0
    # 16.6 samples per second, one cycle = ~17 samples
    # close enough for govt work :(
    for i in range(17):
        avgamp += abs(ampdata[i])
    avgamp /= 17.0

    # sum up power drawn over one 1/60hz cycle
    avgwatt = 0
    # 16.6 samples per second, one cycle = ~17 samples
    for i in range(17):
        avgwatt += abs(wattdata[i])
    avgwatt /= 17.0

    # Print out our most recent measurements
    print str(xb.address_16)+"\tCurrent draw, in amperes: "+str(avgamp)
    print "\tWatt draw, in VA: "+str(avgwatt)

    # Return results
    if (avgamp > 13):
        return  float('nan'),float('nan')          # hmm, bad data
    else:
        return avgamp,avgwatt

 
# Function to log data to a file
def log_data(amps,watts):
   
    # create a data file for today if it doesn't already exist
    today = datetime.datetime.now()
    yr_dir = str(today.year)
    month = "%02d" % today.month
    day = "%02d" % today.day
    mkdir_p( LOGFILEHOME + '/' + yr_dir)
    fileName = LOGFILEHOME + '/' + yr_dir + '/' + str(today.year)[2:4] + month + day + '.csv'
    # Create file if it doesn't already exist
    if not os.path.isfile(fileName):
        logfile = open(fileName, 'w+')
        logfile.write("time,unix_time,amperes,watts\n")
    else:
        logfile = open(fileName, 'a')    
    
    # Get current time stamp
    curr_time = datetime.datetime.now().isoformat()
    utime = unix_time(datetime.datetime.now())
    
    # log data
    logfile.write( curr_time  + ',' +
                   str(utime) + ',' + 
                   str(amps)  + ',' + 
                   str(watts) + '\n' )
    logfile.close()

# Main function
def main():
    
    # For tracking history
    amp_history = record_history()
    watt_history = record_history()
    amp_history.max_age = 2*LOG_SECS
    watt_history.max_age = 2*LOG_SECS
    
    # For logging
    t0 = unix_time( datetime.datetime.now() )
    
    # Loop forever
    attempts = 0
    while True:
    
        # Try to grab a packet of data
        try:
            xb,suc=grab_packet()
        except:
            # On error, sleep for a minute then try to reopen serial port
            print "Error detected, attemping to reopen serial port.."
            time.sleep(60)
            reopen_serial()
            attempts += 1
            if attempts > MAX_ATTEMPTS:
                print "Time out:  unable to read data from xbee"
                if  SEND_EMAIL:
                    send_error_email()
                return 1
            else:
                continue
         
        # If we didn't get anything, wait a few seconds and try again
        if not suc:
            print "Unable to read packet.. retrying"
            attempts+=1
            if attempts > MAX_ATTEMPTS:
                print "Time out:  unable to read data from xbee"
                if  SEND_EMAIL:
                    send_error_email()
                return 1
            else:
                # Wait some time and try again..
                time.sleep(TIME_BETWEEN_ATTEMPTS)
                continue
        else:
            # reset attempts
            attempts = 0
        
        # Process packet
	try:
            avgamp,avgwatt = process_packet(xb)
	except:
            time.sleep(TIME_BETWEEN_ATTEMPTS)
	    continue
        
        # Record newest data points
        amp_history.add_point(avgamp)
        watt_history.add_point(avgwatt)
        
        # See if we should log this data point
        elapsed_time = unix_time( datetime.datetime.now() )  - t0
        if  elapsed_time > LOG_SECS:
            w = watt_history.get_avg(LOG_SECS) 
            a = amp_history.get_avg(LOG_SECS)
            print "Logging data.." 
            log_data(a,w)
            t0 = unix_time( datetime.datetime.now() )
            
        # Wait some time, and then try again
        time.sleep(READ_FREQUENCY)
        
        
if __name__ == "__main__":
    main()
        
    
    
    
    
    
    
    
    
    
    
    
    



        

            

