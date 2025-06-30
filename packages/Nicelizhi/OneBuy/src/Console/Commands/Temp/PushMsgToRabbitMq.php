<?php
namespace Nicelizhi\OneBuy\Console\Commands\Temp;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Artisan;
use Nicelizhi\Shopify\Console\Commands\Order\Post;
use Nicelizhi\Shopify\Console\Commands\Order\PostOdoo;

class PushMsgToRabbitMq extends Command {

    protected $signature = 'onebuy:push:msg:rabbitmq';
    protected $description = 'push msg to rabbitmq';

    public function handle()
    {

        $this->info("Push msg to rabbitMQ queue");

        // push msg to rabbitmq queue use laravel queue

        // $msg = [
        //     'msg' => 'hello rabbitmq'
        // ];

        // $msg = json_encode($msg);


        // $rabbitmq_host = config('queue.connections.rabbitmq.hosts');
        // var_dump($rabbitmq_host);
        // $host = $rabbitmq_host[0]['host'];
        // $port = $rabbitmq_host[0]['port'];
        // $user = $rabbitmq_host[0]['user'];
        // $password = $rabbitmq_host[0]['password'];
        // $vhost = $rabbitmq_host[0]['vhost'];

        // // vhost
        // $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);

        // // push msg to rabbitmq queue
        // $channel = $connection->channel();

        // $channel->queue_declare('hello', false, false, false, false);

        // $msg = new AMQPMessage('Hello World!');
        // $channel->basic_publish($msg, '', 'hello');

        // echo " [x] Sent 'Hello World!'\n";

        // var_dump($connection, $channel);

        $id = 1;

        $queue = config('app.name').':orders';

        $this->info("Push msg to rabbitMQ queue: ".$queue);

        if (1 || config('onebuy.is_sync_erp')) {
            Artisan::queue((new PostOdoo())->getName(), ['--order_id'=> $id])->onConnection('rabbitmq')->onQueue(config('app.name') . ':odoo_order');
        } else {
            Artisan::queue((new Post())->getName(), ['--order_id'=> $id])->onConnection('rabbitmq')->onQueue($queue);
        }


        // get msg from rabbitmq queue use laravel queue









    }

}