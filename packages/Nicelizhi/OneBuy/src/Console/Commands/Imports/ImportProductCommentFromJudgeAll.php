<?php

namespace Nicelizhi\OneBuy\Console\Commands\Imports;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Product\Repositories\ProductReviewRepository;
use Webkul\Product\Repositories\ProductReviewAttachmentRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Illuminate\Support\Facades\Event;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Storage;

class ImportProductCommentFromJudgeAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onebuy:import:products:comment:from:judge:all {prod_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'onebuy:import:products:comment:from:judge:all sync the product comments from judge.me';

    protected $num = 0;

    protected $prod_id = 0;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        protected ProductRepository $productRepository,
        protected ProductReviewRepository $productReviewRepository,
        protected CustomerRepository $customerRepository,
        protected ProductReviewAttachmentRepository $productReviewAttachmentRepository
    ) {
        $this->num = 100;
        parent::__construct();
    }

    private $cache_key = "checkout_v1_product_comments_";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $shop_domain = config("onebuy.judge.shop_domain");
        $api_token = config("onebuy.judge.api_token");

        if ($this->argument('prod_id')) {
            $this->prod_id = $this->argument("prod_id");
        }

        echo $this->prod_id . "\r\n";

        $client = new Client();

        $url = "https://judge.me/api/v1/reviews/count?shop_domain=" . $shop_domain . "&api_token=" . $api_token;


        $this->info($url);

        // @link https://judge.me/api/docs#tag/Reviews
        try {
            $response = $client->get($url, [
                'http_errors' => true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);
        } catch (ClientException $e) {
            var_dump($e);
        }

        $body = json_decode($response->getBody(), true);
        $count = $body['count'];
        $pages = ceil($count / $this->num);
        $client = new Client();

        for ($i = 1; $i <= $pages; $i++) {
            $this->info($i . " page start ");
            echo $i . "\r\n";
            $this->GetFromComments($i, $client);
        }
    }

    protected function GetFromComments($page, $client)
    {
        $shop_domain = config("onebuy.judge.shop_domain");
        $api_token = config("onebuy.judge.api_token");

        $url = "https://judge.me/api/v1/reviews?shop_domain=" . $shop_domain . "&api_token=" . $api_token . "&page=" . $page . "&per_page=" . $this->num;

        //$this->info($url);

        // @link https://judge.me/api/docs#tag/Reviews
        try {
            $response = $client->get($url, [
                'http_errors' => true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);
        } catch (ClientException $e) {
            var_dump($e);
        }

        $body = json_decode($response->getBody(), true);


        foreach ($body['reviews'] as $item) {


            if (!empty($this->prod_id)) {
                if($item['product_external_id']!=$this->prod_id) continue;
            }

            if ($item['published'] != true) continue;
            if ($item['rating'] < 4) continue; // only get the rating >= 4

            if (!empty($item['title'])) {
                $product = $this->productRepository->findBySlug($item['product_external_id']);

                if (!is_null($product)) {
                    $product->id = empty($product->parent_id) ? $product->id : $product->parent_id;
                    //insert into db
                    $review = \Webkul\Product\Models\ProductReview::where('title', $item['title'])->where("comment", $item['body'])->where("product_id", $product->id)->first();

                    if (is_null($review)) {

                        $customer = $this->customerRepository->findOneByField('email', $item['reviewer']['email']);
                        if (!$customer) {
                            $data = [];
                            $data['email'] = $item['reviewer']['email'];
                            $data['customer_group_id'] = 3;
                            $name = explode("-", $item['reviewer']['name']);

                            $data['first_name'] = $name[0];
                            $data['last_name'] = isset($name[1]) ? $name[1] : "";
                            $data['gender'] = null;
                            $data['phone'] = $item['reviewer']['phone'];

                            $password = rand(100000, 10000000);
                            Event::dispatch('customer.registration.before');
                            $data = array_merge($data, [
                                'password'    => bcrypt($password),
                                'is_verified' => 0,
                            ]);
                            $this->customerRepository->create($data);
                            $customer = $this->customerRepository->findOneByField('email', $item['reviewer']['email']);
                        }



                        $data = [];
                        $data['name'] = trim($item['reviewer']['name']);
                        $data['title'] = trim($item['title']);
                        $data['comment'] = trim($item['body']);
                        $data['rating'] = $item['rating'];
                        $data['status'] = "pending";
                        $data['product_id'] = empty($product->parent_id) ? $product->id : $product->parent_id; // why the product sku id is not the same it?
                        $data['attachments'] = [];
                        $data['customer_id'] = $customer->id;

                        if ($item['published'] == true) $data['status'] = "approved";
                        if ($item['reviewer']['name'] == 'Anonymous') $data['status'] = "pending";
                        if ($item['rating'] < 5) $data['status'] = "pending";
                        $data['status'] = "pending"; // default

                        $review = $this->productReviewRepository->create($data);

                        if (!empty($item['pictures'])) {

                            $attachments = [];

                            foreach ($item['pictures'] as $picture) {
                                $path = $this->downloadImageToLocal($picture['urls']['original'], $product->id);
                                $attachments = $this->productReviewAttachmentRepository->findWhere(['path' => $path, 'review_id' => $review->id])->first();
                                if (!empty($attachments)) continue;

                                $fileType[0] = "image";
                                $fileType[1] = "jpeg";

                                $attachments = [];
                                $attachments['type'] = $fileType[0];
                                $attachments['mime_type'] = $fileType[1];
                                $attachments['path'] = $path;
                                $attachments['review_id'] = $review->id;
                                dump($attachments);

                                $this->productReviewAttachmentRepository->create($attachments);
                            }
                        }
                    } else {
                        if (!empty($item['pictures'])) {
                            $attachments = [];
                            foreach ($item['pictures'] as $picture) {
                                $path = $this->downloadImageToLocal($picture['urls']['original'], $product->id);
                                $attachments = $this->productReviewAttachmentRepository->findWhere(['path' => $path, 'review_id' => $review->id])->first();
                                if (!empty($attachments)) continue;


                                $fileType[0] = "image";
                                $fileType[1] = "jpeg";

                                $attachments = [];
                                $attachments['type'] = $fileType[0];
                                $attachments['mime_type'] = $fileType[1];
                                $attachments['path'] = $this->downloadImageToLocal($path, $product->id);
                                $attachments['review_id'] = $review->id;
                                dump($attachments);

                                $this->productReviewAttachmentRepository->create($attachments);
                            }
                        }
                    }
                }
            }
        }
    }

    public function downloadImageToLocal($images_url, $productId)
    {
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );

        $info = pathinfo($images_url);
        $suffix = in_array($info['extension'], ['gif']) ? $info['extension'] : 'webp';
        $image_path = "product_review/" . $productId . "/" . $info['filename'] . "." . $suffix;
        $local_image_path = "storage/" . $image_path;

        // 创建必要的目录
        $directory = public_path(dirname($local_image_path));
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true)) {
                // 记录错误日志
                \Log::error("Failed to create directory: " . $directory);
                return $images_url;
            }
        }

        if (!file_exists(public_path($local_image_path))) {
            try {
                $contents = file_get_contents($images_url, false, stream_context_create($arrContextOptions));
                if ($contents === false) {
                    // 记录错误日志
                    \Log::error("Failed to get image contents from: " . $images_url);
                    return $images_url;
                }
                if (!Storage::disk("images")->put($local_image_path, $contents)) {
                    // 记录错误日志
                    \Log::error("Failed to save image to: " . $local_image_path);
                    return $images_url;
                }
            } catch (\Exception $e) {
                // 记录异常信息
                \Log::error("Exception occurred while downloading image: " . $e->getMessage());
                return $images_url;
            }
        }

        return env('APP_URL') . '/' . $local_image_path;
    }
}
