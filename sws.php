<?php

define('PORT',8003);

$config = parse_ini_file(".env.ini");

$db = new PDO($config['db_dsn'], $config['db_user'], $config['db_pass']);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$table = new Swoole\Table(8192);
$table->column('id', Swoole\Table::TYPE_INT, 4);
$table->create();

echo "on port ",PORT,"\n";
$server = new Swoole\Websocket\Server("127.0.0.1", PORT);
$server->table = $table;

$server->on('open', function ($server, $req) {
    echo "connection open: {$req->fd}\n";
    // $fds[$req->fd] = 0;
    // print_r($fds);
    $server->table->set(strval($req->fd), ['id' => 0]);
});

$server->on('message', function ($server, $frame) {
    echo "received message: $frame->fd {$frame->data}\n";
    if ($frame->data === 'init') {
        $z = getById(0);
        if ($z) {
            $data = ['id' => intval($z[0]['id'])];
            $server->table->set(strval($frame->fd), $data);
            // $fds[$frame->fd] = $z[0]['id'];
            // echo json_encode($z) . "\n";
            // echo "push ", $fd, "\n";
            $server->push($frame->fd, json_encode($z));
        }
    } else {
        $j = json_decode(trim($frame->data), true);
        if ($j) {
            insertChat($j);
        }

        // print_r($fds);
        // foreach ($fds as $fd => $id) {
        foreach ($server->table as $fd => $data) {
            $id = $data['id'];
            $z = getById($id);
            if ($z) {
                // $fds[$fd] = $z[0]['id'];
                $data = ['id' => $z[0]['id']];
                $server->table->set($fd, $data);
                // echo json_encode($z) . "\n";
                echo "push ", $fd, "\n";
                $server->push(intval($fd), json_encode($z));
            }
        }
    }
});

$server->on('close', function ($server, $fd) {
    echo "connection close: {$fd}\n";
    // unset($fds[$fd]);
    $server->table->del(strval($fd));
});

$server->start();

function prepare($sql)
{
    global $db;
    try {
        return $db->prepare($sql);
    } catch (\Throwable $th) {
        //throw $th;
        print_r($th);
    }
}
function getById($id)
{
    global $db;
    $sql = "SELECT * from chat where id>? order by id desc limit 10";
    $s = prepare($sql);
    $s->execute([$id]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function insertChat($j)
{
    global $db;
    $s = prepare("INSERT into chat (username,content,created)
    values(:username, :content, now())");
    $s->execute($j);
}
