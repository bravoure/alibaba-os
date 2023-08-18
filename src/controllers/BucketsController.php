<?php

namespace craft\alibabaoss\controllers;

use Craft;
use craft\alibabaoss\Fs;
use craft\helpers\App;
use craft\web\Controller as BaseController;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * This controller provides functionality to load data from Alibaba.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class BucketsController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->defaultAction = 'load-bucket-data';
    }

    /**
     * Load bucket data for specified credentials.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionLoadBucketData(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $accessKeyId = App::parseEnv($request->getRequiredBodyParam('accessKeyId'));
        $accessKeySecret = App::parseEnv($request->getRequiredBodyParam('accessKeySecret'));

        try {
            return $this->asJson([
                'buckets' => Fs::loadBucketList($accessKeyId, $accessKeySecret),
            ]);
        } catch (\Throwable $e) {
            return $this->asFailure($e->getMessage());
        }
    }
}
