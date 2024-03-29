<?php

namespace Astroselling\FalabellaSdk;

use Astroselling\FalabellaSdk\Exceptions\FetchException;
use Astroselling\FalabellaSdk\Exceptions\FetchProductException;
use Astroselling\FalabellaSdk\Models\FalabellaFeed;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Linio\SellerCenter\Application\Configuration;
use Linio\SellerCenter\Contract\ProductFilters;
use Linio\SellerCenter\Contract\ProductStatus;
use Linio\SellerCenter\Exception\ErrorResponseException;
use Linio\SellerCenter\Model\Brand\Brand;
use Linio\SellerCenter\Model\Category\Category;
use Linio\SellerCenter\Model\Product\BusinessUnit;
use Linio\SellerCenter\Model\Product\BusinessUnits;
use Linio\SellerCenter\Model\Product\GlobalProduct;
use Linio\SellerCenter\Model\Product\Image;
use Linio\SellerCenter\Model\Product\Images;
use Linio\SellerCenter\Model\Product\ProductData;
use Linio\SellerCenter\Model\Product\Products;
use Linio\SellerCenter\Model\Seller\Seller;
use Linio\SellerCenter\SellerCenterSdk;
use Linio\SellerCenter\Service\ProductManager;
use Linio\SellerCenter\Service\WebhookManager;

class FalabellaSdk
{
    protected SellerCenterSdk $sdk;
    protected bool $customLogCalls;
    protected string $userName;
    protected string $operatorCode;
    public const URL = 'https://sellercenter-api.falabella.com';
    public const STAGING_URL = 'https://sellercenter-api-staging.falabella.com';

    public function __construct(string $userName, string $apiKey, string $countryISO, ?string $sellerId = null)
    {
        $this->operatorCode = [
            'ARG' => 'faar',
            'BRA' => 'fabr',
            'CHL' => 'facl',
            'COL' => 'faco',
            'MEX' => 'famx',
            'PER' => 'fape',
            'TST' => 'facl',
            'URY' => 'fauy',
        ][$countryISO];

        $client = new Client();
        $configuration = new Configuration(
            $apiKey, // key
            $userName, // username
            $countryISO == 'TST' ? self::STAGING_URL : self::URL, // endpoint
            '1.0', // version
            'SDK', // source
            $sellerId, // sellerId
            'PHP', // language
            (string) phpversion(), // languageVersion
            'ASTROSELLING', // integrator
            strtoupper(substr($this->operatorCode, -2)) // country
        );
        $this->sdk = new SellerCenterSdk($configuration, $client);
        $this->userName = $userName; // Used only for logging
        $this->customLogCalls = config('falabellasdk.custom_log_calls');
    }

    private function exceptionFromErrorResponse(ErrorResponseException $e)
    {
        return new FetchException(
            new Exception('Error response exception', $e->getCode()),
            [
                'Called From' => 'Falabella get Order Items',
                'Type' => $e->getType(),
                'Action' => $e->getAction(),
                'Message' => $e->getMessage(),
                'Falabella Username' => $this->userName,
            ]
        );
    }

    public function getProducts(int $limit, int $offset, string $filter = null): array
    {
        if (! $filter) {
            $filter = ProductManager::DEFAULT_FILTER;
        }
        if (! in_array($filter, ProductFilters::FILTERS)) {
            throw new FetchProductException(
                new Exception('Unknown product filter: '. $filter, 500),
                ['Called From' => 'Falabella get Products']
            );
        }

        try {
            $this->logCall("getAllProducts (filter: $filter)");

            return $this->sdk->globalProducts()->getProductsFromParameters(
                null, //CreatedAfter
                null, //createdBefore
                null, //search
                $filter,
                $limit,
                $offset,
                null, // skuSellerList
                null, // updatedAfter
                null, // updatedBefore
            );
        } catch (RequestException $e) {
            throw new FetchProductException($e, [
                'Called From' => 'Falabella get Products',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }
    }

    public function deleteProducts(array $deleteIds): FalabellaFeed
    {
        try {
            $products = new Products();
            foreach ($deleteIds as $idInMkp) {
                $prod = GlobalProduct::fromSku($idInMkp);
                $products->add($prod);
            }
            $this->logCall('deleteProducts');
            $feedResponse = $this->sdk->globalProducts()->productRemove($products);
            $feed = $this->sdk->feeds()->getFeedStatusById($feedResponse->getRequestId());

            $tarvosFeed = FalabellaFeed::saveFromSellerCenter($feed);
        } catch (RequestException $e) {
            throw new FetchException($e, [
                'Called From' => 'Delete Products',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }

        return $tarvosFeed;
    }

    public function getOrders(int $limit, int $offset): array
    {
        // TODO: cambiar esto hardcodeado y optimizar las que trae
        // $after = new DateTime('-3 day');
        $after = new DateTime('-1 month');
        $sortBy = 'created_at';
        $sortDir = 'DESC';

        try {
            $this->logCall('getOrdersCreatedAfter');

            return $this->sdk->orders()->getOrdersCreatedAfter($after, $limit, $offset, $sortBy, $sortDir);
        } catch (RequestException $e) {
            throw new FetchException($e, [
                'Called From' => 'Falabella get Orders',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
                'Falabella Username' => $this->userName,
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }
    }

    public function getMultipleOrderItems(array $orderIdList): array
    {
        try {
            if (count($orderIdList) == 0) {
                return [];
            }
            $this->logCall('getMultipleOrderItems');

            return $this->sdk->orders()->getMultipleOrderItems($orderIdList);
        } catch (RequestException $e) {
            throw new FetchException($e, [
                'Called From' => 'Falabella get Order Items',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }
    }

    public function getProductsBySku(array $skus): array
    {
        try {
            if (count($skus) == 0) {
                return [];
            }
            $this->logCall('getProductsBySellerSku');

            return $this->sdk->globalProducts()->getProductsBySellerSku($skus);
        } catch (RequestException $e) {
            throw new FetchProductException($e, [
                'Called From' => 'Falabella get Products By Sku',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }
    }

    public function getCategories(): array
    {
        $this->logCall('getCategoryTree');

        return $this->sdk->categories()->getCategoryTree();
    }

    public function getCategoryAttributes(int $categoryId): array
    {
        $this->logCall('getCategoryAttributes');

        return $this->sdk->categories()->getCategoryAttributes($categoryId);
    }

    public function getBrands(): array
    {
        $this->logCall('getBrands');

        return $this->sdk->brands()->getBrands();
    }

    public function getFeeds(?int $offset = 0, ?int $limit = 10): array
    {
        $this->logCall('getFeedOffsetList');

        return $this->sdk->feeds()->getFeedOffsetList($offset, $limit);
    }

    public function getQc(array $skus): array
    {
        $this->logCall('getQcStatusBySkuSellerList');

        return $this->sdk->qualityControl()->getQcStatusBySkuSellerList($skus);
    }

    public function updateFeed(string $feedId): FalabellaFeed
    {
        try {
            $this->logCall('getQc');
            $feed = $this->sdk->feeds()->getFeedStatusById($feedId);
            $localFeed = FalabellaFeed::saveFromSellerCenter($feed);

            return $localFeed;
        } catch (RequestException $e) {
            throw new FetchException($e, [
                'Called From' => 'Update Feed',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }
    }

    public function publishProducts(array $products): FalabellaFeed
    {
        $globalProducts = new Products();
        foreach ($products as $product) {
            $primaryCategory = Category::fromId($product['PrimaryCategory']);
            $brand = Brand::fromName($product['Brand']);
            $images = new Images();
            $productData = new ProductData(
                $product['ConditionType'] ?? null,
                $product['PackageHeight'] ?? null,
                $product['PackageWidth'] ?? null,
                $product['PackageLength'] ?? null,
                $product['PackageWeight'] ?? null
            );
            if (isset($product['ShortDescription'])) {
                $productData->add('ShortDescription', $product['ShortDescription']);
            }

            $attributes = array_filter(
                $product,
                fn ($a) => ! in_array(
                    $a,
                    [
                        'PrimaryCategory',
                        'Brand',
                        'ConditionType',
                        'PackageHeight',
                        'PackageWidth',
                        'PackageLength',
                        'PackageWeight',
                        'ShortDescription',
                        'Price',
                        'Quantity',
                        'SellerSku',
                        'Name',
                        'Variation',
                        'Description',
                        'ProductId',
                        'TaxClass',
                        'ParentSku',
                    ]
                ),
                ARRAY_FILTER_USE_KEY
            );

            foreach ($attributes as $name => $value) {
                $productData->add($name, $value);
            }

            $businessUnits = new BusinessUnits();

            $businessUnits->add(
                new BusinessUnit(
                    $this->operatorCode,
                    $product['Price'],
                    $product['Quantity'],
                    ProductStatus::ACTIVE
                )
            );

            $globalProduct = GlobalProduct::fromBasicData(
                $product['SellerSku'], // string
                $product['Name'], // string
                $product['Variation'] ?? null, // string
                $primaryCategory, // Category
                $product['Description'], // string
                $brand, // Brand
                $businessUnits,
                $product['ProductId'], // string
                $product['TaxClass'] ?? null, // nulo|string
                $productData, // ProductData
                $images, //Images will be ignored when creating, and used when updating
                ProductStatus::ACTIVE
            );

            if (isset($product['Color'])) {
                $globalProduct->setColor($product['Color']);
            }

            if (isset($product['ColorBasico'])) {
                $globalProduct->setColorBasico($product['ColorBasico']);
            }

            if (isset($product['Size'])) {
                $globalProduct->setSize($product['Size']);
            }

            if (isset($product['Talla'])) {
                $globalProduct->setTalla($product['Talla']);
            }

            $globalProduct->setParentSku($product['ParentSku']);
            $globalProducts->add($globalProduct);
        }

        try {
            $this->logCall('productCreate');
            $feedResponse = $this->sdk->globalProducts()->productCreate($globalProducts);
            $this->logCall('getFeedStatusById');
            $feed = $this->sdk->feeds()->getFeedStatusById($feedResponse->getRequestId());
            $localFeed = FalabellaFeed::saveFromSellerCenter($feed);

            return $localFeed;
        } catch (RequestException $e) {
            throw new FetchException($e, [
                'Called From' => 'Create Product',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
                'Products' => $products,
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }
    }

    public function updateProducts(array $updateData): FalabellaFeed
    {
        try {
            $products = new Products();
            foreach ($updateData as $sku => $prodData) {
                $product = GlobalProduct::fromSku($sku);
                $businessUnits = new BusinessUnits();

                $hasSaleStart = isset($prodData['sale_start']) && $prodData['sale_start'];
                $hasSaleEnd = isset($prodData['sale_end']) && $prodData['sale_end'];

                $businessUnits->add(
                    new BusinessUnit(
                        $this->operatorCode,
                        $prodData['price'],
                        $prodData['stock'],
                        ProductStatus::ACTIVE,
                        null,
                        null,
                        $prodData['sale_price'] ?? null,
                        $hasSaleStart ? Carbon::parse($prodData['sale_start']) : null,
                        $hasSaleEnd ? Carbon::parse($prodData['sale_end']) : null,
                    )
                );

                $product->setBusinessUnits($businessUnits);

                $products->add($product);
            }

            $this->logCall('productUpdate');

            $feedResponse = $this->sdk->globalProducts()->productUpdate($products);
            $feed = $this->sdk->feeds()->getFeedStatusById($feedResponse->getRequestId());

            $localFeed = FalabellaFeed::saveFromSellerCenter($feed);
        } catch (RequestException $e) {
            throw new FetchException($e, [
                'Called From' => 'Update Product',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
                'UpdateData' => $updateData,
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }

        return $localFeed;
    }

    public function publishProductImages(array $images): FalabellaFeed
    {
        $imgSend = [];
        foreach ($images as $sku => $urls) {
            $imgSend[$sku] = [];
            foreach ($urls as $url) {
                $imgSend[$sku][] = new Image($url);
            }
        }

        try {
            $this->logCall('addImage');
            $feedResponse = $this->sdk->globalProducts()->addImage($imgSend);
            $this->logCall('getFeedStatusById');
            $feed = $this->sdk->feeds()->getFeedStatusById($feedResponse->getRequestId());
            $localFeed = FalabellaFeed::saveFromSellerCenter($feed);
        } catch (RequestException $e) {
            throw new FetchException($e, [
                'Called From' => 'Add Images',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
                'UpdateData' => $images,
            ]);
        } catch (ErrorResponseException $e) {
            throw $this->exceptionFromErrorResponse($e);
        }

        return $localFeed;
    }

    private function logCall(string $method): void
    {
        if (! $this->customLogCalls) {
            return;
        }
        Log::channel('plain')->debug(sprintf(
            "[%s] | Falabella Call: %s",
            $this->userName,
            $method
        ));
    }

    /**
     * Devuelve el manager para gestionar los Webhooks.
     *
     * @return WebhookManager
     */
    public function getWebHook(): WebhookManager
    {
        return $this->sdk->webhooks();
    }

    /**
     * Devuelve una orden.
     *
     * @param int $orderId
     * @return object
     * @throws FetchException
     */
    public function getOrder(int $orderId): object
    {
        try {
            $this->logCall('getOrder');

            return $this->sdk->orders()->getOrder($orderId);
        } catch (RequestException $e) {
            throw new FetchException($e, [
                'Called From' => 'Falabella get Order',
                'Response' => Message::toString($e->getResponse()),
                'Response Code' => $e->getResponse()->getStatusCode()
                    . " (" . $e->getResponse()->getReasonPhrase() . ")",
                'Request' => Message::toString($e->getRequest()),
                'Falabella Username' => $this->userName,
            ]);
        } catch (ErrorResponseException $e) {
            throw new FetchException(
                new Exception('Error response exception', $e->getCode()),
                [
                    'Called From' => 'Falabella get Order',
                    'Type' => $e->getType(),
                    'Action' => $e->getAction(),
                    'Falabella Username' => $this->userName,
                ]
            );
        }
    }

    public function getSeller(): Seller
    {
        return $this->sdk->globalSeller()->getSellerByUser();
    }
}
