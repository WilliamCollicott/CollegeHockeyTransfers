import re
import feedparser
import datetime
import requests
import smtplib
import mysql.connector

from bs4 import BeautifulSoup
from email.mime.text import MIMEText
from email.mime.image import MIMEImage
from email.mime.multipart import MIMEMultipart

from python_db_credentials import transaction_ids_path, gmail_app_password, db_username, db_password, db_ip, database_name, smtp_username, sender_email

team_ids_to_name = {
    '2453'  : 'Air Force',
    '1252'  : 'American International',
    '18066' : 'Arizona State',
    '1273'  : 'Army',
    '35387' : 'Augustana',
    '790'   : 'Bemidji State',
    '2319'  : 'Bentley',
    '911'   : 'Boston College',
    '633'   : 'Boston University',
    '1214'  : 'Bowling Green',
    '1320'  : 'Brown',
    '1583'  : 'Canisius',
    '685'   : 'Clarkson',
    '913'   : 'Colgate',
    '1859'  : 'Holy Cross',
    '706'   : 'Colorado College',
    '840'   : 'Cornell',
    '1917'  : 'Dartmouth',
    '728'   : 'Ferris State',
    '1339'  : 'Harvard',
    '1792'  : 'Lake Superior State',
    '35273' : 'Lindenwood',
    '30556' : 'Long Island',
    '1866'  : 'Mercyhurst',
    '1871'  : 'Merrimack',
    '1248'  : 'Miami',
    '1157'  : 'Michigan State',
    '548'   : 'Michigan Tech',
    '1520'  : 'Minnesota State',
    '2110'  : 'Niagara',
    '1465'  : 'Northeastern',
    '925'   : 'Northern Michigan',
    '1549'  : 'Ohio State',
    '2118'  : 'Penn State',
    '1551'  : 'Princeton',
    '713'   : 'Providence',
    '2078'  : 'Quinnipiac',
    '2039'  : 'RIT',
    '1543'  : 'Robert Morris',
    '1758'  : 'RPI',
    '2299'  : 'Sacred Heart',
    '773'   : 'St. Cloud',
    '1772'  : 'St. Lawrence',
    '4991'  : 'Stonehill',
    '1038'  : 'UMass-Lowell',
    '1366'  : 'Union',
    '1915'  : 'Alaska-Anchorage',
    '2071'  : 'Alaska-Fairbanks',
    '1362'  : 'Connecticut',
    '2034'  : 'Denver',
    '606'   : 'Maine',
    '1074'  : 'Massachusetts',
    '803'   : 'Michigan',
    '776'   : 'Minnesota',
    '1794'  : 'Minnesota-Duluth',
    '708'   : 'Omaha',
    '1136'  : 'New Hampshire',
    '1137'  : 'North Dakota',
    '1554'  : 'Notre Dame',
    '2745'  : 'St. Thomas',
    '710'   : 'Vermont',
    '452'   : 'Wisconsin',
    '1250'  : 'Western Michigan',
    '786'   : 'Yale'
}

# This method parses a transfer's description section and assembles the string representing the message to be published 
def construct_email(title, decoded_description, team_id):
    # Parse out the sections of the description we're interested in
    details = re.search(r'(Status: .*)<br/>\n(Date: .*)<br/>\nPlayer: <a href=\"(.*)\">', decoded_description)
    status = details.group(1)
    date = details.group(2)
    ep_player_page = details.group(3)

    # Assemble the formatted string
    email_body = '<p>%s<br>%s<br>%s' % (title, status, date)

    # If the transfer's description has 'additional information' (not all will have this), add it onto the message
    if re.search(r'Information:', decoded_description):
        information = re.search(r'(Information: .*)<br/>', decoded_description).group(1)
        email_body += ('<br>' + information)

    email_body += '<br><a href="%s">EliteProspects Player Page</a></p>' % (ep_player_page)

    # Assemble email object
    email = MIMEMultipart()
    email.attach(MIMEText(email_body, 'html'))
    email['Subject'] = '[CollegeHockeyTransfers] %s Transfer Alert' % (team_ids_to_name[team_id])

    # Load the player's EliteProspects page and search for a profile picture
    ep_player_page_data = requests.get(ep_player_page)
    ep_player_page_html = BeautifulSoup(ep_player_page_data.text, 'html.parser')
    ep_player_page_picture_section = ep_player_page_html.find('div', {'class': 'ep-entity-header__main-image'})
    ep_player_page_picture_search = re.search(r'url\([\"\'](.*)[\"\']\);', ep_player_page_picture_section['style'])

    # If it exists, attach the player page's profile photo to the email's body
    if ep_player_page_picture_search and (ep_player_page_picture_search.group(1) != 'https://static.eliteprospects.com/images/player-fallback.jpg'):
        # If the path to their profile picture is missing the https: prefix, add it
        if "https:" not in ep_player_page_picture_search.group(1):
            img_data = requests.get('https:' + ep_player_page_picture_search.group(1)).content
        else:
            img_data = requests.get(ep_player_page_picture_search.group(1)).content

        email.attach(MIMEImage(img_data, name='ep_player_picture'))

    return email

# For a given transaction, delegate the message construction to construct_message() and publish it
def send_email(transaction_id, transaction_ids_list, title, decoded_description, team_id):
    if transaction_id in transaction_ids_list:
        # Don't send out an alert for this transfer if we've already sent it out
        return
    
    print('Notify those who subscribed to %s' % (team_ids_to_name[team_id]))

    # Assamble the email to be published
    email_object = construct_email(title, decoded_description, team_id)

    # Send the email
    server = smtplib.SMTP('smtp.gmail.com', 587) # Use TLS encryption
    server.set_debuglevel(True)
    server.connect('smtp.gmail.com', 587)
    server.starttls()
    server.login(smtp_username, gmail_app_password)
    try:
        # Query the emails of individuals who have subscribed to the team in question
        connection = mysql.connector.connect(user=db_username, password=db_password, host=db_ip, database=database_name)
        cursor = connection.cursor()
        query = ("SELECT Email FROM Team AS T JOIN Subscription AS S ON S.TeamId = T.Id JOIN Email AS E ON E.Id = S.EmailId WHERE TeamName = '%s'" % (team_ids_to_name[team_id]))
        print(query)
        cursor.execute(query)
        
        # Send the email(s)
        for (email) in cursor:
            server.sendmail(sender_email, [email], email_object.as_string())

        print('Message sent!')

        # Record the transaction's ID so we know not to publish it again it we still see it later on
        with open(transaction_ids_path + 'transaction_ids.txt', 'a') as transaction_ids_file:
            date_and_time = datetime.datetime.now()
            transaction_ids_file.write(transaction_id + ',' + str(date_and_time) + '\n')

    finally:
        server.quit()
        cursor.close()
        connection.close()

# Assemble the list of transaction IDs that have already been published
def setup():
    transaction_ids_list = []           # list of transaction IDs that we've published less than 14 days ago
    transaction_lines_to_add_back = []  # transactions in transaction_ids.txt that were published less than 14 days ago
    script_invocation_time = datetime.datetime.now()

    # Loop through each transaction listed in transaction_ids.txt to determine if we still need to keep track of it
    with open(transaction_ids_path + 'transaction_ids.txt', 'r') as transaction_ids_file:
        transaction_ids_file_lines = transaction_ids_file.readlines()
        
        for line in transaction_ids_file_lines:
            # For each line in the file, parse out it's transaction ID and date it was put into the file
            line_parts = re.search(r'(\d*),(.*)', line)
            transaction_id = line_parts.group(1)
            transaction_datetime = datetime.datetime.strptime(line_parts.group(2), '%Y-%m-%d %H:%M:%S.%f')
            
            # If the transaction is older than 14 days, don't bother keeping track of it anymore
            time_difference = script_invocation_time - transaction_datetime
            if time_difference.days >= 14:
                continue

            # If we published the transaction less than 14 days ago, continue to keep track of it
            transaction_ids_list.append(transaction_id)
            transaction_lines_to_add_back.append(line)
            
    # Clear the transaction_ids.txt file and only write back the lines whose transactions we still want to keep track of
    with open(transaction_ids_path + 'transaction_ids.txt', 'w') as transaction_ids_file:
        for line in transaction_lines_to_add_back:
            transaction_ids_file.write(line)

    return transaction_ids_list

def process_feed(feed, transaction_ids_list):
    if len(feed) == 0:
        raise Exception("The list of RSS feed entries is 0")

    # In each the RSS feed's 50 most recent transfers, look for mentions of Michigan Tech or future or former players
    for item in feed.entries:
        transaction_id = re.search(r'/t/(\d*)', item.guid).group(1)
        decoded_description = str(BeautifulSoup(item.description, features='html.parser'))

        teams_ids = re.findall(r'<a href="https:\/\/www\.eliteprospects\.com\/team\/(\d*)\/', decoded_description)

        print(teams_ids)

        for team_id in teams_ids:
            if team_id in team_ids_to_name:
                # The current transaction involves a NCAA D1 hockey team.
                send_email(transaction_id, transaction_ids_list, item.title, decoded_description, team_id)

def main():
    transaction_ids_list = setup()
    feed = feedparser.parse('https://www.eliteprospects.com/rss/transfers')
    process_feed(feed, transaction_ids_list)

if __name__ == "__main__": 
    main()