<?php


namespace craft\alibabaoss;

use craft\web\assets\cp\CpAsset;
use yii\web\AssetBundle;

/**
 * Asset bundle for the Dashboard
 */
class AlibabaOssBundle extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public $sourcePath = '@craft/alibabaoss/resources';

    /**
     * @inheritdoc
     */
    public $depends = [
        CpAsset::class,
    ];

    /**
     * @inheritdoc
     */
    public $js = [
        'js/editVolume.js',
    ];
}
