require 'rubygems'
require 'mechanize'

if ARGV.size != 9
  puts "9 parameters required"
  puts "NG"
  exit
end

site = ARGV.shift
username = ARGV.shift
password = ARGV.shift
commu_id = ARGV.shift
title = ARGV.shift

body = ARGV.shift
year = ARGV.shift
month = ARGV.shift
day = ARGV.shift


agent = Mechanize.new
page = agent.get(site)
form = page.forms.first

form['authMailAddress[mail_address]'] = username
form['authMailAddress[password]'] = password
form.submit  #Login

sleep 1
page = agent.get(site+"communityEvent/new/"+commu_id)

form = nil

form  = page.forms.first
form.method = 'POST'
form['community_event[name]'] = title
form['community_event[body]'] = body
form['community_event[area]'] = "指定なし"
form['community_event[open_date][year]'] = year
form['community_event[open_date][month]'] = month
form['community_event[open_date][day]'] = day


#p form
form.submit
sleep 1
puts "OK"
