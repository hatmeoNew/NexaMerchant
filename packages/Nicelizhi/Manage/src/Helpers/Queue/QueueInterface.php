<?php

namespace Nicelizhi\Manage\Helpers\Queue;

interface QueueInterface
{
    public function push($channel, $msg);

    public function consume($channel);
}
