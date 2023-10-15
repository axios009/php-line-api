<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';

$app = new \Slim\App;

$app->post('/webhook', function (Request $request, Response $response) {
    $body = $request->getBody();
    $data = json_decode($body, true);

    // Check if the request is from LINE by validating signature
    $channelSecret = '3c7622dcec1b2cd2566cb057cc1c1268';
    $hash = hash_hmac('sha256', $body, $channelSecret, true);
    $signature = base64_encode($hash);

    if ($request->getHeaderLine('X-Line-Signature') !== $signature) {
        return $response->withStatus(403, 'Invalid request signature');
    }

    foreach ($data['events'] as $event) {
        // Handle different types of events (e.g. messages, follows, etc.)
        if ($event['type'] == 'message') {
            handleLineMessage($event);
        }
        if ($event['type'] == 'follow') {
            $userId = $event['source']['userId'];
            storeUserId($userId);
        }

    }

    return $response->withStatus(200, 'OK');
});

function storeUserId($userId)
{
    $database->insert('line_users', ['userId' => $userId]);
}

function handleLineMessage($event)
{
    $replyToken = $event['replyToken'];
    $messageType = $event['message']['type'];
    $userId = $event['source']['userId'];
    $displayName = getUserName($userId);

    if ($displayName) {
        echo "User's display name is: " . $displayName;
    } else {
        echo "Unable to fetch user's display name.";
    }

    if ($messageType == 'text') {
        $text = $event['message']['text'];
        replyToUser($replyToken, "User ID : " . $userId . "\nUser Name : " . $displayName . "\nYou said: " . $text);
    }
}

function getUserName($userId)
{
    $headers = [
        'Authorization: Bearer tYiK0soFdj7fyUu8CWUt7cYOt0XyQUildRihLgt5+3ZCKMEQxadcceQBjvt1yTYvIPfPkAid1mnh40uKKTq/e83bvOi1jX2ZtnkCuy22MySHTcjbN6VEU+UXJiCs9NDa+pRY9fET4XMi073VkkpQtAdB04t89/1O/w1cDnyilFU='
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.line.me/v2/bot/profile/" . $userId);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    $userData = json_decode($result, true);

    if (isset($userData['displayName'])) {
        return $userData['displayName'];
    } else {
        return null;
    }
}


function replyToUser($replyToken, $text)
{
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer tYiK0soFdj7fyUu8CWUt7cYOt0XyQUildRihLgt5+3ZCKMEQxadcceQBjvt1yTYvIPfPkAid1mnh40uKKTq/e83bvOi1jX2ZtnkCuy22MySHTcjbN6VEU+UXJiCs9NDa+pRY9fET4XMi073VkkpQtAdB04t89/1O/w1cDnyilFU='
    ];

    $data = [
        'replyToken' => $replyToken,
        'messages' => [
            [
                'type' => 'text',
                'text' => $text
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.line.me/v2/bot/message/reply");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function pushMessageToUser($userId, $text)
{
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer tYiK0soFdj7fyUu8CWUt7cYOt0XyQUildRihLgt5+3ZCKMEQxadcceQBjvt1yTYvIPfPkAid1mnh40uKKTq/e83bvOi1jX2ZtnkCuy22MySHTcjbN6VEU+UXJiCs9NDa+pRY9fET4XMi073VkkpQtAdB04t89/1O/w1cDnyilFU='
    ];

    $data = [
        'to' => $userId,
        'messages' => [
            [
                'type' => 'text',
                'text' => $text
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.line.me/v2/bot/message/push");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}


$app->run();