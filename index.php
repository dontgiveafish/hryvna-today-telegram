<?php

require_once 'vendor/autoload.php';

/**
 * Function to format exchange rates
 *
 * @param stdClass $exchange_rate
 * @return string
 */
function formatValue(stdClass $exchange_rate)
{
    $value  = number_format(round($exchange_rate->value, 2), 2);
    $diff   = number_format(round($exchange_rate->diff, 2), 2);

    if ($diff > 0) {
        $diff = '+' . $diff;
    }

    return "$value ($diff)";
}

try {

    // include config
    $config = require_once 'config.php';

    // create a bot
    $bot = new \TelegramBot\Api\Client($config['bot_token'], $config['bot_tracker']);


    // bot command => api data type
    $commands_to_datatype = [
        'avg' => 'avg',
        'nbu' => 'government',
        'banks' => 'commercial',
        'black' => 'black',
        'interbank' => 'interbank'
    ];

    // create commands
    foreach ($commands_to_datatype as $command => $datatype) {

        $bot->command($command, function ($message) use ($bot, $datatype, $config) {

            try {

                // prepare memcached
                $m = new \Memcached();
                $m->addServer($config['memcache_server'], $config['memcache_port']);

                // try to get saved data
                $data = $m->get('telegram-hryvna-data');

                // if empty, call Hryvna Today API
                if (empty($data)) {

                    // get response
                    $response = \Unirest\Request::get($config['api_url'],
                        array(
                            'X-Mashape-Key' => $config['api_key'],
                            'Accept' => 'application/json'
                        )
                    );

                    // cache it
                    if (!empty($data = $response->body->data)) {
                        $m->set('telegram-hryvna-data', $data, $config['cache_time_to_live']);
                    }
                }

                // get base currency
                $currency_id = $config['base_currency_id'];

                // check if data is looking good
                if (empty($exchange_rate = $data->$currency_id->$datatype)) {
                    throw new \Exception();
                }

                // prepare answer
                if (in_array($datatype, ['avg', 'government'])) {
                    $answer =
                        formatValue($exchange_rate->avg);
                }
                else {
                    $answer =
                        formatValue($exchange_rate->buy) . ' / ' .
                        formatValue($exchange_rate->sale);
                }

                // add link sometimes
                if (rand(0, 100) < 10) {
                    $answer .= PHP_EOL . PHP_EOL . 'Get more on hryvna.today';
                }

            } catch (\Exception $e) {
                $answer = 'There was a problem performing your request. Please try again later or just visit hryvna.today';
            }

            $bot->sendMessage($message->getChat()->getId(), $answer);
        });

    }

    // run, hryvna, run!
    $bot->run();

} catch (\TelegramBot\Api\Exception $e) {
    // @todo add error log
    $e->getMessage();
}