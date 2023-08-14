<?php

declare(strict_types=1);
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\alibabaoss;

use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\Exception\CloudFrontException;
use Aws\S3\Exception\S3Exception;
use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\flysystem\base\FlysystemFs;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Assets;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use DateTime;
use Iidestiny\Flysystem\Oss\OssAdapter;
use InvalidArgumentException;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Visibility;
use OSS\Core\OssException;
use OSS\OssClient;
use yii\base\Application;


/**
 * Class Fs
 *
 * @property mixed $settingsHtml
 * @property string $rootUrl
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class Fs extends FlysystemFs
{
    // Constants
    // =========================================================================

    public const STORAGE_STANDARD = 'STANDARD';
    public const STORAGE_REDUCED_REDUNDANCY = 'REDUCED_REDUNDANCY';
    public const STORAGE_STANDARD_IA = 'STANDARD_IA';

    /**
     * Cache key to use for caching purposes
     */
    public const CACHE_KEY_PREFIX = 'aws.';

    /**
     * Cache duration for access token
     */
    public const CACHE_DURATION_SECONDS = 3600;

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Alibaba Cloud OSS';
    }

    // Properties
    // =========================================================================

    /**
     * @var string Subfolder to use
     */
    public string $subfolder = '';

    /**
     * @var string OSS access key ID
     */
    public string $accessKeyId = '';

    /**
     * @var string AWS key accessKeySecret
     */
    public string $accessKeySecret = '';

    /**
     * @var string Bucket selection mode ('choose' or 'manual')
     */
    public string $bucketSelectionMode = 'choose';

    /**
     * @var string Bucket to use
     */
    public string $bucket = '';

    /**
     * @var string Region to use
     */
    public string $region = '';

    /**
     * @var string Cache expiration period.
     */
    public string $expires = '';

    /**
     * @var bool Set ACL for Uploads
     */
    public bool $makeUploadsPublic = true;

    /**
     * @var string S3 storage class to use.
     * @deprecated in 1.1.1
     */
    public string $storageClass = '';

    /**
     * @var string CloudFront Distribution ID
     */
    public string $cfDistributionId = '';

    /**
     * @var string CloudFront Distribution Prefix
     */
    public string $cfPrefix = '';

    /**
     * @var bool Whether facial detection should be attempted to set the focal point automatically
     */
    public bool $autoFocalPoint = false;

    /**
     * @var bool Whether the specified sub folder should be added to the root URL
     */
    public bool $addSubfolderToRootUrl = true;

    /**
     * @var array A list of paths to invalidate at the end of request.
     */
    protected array $pathsToInvalidate = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        if (isset($config['manualBucket'])) {
            if (isset($config['bucketSelectionMode']) && $config['bucketSelectionMode'] === 'manual') {
                $config['bucket'] = ArrayHelper::remove($config, 'manualBucket');
                $config['region'] = ArrayHelper::remove($config, 'manualRegion');
            } else {
                unset($config['manualBucket'], $config['manualRegion']);
            }
        }

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'accessKeyId',
                'accessKeySecret',
                'bucket',
                'region',
                'subfolder',
                'cfDistributionId',
                'cfPrefix',
            ],
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['bucket', 'region'], 'required'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('alibaba-oss/fsSettings', [
            'fs' => $this,
            'periods' => array_merge(['' => ''], Assets::periodList()),
        ]);
    }

    /**
     * Get the bucket list using the specified credentials.
     *
     * @param string|null $accessKeyId The key ID
     * @param string|null $accessKeySecret The key accessKeySecret
     * @return array
     * @throws InvalidArgumentException|OssException
     */
    public static function loadBucketList(?string $accessKeyId, ?string $accessKeySecret): array
    {
        $client = static::client($accessKeyId, $accessKeySecret, null);

        $objects = $client->listBuckets();

        if (empty($objects['Buckets'])) {
            return [];
        }

        $buckets = $objects['Buckets'];
        $bucketList = [];

        foreach ($buckets as $bucket) {
            try {
                $region = $client->getBucketLocation($bucket['Name']);
            } catch (OssException $e) {
                Craft::warning($e->getMessage());
                continue;
            }

            $bucketList[] = [
                'bucket' => $bucket['Name'],
                'region' => $region,
            ];
        }

        return $bucketList;
    }

    /**
     * @inheritdoc
     */
    public function getRootUrl(): ?string
    {
        $rootUrl = parent::getRootUrl();

        if ($rootUrl) {
            $rootUrl .= $this->_getRootUrlPath();
        }

        return $rootUrl;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @return OssAdapter
     * @throws OssException
     */
    protected function createAdapter(): FilesystemAdapter
    {
        return new OssAdapter($this->accessKeyId, $this->accessKeySecret, '', $this->bucket, true, $this->_subfolder());
//        return new AwsS3V3Adapter($client, Craft::parseEnv($this->bucket), $this->_subfolder(), new PortableVisibilityConverter($this->visibility()), null, [], false);
    }

    /**
     * Get the Alibaba Cloud OSS client.
     *
     * @param ?string $accessKeyId
     * @param ?string $accessKeySecret
     * @param ?string $bucket
     * @return OssClient|null
     */
    protected static function client(?string $accessKeyId, ?string $accessKeySecret, ?string $bucket): ?OssClient
    {
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, '', true);
        } catch (OssException $e) {
            Craft::warning($e->getMessage());
            return null;
        }
        return $ossClient;
    }

    /**
     * @inheritdoc
     */
    protected function addFileMetadataToConfig(array $config): array
    {
        if (!empty($this->expires) && DateTimeHelper::isValidIntervalString($this->expires)) {
            $expires = new DateTime();
            $now = new DateTime();
            $expires->modify('+' . $this->expires);
            $diff = (int) $expires->format('U') - (int) $now->format('U');
            $config['CacheControl'] = 'max-age=' . $diff;
        }

        return parent::addFileMetadataToConfig($config);
    }

    /**
     * @inheritdoc
     */
    protected function invalidateCdnPath(string $path): bool
    {
        if (!empty($this->cfDistributionId)) {
            if (empty($this->pathsToInvalidate)) {
                Craft::$app->on(Application::EVENT_AFTER_REQUEST, [$this, 'purgeQueuedPaths']);
            }

            // Ensure our paths are prefixed with configured subfolder
            $path = $this->_getRootUrlPath() . $path;

            $this->pathsToInvalidate[$path] = true;
        }

        return true;
    }

    /**
     * Purge any queued paths from the CDN.
     */
    public function purgeQueuedPaths(): void
    {
        if (!empty($this->pathsToInvalidate)) {
            // If there's a CloudFront distribution ID set, invalidate the path.
            $cfClient = $this->_getCloudFrontClient();
            $items = [];

            foreach ($this->pathsToInvalidate as $path => $bool) {
                $items[] = '/' . $this->_cfPrefix() . ltrim($path, '/');
            }

            try {
                $cfClient->createInvalidation(
                    [
                        'DistributionId' => Craft::parseEnv($this->cfDistributionId),
                        'InvalidationBatch' => [
                            'Paths' =>
                                [
                                    'Quantity' => count($items),
                                    'Items' => $items,
                                ],
                            'CallerReference' => 'Craft-' . StringHelper::randomString(24),
                        ],
                    ]
                );
            } catch (CloudFrontException $exception) {
                // Log the warning, most likely due to 404. Allow the operation to continue, though.
                Craft::warning($exception->getMessage());
            }
        }
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns the parsed subfolder path
     *
     * @return string
     */
    private function _subfolder(): string
    {
        if ($this->subfolder && ($subfolder = rtrim(App::parseEnv($this->subfolder), '/')) !== '') {
            return $subfolder . '/';
        }

        return '';
    }

    /**
     * Returns the root path for URLs
     *
     * @return string
     */
    private function _getRootUrlPath(): string
    {
        if ($this->addSubfolderToRootUrl) {
            return $this->_subfolder();
        }
        return '';
    }

    /**
     * Returns the parsed CloudFront distribution prefix
     *
     * @return string
     */
    private function _cfPrefix(): string
    {
        if ($this->cfPrefix && ($cfPrefix = rtrim(App::parseEnv($this->cfPrefix), '/')) !== '') {
            return $cfPrefix . '/';
        }

        return '';
    }

    /**
     * Get a CloudFront client.
     *
     * @return CloudFrontClient
     */
    private function _getCloudFrontClient(): CloudFrontClient
    {
        return new CloudFrontClient($this->_getConfigArray());
    }

    /**
     * Get the config array for AWS Clients.
     *
     * @return array
     */
    private function _getConfigArray(): array
    {
        return $this->_getCredentials();
    }

    /**
     * Return the credentials as an array
     *
     * @return array
     */
    private function _getCredentials(): array
    {
        return [
            'accessKeyId' => App::parseEnv($this->accessKeyId),
            'accessKeySecret' => App::parseEnv($this->accessKeySecret),
            'region' => App::parseEnv($this->region),
        ];
    }

    /**
     * Returns the visibility setting for the Fs.
     *
     * @return string
     */
    protected function visibility(): string
    {
        return $this->makeUploadsPublic ? Visibility::PUBLIC : Visibility::PRIVATE;
    }
}
