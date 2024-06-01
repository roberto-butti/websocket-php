<?php

/*
001
Include OpenSwoole classes used in the script
*/

use OpenSwoole\WebSocket\{Frame, Server};
use OpenSwoole\Constant;
use OpenSwoole\Http\Request;
use OpenSwoole\Table;

/*
002
Instancing the Server on port 9501 , listening on 0.0.0.0 (accepting all incoming reqeust)
On TCP protocol (Constant::SOCK_TCP). If you want to enable Secure WebSocket you should use Constant::SSL
as forth parameter `Constant::SOCK_TCP || Constant::SSL` )
*/


$server = new Server(
    host: "0.0.0.0",
    port: 9501,
    mode: Server::SIMPLE_MODE,
    sockType: Constant::SOCK_TCP,
);

/*
003
Creating a Table (a two dimensions memory table) with fd and name fields
*/
$fds = new Table(1024);
$fds->column('fd', Table::TYPE_INT, 4);
$fds->column('name', Table::TYPE_STRING, 16);
$fds->create();

/*
004
Set certificates if you want Secure Web Socket
*/
/*
$server->set([
    'ssl_cert_file' => __DIR__ . '/localhost+2.pem',
    'ssl_key_file' => __DIR__ . '/localhost+2-key.pem'
]);
*/

/*
005
Listen the Start event.
"Start" is triggered once the websocket service is started
*/
$server->on("Start", function (Server $server) {
    echo "Swoole WebSocket Server is started at " . $server->host . ":" . $server->port . "\n";
});

/*
006
Listen the Open event.
"Open" is triggered once a client is connected
*/
$server->on('Open', function (Server $server, Request $request) use ($fds) {
    $fd = $request->fd;
    $clientName = sprintf("Client-%'.06d", $request->fd);
    $fds->set($request->fd, [
        'fd' => $fd,
        'name' => sprintf($clientName),
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
007
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
            $server->push($key, "FROM: {$sender} - MESSAGE: " . $frame->data);
        }
    }
});

/*
008
Listen the Close event.
"Close" is triggered once a client close the connection
*/
$server->on('Close', function (Server $server, int $fd) use ($fds) {
    $fds->del($fd);
    echo "Connection close: {$fd}, total connections: " . $fds->count() . "\n";
});

/*
009
Listen the Disconnect event.
"Disconnect" is triggered once a client loose the connection
*/
$server->on('Disconnect', function (Server $server, int $fd) use ($fds) {
    $fds->del($fd);
    echo "Disconnect: {$fd}, total connections: " . $fds->count() . "\n";
});

/*
010
Start the WebSocket server, so the "Start" event is triggered
*/
$server->start();
