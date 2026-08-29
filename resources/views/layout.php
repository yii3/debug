<?php

declare(strict_types=1);

use Yii3\Debug\DebugAsset;
use Yiisoft\Assets\AssetManager;
use Yiisoft\View\WebView;

/**
 * @var AssetManager $assetManager Yii asset manager.
 * @var string $content Rendered Debug Core content.
 * @var string $theme Resolved debugger theme.
 * @var string $title Document title.
 * @var WebView $this Yii web view instance.
 */
$assetManager->register(DebugAsset::class);

$this->addCssFiles($assetManager->getCssFiles());
$this->addCssStrings($assetManager->getCssStrings());
$this->addJsFiles($assetManager->getJsFiles());
$this->addJsStrings($assetManager->getJsStrings());
$this->addJsVars($assetManager->getJsVars());

$encode = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$favicon = $assetManager->getUrl(DebugAsset::class, 'svg/yii.svg');

$this->beginPage();
?>
<!doctype html>
<html lang="en" data-yii-debug-theme="<?= $encode($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="none">
    <title><?= $encode($title) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $encode($favicon) ?>">
    <?php $this->head() ?>
</head>
<body class="yii-debug">
<?php $this->beginBody() ?>
<?= $content ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage();
