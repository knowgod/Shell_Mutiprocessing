<?php

require_once('abstract.php');
require_once("Arkuznet/Mutiprocessing.php");

/**
 * Class Arkadyk_Multiprocess
 *
 * @category Shell
 * @package Shell_Arkuznet_Multiprocess
 * @author Arkadij Kuzhel <a.kuzhel@youwe.nl>
 */
class Arkuznet_Multiprocess_Sample extends Mage_Shell_Abstract
    implements Arkuznet_Multiprocessing_Interface
{
    use Arkuznet_Multiprocessing;

    /**
     * Collection page size
     */
    const PAGE_SIZE = 4;

    protected $_collection;

    /**
     * Main method
     */
    public function run()
    {
        if (!$this->isChild()) {
            $orders = $this->_getCollection();

            $this->initMultiprocess(1, $orders->getLastPageNumber(), 4, 'multiproc');

        } else {
            $this->initMultiprocess();
        }
    }

    /**
     * Proceed pages from Start to End
     */
    public function runSubProcess()
    {
        $start = $this->getArg(self::ARG_PAGE_START);
        $finish = $this->getArg(self::ARG_PAGE_FINISH);

        $this->log(__METHOD__ . " [$start, $finish] start");
        for ($page = $start; $page <= $finish; ++$page) {
            $orders = $this->_getCollection($page, true);

            $orders->getResource()->beginTransaction();

            foreach ($orders as $_order) {
                /** @var Mage_Sales_Model_Order $_order */
                $this->log(array($page, $_order->getIncrementId()));
            }

            $orders->getResource()->commit();
        }
        sleep(rand(1, 3));
        $this->log(__METHOD__ . " [$start, $finish] finish");
    }

    /**
     * @param int|null $currentPage
     * @param bool $reload
     * @return Mage_Sales_Model_Resource_Order_Collection
     */
    protected function _getCollection($currentPage = null, $reload = false)
    {
        if (!$this->_collection || $reload) {
            $this->_collection = Mage::getResourceModel('sales/order_collection')
                ->setPageSize(self::PAGE_SIZE);

            if (!is_null($currentPage)) {
                $this->_collection->setCurPage($currentPage);
            }
        }
        return $this->_collection;
    }

    /**
     * Use Magento default logger
     * or echo using trait's method
     *
     * @param $msq
     */
    public function log($msq)
    {
        $logFile = $this->getArg(static::ARG_LOG_TO);
        if ($logFile) {
            Mage::log($msq, Zend_log::INFO, $logFile, true);
        }
        if (!is_scalar($msq)) {
            $msq = var_export($msq, true);
        }
        Arkuznet_Multiprocessing::log($msq);
    }
}


$shell = new Arkuznet_Multiprocess_Sample();
$shell->run();
