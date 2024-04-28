import re
import os.path
import feedparser
import datetime
import requests
import smtplib
import mysql.connector
from bs4 import BeautifulSoup
from email.mime.text import MIMEText
from email.mime.image import MIMEImage
from email.mime.multipart import MIMEMultipart
from python_credentials import *
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

inter_university_transfers = []

# Mapping of team ID numbers to their corresponding team name using the EliteProspects team ID. Note these are different to those in the Team table in the database.
ep_team_ids_to_name = {
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

# Mapping of team names in the portal spreadsheets to their corresponding team name in the database.
spreadsheet_team_name_to_db_name = {
    'U.S. Air Force Academy'               : 'Air Force',
    'AIC'                                  : 'American International',
    'American International College'       : 'American International',
    'Arizona State University'             : 'Arizona State',
    'U.S. Military Academy'                : 'Army',
    'Augustana University (South Dakota)'  : 'Augustana',
    'Bemidji State University'             : 'Bemidji State',
    'Bentley University'                   : 'Bentley',
    'Bowling Green State University'       : 'Bowling Green',
    'Brown University'                     : 'Brown',
    'Canisius College'                     : 'Canisius',
    'Clarkson University'                  : 'Clarkson',
    'Colgate University'                   : 'Colgate',
    'College of the Holy Cross'            : 'Holy Cross',
    'Cornell University'                   : 'Cornell',
    'Dartmouth College'                    : 'Dartmouth',
    'Ferris State University'              : 'Ferris State',
    'Harvard University'                   : 'Harvard',
    'Lake Superior State University'       : 'Lake Superior State',
    'Lindenwood University'                : 'Lindenwood',
    'Long Island University'               : 'Long Island',
    'Mercyhurst University'                : 'Mercyhurst',
    'Merrimack College'                    : 'Merrimack',
    'Miami University (Ohio)'              : 'Miami',
    'Michigan State University'            : 'Michigan State',
    'Michigan Technological University'    : 'Michigan Tech',
    'Minnesota State University, Mankato'  : 'Minnesota State',
    'Niagara University'                   : 'Niagara',
    'Northeastern University'              : 'Northeastern',
    'Northern Michigan University'         : 'Northern Michigan',
    'The Ohio State University'            : 'Ohio State',
    'Pennsylvania State University'        : 'Penn State',
    'Princeton University'                 : 'Princeton',
    'Providence College'                   : 'Providence',
    'Quinnipiac University'                : 'Quinnipiac',
    'Rochester'                            : 'RIT',
    'Rochester Institute of Technology'    : 'RIT',
    'Robert Morris University'             : 'Robert Morris',
    'Rensselaer Polytechnic Institute'     : 'RPI',
    'Sacred Heart University'              : 'Sacred Heart',
    'St. Cloud State'                      : 'St. Cloud',
    'St. Cloud State University'           : 'St. Cloud',
    'St. Lawrence University'              : 'St. Lawrence',
    'Stonehill College'                    : 'Stonehill',
    'UMass Lowell'                         : 'UMass-Lowell',
    'University of Massachusetts Lowell'   : 'UMass-Lowell',
    'Union College (New York)'             : 'Union',
    'University of Alaska Anchorage'       : 'Alaska-Anchorage',
    'Alaska'                               : 'Alaska-Fairbanks',
    'University of Alaska Fairbanks'       : 'Alaska-Fairbanks',
    'University of Connecticut'            : 'Connecticut',
    'UConn'                                : 'Connecticut',
    'University of Denver'                 : 'Denver',
    'University of Maine'                  : 'Maine',
    'University of Massachusetts, Amherst' : 'Massachusetts',
    'University of Michigan'               : 'Michigan',
    'University of Minnesota, Twin Cities' : 'Minnesota',
    'Minnesota Duluth'                     : 'Minnesota-Duluth',
    'University of Minnesota Duluth'       : 'Minnesota-Duluth',
    'University of Nebraska Omaha'         : 'Omaha',
    'University of New Hampshire'          : 'New Hampshire',
    'University of North Dakota'           : 'North Dakota',
    'University of Notre Dame'             : 'Notre Dame',
    'University of St. Thomas (Minnesota)' : 'St. Thomas',
    'University of Vermont'                : 'Vermont',
    'University of Wisconsin-Madison'      : 'Wisconsin',
    'Western Michigan University'          : 'Western Michigan',
    'Yale University'                      : 'Yale'
}

# Access and load the data in a certain tab of the specified Google Sheets spreasheet.
def get_portal_spreadsheet_data(spreadsheet_id, sheet_name):
    scopes = ['https://www.googleapis.com/auth/spreadsheets.readonly']
    creds = None

    # token.json stores the user's access and refresh tokens.
    # It's created automatically when the authorization flow completes for the first time.
    if os.path.exists(token_json_path + 'token.json'):
        creds = Credentials.from_authorized_user_file(token_json_path + 'token.json', scopes)

    # If there are no (valid) credentials available, let the user log in.
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            flow = InstalledAppFlow.from_client_secrets_file(
                credentials_json_path + 'credentials.json', scopes
            )
            creds = flow.run_local_server(port=0)

        # Save the credentials for the next run
        with open(token_json_path + 'token.json', 'w') as token:
            token.write(creds.to_json())
    try:
        service = build('sheets', 'v4', credentials=creds)

        # Call the Sheets API
        sheet = service.spreadsheets()
        result = (
            sheet.values()
            .get(spreadsheetId=spreadsheet_id, range=sheet_name)
            .execute()
        )
        values = result.get('values', [])

        if not values:
            print('No data found.')
            return []

        return values
    except HttpError as err:
        print(err)
        return []

# Send the provided text in an email to the subscribers of each team mentioned in teams_involved.
def send_transfer_email(text, teams_involved, server, cursor):
    # Construct the email object.
    email_body = '<p>' + text + '</p>'
    email_object = MIMEText(email_body, 'html')
    email_object['Subject'] = '[CollegeHockeyTransfers] Transfer Alert'

    # Query the emails of individuals who have subscribed to the team in question.
    query = "SELECT Email FROM Team AS T JOIN Subscription AS S ON S.TeamId = T.Id JOIN Email AS E ON E.Id = S.EmailId WHERE TeamName = '%s'" % (teams_involved[0])

    # If a player's transfer lists a destination team, add an OR clause to the query so we get the email addresses both teams' subscribers.
    if len(teams_involved) == 2:
        query += " OR TeamName = '%s'" % teams_involved[1]

    cursor.execute(query)

    # Convert to a set and then back to a list in order to remove duplicates.
    recipient_list = list(set(list(cursor.fetchall())))

    # Send an email to each recipient.
    for recipient in recipient_list:
        try:
            server.sendmail(sender_email, [recipient], email_object.as_string())
        except smtplib.SMTPRecipientsRefused:
            # Recipient refused the email, so just move on to the next email address in recipient_list.
            continue

# Parse a transfer portal spreadsheet's data into a 'master' list that represents a running union of transfers amongst all spreadsheets analyzed so far.
def process_portal_spreadsheet(portal_spreadsheet_data, db_team_names, starting_row, origin_team_column, position_column, player_name_column, destination_team_column, date_added_column):
    # Loop through each row in the spreadsheet data.
    for row in portal_spreadsheet_data[starting_row:]:
        # Handle situations where sometimes a row's columns are empty and represented as not part of the row instead of just an empty string.
        try:
            spreadsheet_origin_team = row[origin_team_column].strip()

            if spreadsheet_origin_team == '':
                raise IndexError('The origin team is not listed!')
        except IndexError:
            # If there's no origin team listed, move on to the next row.
            continue
        
        try:
            spreadsheet_destination_team = '?' if row[destination_team_column] == '' else row[destination_team_column].strip()
        except IndexError:
            spreadsheet_destination_team = '?'

        try:
            # A player's position will be represented as either F, D, or G.
            position = '?' if row[position_column][0].upper() == '' else row[position_column].strip()[0].upper()
        except IndexError:
            position = '?'

        # Trim off any leading or trailing white space.
        spreadsheet_player_name = row[player_name_column].strip()

        if spreadsheet_origin_team in db_team_names:
            # Use what's in the spreadsheet if the raw text maps to a team name in the database.
            db_origin_team = spreadsheet_origin_team
        elif spreadsheet_origin_team in spreadsheet_team_name_to_db_name:
            # If not, check to see if it maps to a team name in the database.
            db_origin_team = spreadsheet_team_name_to_db_name[spreadsheet_origin_team]
        else:
            # The origin team could not be matched, so don't report this transfer.
            print('Skip reporing %s\'s transfer because the origin team could not be matched: %s' % (spreadsheet_player_name, spreadsheet_origin_team))
            continue

        if '(Withdrew from Portal)' in spreadsheet_destination_team or 'WITHDRAWN' in spreadsheet_destination_team:
            # If a player has withdrawn from the portal, make note of that.
            db_destination_team = db_origin_team
        elif spreadsheet_destination_team in db_team_names or spreadsheet_destination_team == '?': 
            # If the spreadsheet's destination team either matches a database team name or is unknown, use spreadsheet_destination_team. 
            db_destination_team = spreadsheet_destination_team
        elif spreadsheet_destination_team in spreadsheet_team_name_to_db_name:
            # Otherwise, we know that the spreadsheet's destination team maps to one in the database.
            db_destination_team = spreadsheet_team_name_to_db_name[spreadsheet_destination_team]
        else:
            # The destination team could not be matched, so don't report this transfer. For example, the player could be transferring to a D3, club, or junior team.
            print('Skip reporing %s\'s transfer because the destination team could not be matched: %s' % (spreadsheet_player_name, spreadsheet_destination_team))
            continue

        # Parse out and re-assemble the date added in order to account for differences in sheets' format, typos, etc.
        date_parts = re.search(r'(\d+)\/(\d+)\/(\d+)', row[date_added_column].strip())
        month = date_parts.group(1)
        day = date_parts.group(2)
        year = date_parts.group(3)

        if not year.startswith('20'):
            year = '20' + year

        date_added = month + '/' + day + '/' + year
        current_transfer = [date_added, spreadsheet_player_name, position, db_origin_team, db_destination_team]
        already_present = False

        # Look for the current transfer in our list ones we've already compiled from other transfer portal spreadsheets.
        for existing_transfer in inter_university_transfers:
            if current_transfer[1] == existing_transfer[1] and current_transfer[3] == existing_transfer[3]:
                # If we already saw this transfer in another transfer portal spreadsheet, check to see if it had a destination team listed.
                already_present = True
                if current_transfer[2] != '?' and existing_transfer[2] == '?':
                    # If the previous mention of this transfer didn't list the player's position, but this spreadsheet does, add it.
                    existing_transfer[2] == current_transfer[2]

                if current_transfer[4] != '?' and existing_transfer[4] == '?':
                    # If the previous mention of this transfer didn't list a destination team, but this spreadsheet does, add it.
                    existing_transfer[4] = current_transfer[4]
                    break

        # If this tranfer was not previously recorded, add it to our list of transfers to publish (as long as we didn't publish it in a previous invocation).
        if not already_present:
            inter_university_transfers.append(current_transfer)

# Examine each transfer we compiled from the spreadsheets and create a message for it. Then, send it out via email.
def construct_and_send_transfer_message(server, cursor):
    # Gather a list of lines from the file keeping track of which transfers have already been published.
    with open(published_transfers_path + 'published_transfers.txt', 'r') as published_transfers_file:
        published_transfers_file_lines = published_transfers_file.readlines()

    with open(published_transfers_path + 'published_transfers.txt', 'w') as published_transfers_file:
        # For each transfer that identified in the portal spreadsheets, check if it exists in published_transfers.txt (it was already published).
        for transfer in inter_university_transfers:
            date_added = transfer[0]
            player_name = transfer[1]
            player_position = transfer[2]
            origin_team = transfer[3]
            destination_team = transfer[4]

            transfer_already_published = False

            for published_transfer in published_transfers_file_lines:
                # Separate each line from published_transfers.txt into an array of its parts.
                published_transfer_parts = re.split(',', published_transfer.rstrip())

                # If we find a matching transfer that was already published (having the same transfer portal entry date and player name), check if the
                # previous publish was incomplete (didn't list a destination team). If it was, send it again to announce the destination team.
                if date_added == published_transfer_parts[0] and player_name == published_transfer_parts[1]:
                    transfer_already_published = True

                    # If the version of the transfer from published_transfers.txt listed '?' as the destination team, and the version that was identified
                    # in the latest invocation's destination team is NOT unknown, send out a second, complete notification.
                    if published_transfer_parts[4] == '?' and destination_team != '?':
                        # If the player has withdrawn from the portal and returned to their origin team, send out an update to complete the transfer.
                        if origin_team == destination_team:
                            text = '%s %s %s has withdrawn from the transfer portal.' % (origin_team, player_position, player_name)
                            send_transfer_email(text, [origin_team], server, cursor)
                        else:
                            text = '%s %s %s has transferred to %s.' % (origin_team, player_position, player_name, destination_team)
                            send_transfer_email(text, [origin_team, destination_team], server, cursor)

                        # When recording this transfer in published_transfers.txt, we want it to be the version that is complete (lists a destination team).
                        published_transfers_file.write('%s,%s,%s,%s,%s\n' % (date_added, player_name, player_position, origin_team, destination_team))
                        break

                    published_transfers_file.write(published_transfer)

            if not transfer_already_published:
                # A new transfer has been identified, so publish a notification for it.
                if destination_team == '?':
                    text = '%s %s %s has entered the transfer portal.' % (origin_team, player_position, player_name)
                    send_transfer_email(text, [origin_team], server, cursor)
                elif origin_team == destination_team:
                    text = '%s %s %s entered the transfer portal, but later withdrew.' % (origin_team, player_position, player_name)
                    send_transfer_email(text, [origin_team], server, cursor)
                else:
                    text = '%s %s %s has transferred to %s.' % (origin_team, player_position, player_name, destination_team)
                    send_transfer_email(text, [origin_team, destination_team], server, cursor)

                published_transfers_file.write('%s,%s,%s,%s,%s\n' % (date_added, player_name, player_position, origin_team, destination_team))

# This method parses a transfer's description section and assembles the string representing the message to be published.
def construct_email(title, decoded_description):
    # Parse out the sections of the description we're interested in.
    details = re.search(r'(Status: .*)<br/>\n(Date: .*)<br/>\nPlayer: <a href=\"(.*)\">', decoded_description)
    status = details.group(1)
    date = details.group(2)
    ep_player_page = details.group(3)

    # Assemble the formatted string
    email_body = '<p>%s<br>%s<br>%s' % (title, status, date)

    # If the transfer's description has 'additional information' (not all will have this), add it onto the message.
    if re.search(r'Information:', decoded_description):
        information = re.search(r'(Information: .*)<br/>', decoded_description).group(1)
        email_body += ('<br>' + information)

    email_body += '<br><a href="%s">EliteProspects Player Page</a></p>' % (ep_player_page)

    # Assemble email object.
    email = MIMEMultipart()
    email.attach(MIMEText(email_body, 'html'))
    email['Subject'] = '[CollegeHockeyTransfers] Transaction Alert'

    # Load the player's EliteProspects page and search for a profile picture.
    ep_player_page_data = requests.get(ep_player_page)
    ep_player_page_html = BeautifulSoup(ep_player_page_data.text, 'html.parser')
    ep_player_page_picture_section = ep_player_page_html.find('div', {'class': 'ep-entity-header__main-image'})
    ep_player_page_picture_search = re.search(r'url\([\"\'](.*)[\"\']\);', ep_player_page_picture_section['style'])

    # If it exists, attach the player page's profile photo to the email's body.
    if ep_player_page_picture_search and ep_player_page_picture_search.group(1) != 'https://static.eliteprospects.com/images/player-fallback.jpg':
        ep_player_picture_link = ep_player_page_picture_search.group(1)

        # If the path to their profile picture is missing the 'https:' prefix, add it.
        if 'https:' not in ep_player_picture_link:
            ep_player_picture_link = 'https:' + ep_player_picture_link

        # Determine the profile picture's file type so we know what kind of image to attach to the email.
        file_name = ep_player_picture_link.split('/')[-1]
        file_extension = '.' + file_name.split('.')[-1]

        img_data = requests.get(ep_player_picture_link).content
        email.attach(MIMEImage(img_data, name='ep_player_picture', _subtype=file_extension))

    return email

# For a given transaction, delegate the message construction to construct_message() and publish it.
def send_transaction_email(transaction_id, title, decoded_description, team_id, server, cursor):
    with open(transaction_ids_path + 'transaction_ids.txt', 'a') as transaction_ids_file:
        # Assemble the email to be published.
        email_object = construct_email(title, decoded_description)

        # Query the emails of individuals who have subscribed to the team in question.
        query = ("SELECT Email FROM Team AS T JOIN Subscription AS S ON S.TeamId = T.Id JOIN Email AS E ON E.Id = S.EmailId WHERE TeamName = '%s'" % (ep_team_ids_to_name[team_id]))
        cursor.execute(query)
        recipient_list = list(cursor.fetchall())

        # Send an email to each subscriber for the current team as long as we haven't sent them an email about this transfer already.
        for recipient in recipient_list:
            try:
                server.sendmail(sender_email, [recipient], email_object.as_string())
            except smtplib.SMTPRecipientsRefused:
                # Recipient refused the email, so just move on to the next email address in recipient_list.
                continue

        date_and_time = datetime.datetime.now()
        transaction_ids_file.write(transaction_id + ',' + str(date_and_time) + '\n')

# Assemble the list of transaction IDs that have already been published.
def update_transaction_ids_file():
    # List of transaction IDs that we've published less than 14 days ago.
    transaction_ids_list = []
    script_invocation_time = datetime.datetime.now()

    # Loop through each transaction listed in transaction_ids.txt to determine if we still need to keep track of it.
    with open(transaction_ids_path + 'transaction_ids.txt', 'r') as transaction_ids_file:
        transaction_ids_file_lines = transaction_ids_file.readlines()

    # Clear the transaction_ids.txt file and only write back the lines whose transactions we still want to keep track of.
    with open(transaction_ids_path + 'transaction_ids.txt', 'w') as transaction_ids_file:
        for line in transaction_ids_file_lines:
            # For each line in the file, parse out it's transaction ID and date it was put into the file.
            line_parts = re.search(r'(\d*),(.*)', line)
            transaction_id = line_parts.group(1)
            transaction_datetime = datetime.datetime.strptime(line_parts.group(2), '%Y-%m-%d %H:%M:%S.%f')

            # If the transaction is older than 14 days, don't bother keeping track of it anymore.
            time_difference = script_invocation_time - transaction_datetime
            if time_difference.days >= 14:
                continue

            # If we published the transaction less than 14 days ago, continue to keep track of it.
            transaction_ids_list.append(transaction_id)
            transaction_ids_file.write(line)

    return transaction_ids_list

# This method examines each of the 50 most recent entries in the EliteProspects RSS transaction for mentions of NCAA D1 hockey teams.
def process_feed(transaction_ids_list, server, cursor):
    # Query the EliteProspects transfers RSS feed.
    feed = feedparser.parse('https://www.eliteprospects.com/rss/transfers')

    if len(feed) == 0:
        raise Exception('The list of RSS feed entries is 0.')

    # In each the RSS feed's 50 most recent transfers, look for mentions of Michigan Tech or future or former players.
    for item in feed.entries:
        transaction_id = re.search(r'/t/(\d*)', item.guid).group(1)

        if transaction_id in transaction_ids_list:
            # Don't proceed if we've already send out a notification about this transfer.
            continue

        decoded_description = str(BeautifulSoup(item.description, features='html.parser'))

        teams_ids = re.findall(r'<a href="https:\/\/www\.eliteprospects\.com\/team\/(\d*)\/', decoded_description)

        # Only pass send_transaction_email() a team ID which correspond to an NCAA D1 team.
        ncaa_d1_team_ids = []
        for team_id in teams_ids:
            if team_id in ep_team_ids_to_name:
                # The current team_id corresponds to an NCAA D1 team.
                ncaa_d1_team_ids.append(team_id)

        # Send an email notification as long as the transaction one NCAA D1 team. If there's two, skip it because it'll be handled when looking at transfer portal spreadsheets.
        if len(ncaa_d1_team_ids) == 1:
            send_transaction_email(transaction_id, item.title, decoded_description, ncaa_d1_team_ids[0], server, cursor)

def main():
    # Connect to the Gmail server using TLS encryption.
    server = smtplib.SMTP('smtp.gmail.com', 587)
    server.set_debuglevel(True)
    server.connect('smtp.gmail.com', 587)
    server.starttls()
    server.login(smtp_username, gmail_app_password)

    # Set up database connection.
    connection = mysql.connector.connect(user=db_username, password=db_password, host=db_ip, database=database_name)
    cursor = connection.cursor()

    # Check EliteProspects for transactions that are NOT between two NCAA D1 teams.
    transaction_ids_list = update_transaction_ids_file()
    process_feed(transaction_ids_list, server, cursor)

    # Monitor online transfer portal spreadsheets for transfers involving two NCAA D1 teams.
    rink_live_portal_data = get_portal_spreadsheet_data(rink_live_spreadsheet_id, rink_live_tab_name)
    gopher_puck_live_portal_data = get_portal_spreadsheet_data(gopher_puck_live_shreadsheet_id, gopher_puck_live_tab_name)
    db_team_names = list(ep_team_ids_to_name.values())
    process_portal_spreadsheet(rink_live_portal_data, db_team_names, 2, 1, 6, 0, 11, 15)
    process_portal_spreadsheet(gopher_puck_live_portal_data, db_team_names, 1, 2, 3, 1, 5, 0)
    construct_and_send_transfer_message(server, cursor)

    # Close the connection to the database and Gmail server.
    cursor.close()
    connection.close()
    server.quit()

if __name__ == '__main__': 
    main()