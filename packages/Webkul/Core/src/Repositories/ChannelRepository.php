<?php

namespace Webkul\Core\Repositories;

use Illuminate\Support\Facades\Storage;
use Webkul\Core\Eloquent\Repository;
use Webkul\Core\Models\Channel;

class ChannelRepository extends Repository
{
    /**
     * Specify model class name.
     *
     * @return string
     */
    public function model(): string
    {
        return 'Webkul\Core\Contracts\Channel';
    }

    /**
     * Create.
     *
     * @param  array  $data
     * @return \Webkul\Core\Contracts\Channel
     */
    public function create(array $data)
    {

        $model = $this->getModel();

        foreach (core()->getAllLocales() as $locale) {
            foreach ($model->translatedAttributes as $attribute) {
                if (isset($data[$attribute])) {
                    $data[$locale->code][$attribute] = $data[$attribute];
                }
            }
        }

        $channel = parent::create($data);

        $channel->locales()->sync($data['locales']);

        $channel->currencies()->sync($data['currencies']);

        $channel->inventory_sources()->sync($data['inventory_sources']);

        $this->uploadImages($data, $channel);

        $this->uploadImages($data, $channel, 'favicon');

        return $channel;
    }

    /**
     * Update.
     *
     * @param  array  $data
     * @param  int  $id
     * @param  string  $attribute
     * @return \Webkul\Core\Contracts\Channel
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        $channel = parent::update($data, $id, $attribute);

        $channel->locales()->sync($data['locales']);

        $channel->currencies()->sync($data['currencies']);

        $channel->inventory_sources()->sync($data['inventory_sources']);

        // $this->uploadImages($data, $channel);
        $this->saveImages($data, $channel);

        // $this->uploadImages($data, $channel, 'favicon');
        $this->saveImages($data, $channel, 'favicon');

        return $channel;
    }

    /**
     * Upload images.
     *
     * @param  array  $data
     * @param  \Webkul\Core\Contracts\Channel  $channel
     * @param  string  $type
     * @return void
     */
    public function uploadImages($data, $channel, $type = 'logo')
    {
        if (request()->hasFile($type)) {
            $channel->{$type} = current(request()->file($type))->store('channel/' . $channel->id);

            $channel->save();
        } else {
            if (! isset($data[$type])) {
                if (! empty($data[$type])) {
                    Storage::delete($channel->{$type});
                }

                $channel->{$type} = null;

                $channel->save();
            }
        }
    }

    public function saveImages($data, $channel, $type = 'logo')
    {
        $options = [
            "ssl" => [
                "verify_peer"      => false,
                "verify_peer_name" => false,
            ],
        ];

        $context = stream_context_create($options);

        if (isset($data['logo_url'])) {
            // save logo url to channel

            //$channel->logo = $data['logo']->store('channel/' . $channel->id);
            $channel->logo = $data['logo_url'];
            //download logo
            $logo = file_get_contents($channel->logo, false, $context);
            $ext = pathinfo($channel->logo, PATHINFO_EXTENSION);
            $path = storage_path('app/public/logo.' . $ext);
            file_put_contents($path, $logo);

            $channel->save();
        }

        if (isset($data['favicon_url'])) {
            //$channel->favicon = $data['favicon']->store('channel/' . $channel->id);
            $channel->favicon = $data['favicon_url'];
            //download favicon
            $favicon = file_get_contents($channel->favicon, false, $context);
            $path = storage_path('app/public/favicon.ico');
            file_put_contents($path, $favicon);

            $channel->save();
        }
    }

    /**
     * Find a channel by its hostname.
     *
     * @param string $hostname
     * @return Channel|null
     */
    public function findByHostname(string $hostname): ?Channel
    {
        return Channel::where('hostname', $hostname)->first();
    }
}
