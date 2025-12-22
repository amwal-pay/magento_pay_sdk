<?php
namespace Amwal\Pay\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Psr\Log\LoggerInterface;
use Amwal\Pay\Helper\AmwalPay;
use Magento\Store\Model\ScopeInterface;
class AppleCert extends Value
{
    /**
     * @var File
     */
    protected $fileIo;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    protected $helper;
     protected $scopeConfig;
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        File $fileIo,
        LoggerInterface $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        AmwalPay $helper,
        array $data = []
    ) {
        parent::__construct($context, $registry, $scopeConfig, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->fileIo = $fileIo;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
    }

    public function afterSave()
    {
        try {
            $debug= $this->scopeConfig->getValue('payment/amwal_pay/debug', ScopeInterface::SCOPE_STORE);
            $appleCertContent = trim($this->getValue());
            $rootPath = BP . DIRECTORY_SEPARATOR; // Magento root path
            $wellKnownPath = $rootPath . '.well-known' . DIRECTORY_SEPARATOR;
            $appleFile = $wellKnownPath . 'apple-developer-merchantid-domain-association.txt';

            // Ensure directory exists
            if (!$this->fileIo->fileExists($wellKnownPath, false)) {
                $this->fileIo->mkdir($wellKnownPath, 0755);
            }

            if (empty($appleCertContent)) {
                // Remove file if no content
                if ($this->fileIo->fileExists($appleFile)) {
                    $this->fileIo->rm($appleFile);
                    $this->helper->addLogs($debug,AMWAL_DEBUG_FILE ,'[AmwalPay] Apple association file removed.');
                }
            } else {
                // Always write the file (even if content is same)
                $this->fileIo->write($appleFile, $appleCertContent, 0664);
                $this->helper->addLogs($debug,AMWAL_DEBUG_FILE, "[AmwalPay] Apple association file written to: $appleFile");
            }
        } catch (\Exception $e) {
            $this->helper->addLogs($debug,AMWAL_DEBUG_FILE, '[AmwalPay] Error writing Apple association file: ' . $e->getMessage());
            throw new LocalizedException(__('Cannot write Apple association file. Check permissions.'));
        }

        return parent::afterSave();
    }
}