<?php

/*
001
Include Swoole classes used in the script
*/
use Swoole\WebSocket\{Server, Frame};

/*
002
Instancing the Server on port 9501 , listening on 0.0.0.0 (accepting all incoming reqeust)
On TCP protocol (SWOOLE_SOCK_TCP) and Secure WebSocket (SWOOLE_SSL)
*/

$server = new Server("0.0.0.0", 9501, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);

/*
003
Creating a Table (a two dimensions memory table) with fd and name fields
*/
$fds = new Swoole\Table(1024);
$fds->column('fd', Swoole\Table::TYPE_INT, 4);
$fds->column('name', Swoole\Table::TYPE_STRING, 16);
$fds->create();

/*
003
Set certificates
*/
$server->set([
    'ssl_cert_file' => __DIR__ . '/localhost+2.pem',
    'ssl_key_file' => __DIR__ . '/localhost+2-key.pem'
]);

/*
004
Listen the Start event.
"Start" is triggered once the websocket service is started
*/
$server->on("Start", function (Server $server) {
    echo "Swoole WebSocket Server is started at " . $server->host . ":" . $server->port . "\n";
});

/*
008
Listen the Open event.
"Open" is triggered once a client is connected
*/
$server->on('Open', function (Server $server, Swoole\Http\Request $request) use ($fds) {
    $fd = $request->fd;
    $clientName = sprintf("Client-%'.06d\n", $request->fd);
    $fds->set($request->fd, [
        'fd' => $fd,
        'name' => sprintf($clientName)
    ]);
    echo "Connection <{$fd}> open by {$clientName}. Total connections: " . $fds->count() . "\n";
    foreach ($fds as $key => $value) {
        if ($key == $fd) {
            $server->push($request->fd, "Welcome {$clientName}, there are " . $fds->count() . " connections");
        } else {
            $server->push($key, "A new client ({$clientName}) is joining to the party");
        }
    }
});

/*
009
Listen the Message event.
"Message" is triggered once a client sent a message to WebSocket service
*/
$server->on('Message', function (Server $server, Frame $frame) use ($fds) {
    $sender = $fds->get(strval($frame->fd), "name");
    echo "Received from " . $sender . ", message: {$frame->data}" . PHP_EOL;
    foreach ($fds as $key => $value) {
        if ($key == $frame->fd) {
            $server->push($frame->fd, "Message sent");
        } else {
            $server->push($key,  "FROM: {$sender} - MESSAGE: " . $frame->data);
        }
    }
});

/*
010
Listen the Close event.
"Close" is triggered once a client close the connection
*/
$server->on('Close', function (Server $server, int $fd) use ($fds) {
    $fds->del($fd);
    echo "Connection close: {$fd}, total connections: " . $fds->count() . "\n";
});

/*
011
Listen the Disconnect event.
"Disconnect" is triggered once a client loose the connection
*/
$server->on('Disconnect', function (Server $server, int $fd) use ($fds) {
    $fds->del($fd);
    echo "Disconnect: {$fd}, total connections: " . $fds->count() . "\n";
});

/*
012
Start the WebSocket server, so the "Start" event is triggered
*/
$server->start();
