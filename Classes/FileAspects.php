<?php
namespace Lemming\Imageoptimizer;

use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileAspects
{
    /**
     * @var OptimizeImageService
     */
    protected $service;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct()
    {
        $this->service = GeneralUtility::makeInstance(OptimizeImageService::class);
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Called when a new file is uploaded
     *
     * @param string $targetFileName
     * @param Folder $targetFolder
     * @param string $sourceFilePath
     * @return string Modified target file name
     */
    public function addFile($targetFileName, Folder $targetFolder, $sourceFilePath)
    {
        $this->service->process($sourceFilePath, pathinfo($targetFileName)['extension'], true);
    }

    /**
     * Called when a file is overwritten
     *
     * @param FileInterface $file The file to replace
     * @param string $localFilePath The uploaded file
     */
    public function replaceFile(FileInterface $file, $localFilePath)
    {
        $this->service->process($localFilePath, $file->getExtension(), true);
    }

    /**
     * Called when a file was processed
     *
     * @param \TYPO3\CMS\Core\Resource\Service\FileProcessingService $fileProcessingService
     * @param \TYPO3\CMS\Core\Resource\Driver\DriverInterface $driver
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $processedFile
     */
    public function processFile($fileProcessingService, $driver, $processedFile)
    {
        if (
            !$processedFile->exists()
            || !($processedFile->usesOriginalFile() === true || $processedFile->isUpdated() === true)
        ) {
            return;
        }

        $fileExtension = $processedFile->getExtension();
        if (empty($fileExtension) && $processedFile->getOriginalFile()) {
            $fileExtension = $processedFile->getOriginalFile()->getExtension();
        }

        if (!$this->service->fileTypeHasProcessor($fileExtension)) {
            return;
        }

        $fileForLocalProcessing = $processedFile->getForLocalProcessing();
        $processingWasSuccessfull = $this->service->process($fileForLocalProcessing, $fileExtension);
        if (!$processingWasSuccessfull) {
            $this->logger->log(
                LogLevel::ERROR,
                'Optimization failed.',
                [
                    'output' => $this->service->getOutput(),
                    'fileIdentifier' => $processedFile->getIdentifier(),
                    'storage' => $processedFile->getStorage()->getName()
                ]
            );
        }

        if ($processedFile->getSha1() !== sha1_file($fileForLocalProcessing)) {
            $processedFile->updateWithLocalFile($fileForLocalProcessing);
            $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
            $processedFileRepository->add($processedFile);
        }

        // remove the temporary processed file
        GeneralUtility::unlink_tempfile($fileForLocalProcessing);
    }
}
