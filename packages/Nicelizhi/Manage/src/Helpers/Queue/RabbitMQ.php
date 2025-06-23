<?php

namespace Nicelizhi\Manage\Helpers\Queue;

use Nicelizhi\Shopify\Helpers\Utils;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQ implements QueueInterface
{

    const CONFIRM_QUEUE = 'confirm';

    protected $connection;
    protected $channel;

    public function __construct()
    {
        $config = [
            'host' => env('RABBITMQ_HOST'),
            'port' => env('RABBITMQ_PORT'),
            'user' => env('RABBITMQ_USER'),
            'password' => env('RABBITMQ_PASSWORD'),
            'vhost' => env('RABBITMQ_VHOST')
        ];

        try {
            $this->connection = new AMQPStreamConnection(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['vhost'],
                false,
                'AMQPLAIN',
                null,
                'en_US',
                30.0,
                100.0,
                null,
                false,
                3600,
                0.0
            );
        } catch (\AMQPConnectionException $e) {
            $this->reConnect();
        }
        $this->channel = $this->connection->channel();
    }

    public function push($channel, $data)
    {
        if (empty($channel)) {
            $message = "推送队列名为空 时间 :" . date('Y-m-d H:i:s') . PHP_EOL;
            Utils::sendFeishu($message);
        }
        try {
            $this->channel->queue_declare($channel, false, true, false, false);
        } catch (\Exception $e) {
            $this->reConnect();
            echo "MQ Connection reconnect " . PHP_EOL;
        }
        if (is_array($data)) {
            foreach ($data as $datum) {
                $this->channel->basic_publish(new AMQPMessage($datum['msg'], [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
                ]), '', $channel);
            }
        } else {
            if (is_string($data)) {
                $this->channel->basic_publish(new AMQPMessage($data, [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
                ]), '', $channel);
            }
        }
    }

    public function getChannelMessageNum($channel): array
    {
        if (empty($channel)) {
            $message = "推送队列名为空 时间 :" . date('Y-m-d H:i:s') . PHP_EOL;
            Utils::sendFeishu($message);
        }
        list($queueName, $messageCount, $consumerCount) = $this->channel->queue_declare($channel, true);
        return [
            'queueName' => $queueName,
            'messageCount' => $messageCount,
            'consumerCount' => $consumerCount,
        ];
    }

    public function consume($channel, $handler = null)
    {
        if (empty($channel)) {
            $message = "消费队列名为空 时间 :" . date('Y-m-d H:i:s') . PHP_EOL;
            Utils::sendFeishu($message);
        }
        $this->channel->queue_declare($channel, false, true, false, false);
        $exitFlag = false; // 新增退出标志
        $callback = function ($message) use ($handler, &$exitFlag) {
            if ($handler instanceof \Closure) {
                $result = call_user_func($handler, $message->body);
                if (true === $result) {
                    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
                }
                if ($result === false) {
                    $exitFlag = true;
                }
            }
        };
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($channel, '', false, false, false, false, $callback);

        while (count($this->channel->callbacks) && !$exitFlag) {
            $this->channel->wait(null, null, 60);
        }
    }

    public function consumePersist($channel, $handler = null)
    {
        if (empty($channel)) {
            $message = "消费队列名为空 时间 :" . date('Y-m-d H:i:s') . PHP_EOL;
            Utils::sendFeishu($message);
        }
        $this->channel->queue_declare($channel, false, false, false, false);
        $exitFlag = false; // 新增退出标志
        $callback = function ($message) use ($handler, &$exitFlag) {
            if ($handler instanceof \Closure) {
                $result = call_user_func($handler, $message->body);
                if (true === $result) {
                    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
                }
                if ($result === false) {
                    $exitFlag = true;
                }
            }
        };
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($channel, '', false, false, false, false, $callback);

        while (count($this->channel->callbacks) && !$exitFlag) {
            $this->channel->wait(null, null, 60);
        }
    }

    public function closeChannel()
    {
        $this->channel->close();
    }

    public function closeConnection()
    {
        $this->connection->close();
    }

    public function reConnect()
    {
        $config = [
            'host' => env('RABBITMQ_HOST'),
            'port' => env('RABBITMQ_PORT'),
            'user' => env('RABBITMQ_USER'),
            'password' => env('RABBITMQ_PASSWORD'),
            'vhost' => env('RABBITMQ_VHOST')
        ];
        $reconnectInterval = 2; // 初始重连间隔
        $maxReconnectInterval = 64; // 最大重连间隔

        while (true) {
            try {
                $this->connection->close(); // 关闭旧连接
                $this->connection = new AMQPStreamConnection(
                    $config['host'],
                    $config['port'],
                    $config['user'],
                    $config['password'],
                    $config['vhost'],
                    false,
                    'AMQPLAIN',
                    null,
                    'en_US',
                    30.0,
                    100.0,
                    null,
                    false,
                    60,
                    0.0
                );

                $this->channel = $this->connection->channel();
                // 连接成功后，退出循环
                break;
            } catch (\AMQPConnectionException $e) {
                // Log::channel('distribute_mapping')->critical("RabbitMQ reconnect failed: " . $e->getMessage());
                echo $e->getMessage() . PHP_EOL;
                // 使用指数退避策略增加重连间隔
                sleep($reconnectInterval);

                // 增加下一次重连的间隔，不超过最大值
                $reconnectInterval = min($reconnectInterval * 2, $maxReconnectInterval);
            }
        }
    }
}
