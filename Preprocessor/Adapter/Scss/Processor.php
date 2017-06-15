<?php

/**
 * @copyright  Copyright 2017 SplashLab
 */

namespace SplashLab\SassPreprocessor\Preprocessor\Adapter\Scss;

use Leafo\ScssPhp\Compiler;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Css\PreProcessor\Config;
use Magento\Framework\Phrase;
use Magento\Framework\View\Asset\ContentProcessorException;
use Magento\Framework\View\Asset\ContentProcessorInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Source;
use Psr\Log\LoggerInterface;

/**
 * Class Processor
 * @package SplashLab\SassPreprocessor
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Processor implements ContentProcessorInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var Source
     */
    private $assetSource;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @var Config
     */
    private $config;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param State $appState
     * @param Source $assetSource
     * @param DirectoryList $directoryList
     * @param Config $config
     */
    public function __construct(
        LoggerInterface $logger,
        State $appState,
        Source $assetSource,
        DirectoryList $directoryList,
        Config $config
    )
    {
        $this->logger = $logger;
        $this->appState = $appState;
        $this->assetSource = $assetSource;
        $this->directoryList = $directoryList;
        $this->config = $config;
    }

    /**
     * Process file content
     *
     * @inheritdoc
     * @throws ContentProcessorException
     */
    public function processContent(File $asset)
    {
        $path = $asset->getPath();
        try {
            $compiler = new Compiler();

            if ($this->appState->getMode() !== State::MODE_DEVELOPER) {
                $compiler->setFormatter("Leafo\ScssPhp\Formatter\Compressed");
            }

            // get correct path of temp files in view_preprocessed
            $varDir = $this->directoryList->getPath(DirectoryList::VAR_DIR);
            $filePath = $this->config->getMaterializationRelativePath();
            $pathDir = dirname($path);
            $tmpDir = $varDir . '/' . $filePath . '/' . $pathDir;
            $compiler->setImportPaths([
                $tmpDir
            ]);

            $content = $this->assetSource->getContent($asset);

            if (trim($content) === '') {
                return '';
            }

            gc_disable();
            $content = $compiler->compile($content);
            gc_enable();

            if (trim($content) === '') {
                $errorMessage = PHP_EOL . self::ERROR_MESSAGE_PREFIX . PHP_EOL . $path;
                $this->logger->critical($errorMessage);

                throw new ContentProcessorException(new Phrase($errorMessage));
            }

            return $content;

        } catch (\Exception $e) {

            $errorMessage = PHP_EOL . self::ERROR_MESSAGE_PREFIX . PHP_EOL . $path . PHP_EOL . $e->getMessage();
            $this->logger->critical($errorMessage);

            throw new ContentProcessorException(new Phrase($errorMessage));
        }
    }
}
