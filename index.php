<?php

require_once 'vendor/autoload.php';
require_once 'helpers.php';

$config = require 'config.php';

try {
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
                // get response
                $response = \Unirest\Request::get($config['api_url'],
                    array(
                        'X-Mashape-Key' => $config['api_key'],
                        'Accept' => 'application/json'
                    )
                );

                $data = $response->body->data;
                if (empty($data)) {
                    throw new \Exception('Empty response');
                }

                // get base currency
                $currency_id = $config['base_currency_id'];

                // check if data is looking good
                if (empty($exchange_rate = $data->$currency_id->$datatype)) {
                    throw new \Exception('Bad response');
                }

                // prepare answer
                if (in_array($datatype, ['avg', 'government'])) {
                    $answer =
                        formatRate($exchange_rate->avg);
                } else {
                    $answer =
                        formatRate($exchange_rate->buy) . ' / ' .
                        formatRate($exchange_rate->sale);
                }

                // add link sometimes
                if (rand(0, 100) < 10) {
                    $answer .= PHP_EOL . PHP_EOL . 'Get more on hryvna.today';
                }

            } catch (\Throwable $e) {
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