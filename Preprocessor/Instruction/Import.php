<?php

/**
 * @copyright Copyright 2017 SplashLab
 */

// @codingStandardsIgnoreFile

namespace SplashLab\SassPreprocessor\Preprocessor\Instruction;

use Magento\Framework\Css\PreProcessor\FileGenerator\RelatedGenerator;
use Magento\Framework\View\Asset\File\NotFoundException;
use Magento\Framework\View\Asset\LocalInterface;
use Magento\Framework\View\Asset\NotationResolver;

/**
 * Class Import
 * @package SplashLab\SassPreprocessor
 * @ import instruction preprocessor
 */
class Import extends \Magento\Framework\Css\PreProcessor\Instruction\Import
{
    /**
     * Pattern of @import instruction
     */
    const REPLACE_PATTERN =
        '#@import\s+(\((?P<type>\w+)\)\s+)?[\'\"](?P<path>(?![/\\\]|\w:[/\\\])[^\"\']+)[\'\"]\s*?(?P<media>.*?);#';

    /**
     * @var \Magento\Framework\View\Asset\NotationResolver\Module
     */
    private $notationResolver;

    /**
     * @var array
     */
    protected $relatedFiles = [];

    /**
     * @var RelatedGenerator
     */
    private $relatedFileGenerator;

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    private $assetRepository;

    /**
     * Constructor
     *
     * @param NotationResolver\Module $notationResolver
     * @param \Magento\Framework\View\Asset\Repository $assetRepository
     * @param RelatedGenerator $relatedFileGenerator
     */
    public function __construct(
        NotationResolver\Module $notationResolver,
        \Magento\Framework\View\Asset\Repository $assetRepository,
        RelatedGenerator $relatedFileGenerator
    ) {
        $this->notationResolver = $notationResolver;
        $this->assetRepository = $assetRepository;
        $this->relatedFileGenerator = $relatedFileGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function process(\Magento\Framework\View\Asset\PreProcessor\Chain $chain)
    {
        $asset = $chain->getAsset();
        $contentType = $chain->getContentType();
        $replaceCallback = function ($matchContent) use ($asset, $contentType) {
            return $this->replace($matchContent, $asset, $contentType);
        };
        $content = $this->removeComments($chain->getContent());

        $processedContent = preg_replace_callback(self::REPLACE_PATTERN, $replaceCallback, $content);
        $this->relatedFileGenerator->generate($this);

        if ($processedContent !== $content) {
            $chain->setContent($processedContent);
        }
    }

    /**
     * Returns the content without commented lines
     *
     * @param string $content
     * @return string
     */
    private function removeComments($content)
    {
        return preg_replace("#(^\s*//.*$)|((^\s*/\*(?s).*?(\*/)(?!\*/))$)#m", '', $content);
    }

    /**
     * Retrieve information on all related files, processed so far
     *
     * BUG: this information about related files is not supposed to be in the state of this object.
     * This class is meant to be a service (shareable instance) without such a transient state.
     * The list of related files needs to be accumulated for the preprocessor,
     * because it uses a 3rd-party library, which requires the files to physically reside in the base same directory.
     *
     * @return array
     */
    public function getRelatedFiles()
    {
        return $this->relatedFiles;
    }

    /**
     * Clear the record of related files, processed so far
     * @return void
     */
    public function resetRelatedFiles()
    {
        $this->relatedFiles = [];
    }

    /**
     * Add related file to the record of processed files
     *
     * @param string $matchedFileId
     * @param LocalInterface $asset
     * @return void
     */
    protected function recordRelatedFile($matchedFileId, LocalInterface $asset)
    {
        $this->relatedFiles[] = [$matchedFileId, $asset];
    }

    /**
     * Return replacement of an original @import directive
     *
     * @param array $matchedContent
     * @param LocalInterface $asset
     * @param string $contentType
     * @return string
     */
    protected function replace(array $matchedContent, LocalInterface $asset, $contentType)
    {
        $fileNameOriginal = basename($matchedContent['path']);
        $fileName = $fileNameOriginal;
        $fileName = dirname($matchedContent['path']) . '/' . $fileName;
        $matchedFileId = $this->fixFileExtension($fileName, $contentType);

        $relatedAsset = $this->assetRepository->createRelated($matchedFileId, $asset);
        // if not found, try with underscore in file name
        if (!$this->testFile($relatedAsset)) {
            if ($fileNameOriginal[0] != '_') {
                $fileName = '_' . $fileNameOriginal;
            }
            $fileName = dirname($matchedContent['path']) . '/' . $fileName;
            $matchedFileId = $this->fixFileExtension($fileName, $contentType);
        }

        $this->recordRelatedFile($matchedFileId, $asset);
        $resolvedPath = $this->notationResolver->convertModuleNotationToPath($asset, $matchedFileId);
        $typeString = empty($matchedContent['type']) ? '' : '(' . $matchedContent['type'] . ') ';
        $mediaString = empty($matchedContent['media']) ? '' : ' ' . trim($matchedContent['media']);
        return "@import {$typeString}'{$resolvedPath}'{$mediaString};";
    }

    /**
     * Check if the source file exists
     *
     * @param LocalInterface $asset
     * @return boolean
     */
    protected function testFile($asset)
    {
        try {
            $asset->getSourceFile();
        } catch (NotFoundException $e) {
            return false;
        }
        return true;
    }

    /**
     * Resolve extension of imported asset according to exact format
     *
     * @param string $fileId
     * @param string $contentType
     * @return string
     * @link http://lesscss.org/features/#import-directives-feature-file-extensions
     */
    protected function fixFileExtension($fileId, $contentType)
    {
        if (!pathinfo($fileId, PATHINFO_EXTENSION)) {
            $fileId .= '.' . $contentType;
        }
        return $fileId;
    }
}
