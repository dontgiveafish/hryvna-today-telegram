import os
import json
import random
import http.client
import urllib.parse
from botocore.vendored import requests

telegram_bot_token = os.environ['TELEGRAM_BOT_TOKEN']
hryvna_api_host = os.environ['HRYVNA_API_HOST']
hryvna_api_key = os.environ['HRYVNA_API_KEY']
default_currency = os.environ['DEFAULT_CURRENCY']


class WrongCommandException(Exception):
    pass


def send_message(text, chat_id):
    url = "https://api.telegram.org/bot{}/sendMessage".format(telegram_bot_token)
    requests.get(url, params={'text': text, 'chat_id': chat_id})


def get_type(command):
    map = {
        '/avg': 'avg',
        '/nbu': 'government',
        '/banks': 'commercial',
        '/black': 'black',
        '/interbank': 'interbank',
    }

    if command in map:
        return map[command]

    raise WrongCommandException()


def get_rates(type):
    connection = http.client.HTTPSConnection(hryvna_api_host)
    connection.request("GET", "/v1/rates/today", headers={
        'x-rapidapi-host': hryvna_api_host,
        'x-rapidapi-key': hryvna_api_key,
    })

    res = connection.getresponse()
    data = json.loads(res.read().decode())
    rates = data['data'][default_currency][type]

    return format_rates(type, rates)


def format_rates(type, rates):
    if type == 'government':
        return format_rate(rates['avg'])

    return "{} {}".format(format_rate(rates['buy']), format_rate(rates['sale']))


def format_rate(rates):
    value = rates['value']
    diff = rates['diff']

    return "{0:.2f} ({1:+.2f})".format(float(value), float(diff))


def lambda_handler(event, context):
    message = json.loads(event['body'])
    chat_id = message['message']['chat']['id']

    try:
        text = message['message']['text']

        type = get_type(text)
        reply = get_rates(type)

        if random.randint(0, 10) < 5:
            reply += "\n\nGet more on hryvna.today"

        send_message(reply, chat_id)

    except WrongCommandException:
        send_message(
            "Unknown command, please type '/' and see the list of commands", chat_id
        )

    except BaseException:
        send_message(
            "There was a problem performing your request. Please try again later or just visit hryvna.today", chat_id
        )

    return {'statusCode': 200}
