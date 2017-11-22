<?php

$_logFile = 'var/log/category_remove_schedule.' . date('YmdHis') . '.log';

function _formatTime($time)
{
    return sprintf('%02d:%02d:%02d', ($time / 3600), ($time / 60 % 60), $time % 60);
}

function _p($string = '')
{
    global $_logFile;
    echo $string . "\n";
    if (is_writeable($_logFile)) {
        error_log($string, 3, $_logFile);
    }
}

$timeStart = microtime(true);

require_once './app/bootstrap.php';

/** @var \Magento\Framework\App\Bootstrap $bootstrap */
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

/** @var \Magento\Framework\ObjectManager\ObjectManager $objectManager */
$objectManager = $bootstrap->getObjectManager();

/** @var \Magento\Framework\App\State $appState */
$appState = $objectManager->get('Magento\Framework\App\State');
$appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMIN);

$timeEnd = microtime(true);
$timeDiff = $timeEnd - $timeStart;
_p('Magento application initialization: ' . _formatTime($timeDiff));

/* ENTITY_TYPE_ID repair */

$timeStart = microtime(true);

/** @var \Magento\Framework\Module\ModuleResource $moduleResource */
$moduleResource = $objectManager->get('Magento\Framework\Module\ModuleResource');
/** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
$connection = $moduleResource->getConnection();

$connection->query('SET `foreign_key_checks` = 0;');
$data = $connection->fetchAll('
SELECT 
  GROUP_CONCAT(DISTINCT row_id SEPARATOR ",") as ids, 
  entity_id, 
  count(row_id) as cnt, 
  max(updated_in) as updated_in 
FROM catalog_category_entity 
WHERE created_in = 1 
GROUP BY entity_id 
HAVING cnt > 1
');
foreach ($data as $item) {
    $ids = explode(',', $item['ids']);
    foreach ($ids as $rowId) {
        if ($rowId === $item['entity_id']) {
            $connection->update('catalog_category_entity', ['updated_in' => $item['updated_in']], 'row_id = ' . $rowId);
            _p("Update in row_id '$rowId' field for update: updated_in = " . $item['updated_in']);
        } else {
            $connection->delete('catalog_category_entity', 'row_id = ' . $rowId);
            _p("Deleted row with row_id '$rowId'");
        }
    }
}
$timeEnd = microtime(true);
$timeDiff = $timeEnd - $timeStart;
_p('Success updated. Time - ' . _formatTime($timeDiff));
