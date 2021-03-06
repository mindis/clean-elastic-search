<?php

require_once('vendor/autoload.php');

function checkAccess()
{
    $xml = new SimpleXMLElement(file_get_contents('app/etc/local.xml'));
    if (!isset($xml->global->elasticsearch->secret)) {
        throw new Exception("Missing elasticsearch secret config in local.xml");
    }

    if (!isset($_GET['key'])) {
        throw new Exception("Missing or invalid key");
    }

    if ($_GET['key'] != (string)$xml->global->elasticsearch->secret) {
        throw new Exception("Missing or invalid key");
    }

    return true;
}

function getElasticaIndex()
{
    $xml = new SimpleXMLElement(file_get_contents('app/etc/local.xml'));
    if (!isset($xml->global->elasticsearch)) {
        throw new Exception("Missing elasticsearch config in local.xml");
    }

    if (! (string)$xml->global->elasticsearch->index) {
        throw new Exception("Missing elasticsearch index name in local.xml");
    }

    $elasticaClient = new \Elastica\Client(array(
        'host' => (string)$xml->global->elasticsearch->host,
        'port' => (string)$xml->global->elasticsearch->port,
    ));

    $index = $elasticaClient->getIndex((string)$xml->global->elasticsearch->index);

    return $index;
}

/**
 * @param $result \Elastica\Result
 */
function getResultUrl($result)
{
    $baseUrl = $_GET['base_url'];
    $data = $result->getData();
    $type = $result->getType();

    if ($type == 'customer') {
        return $baseUrl . 'customer/edit/id/' . $result->getId();
    } elseif ($type == 'order') {
        return $baseUrl . 'sales_order/view/order_id/' . $result->getId();
    } elseif ($type == 'product') {
        return $baseUrl . 'catalog_product/edit/id/' . $result->getId();
    } elseif ($type == 'config') {
        return $baseUrl . 'system_config/edit/section/' . $data['section_code'];
    }

    throw new Exception("Can't determine the type of this result");
}

/**
 * @param $result \Elastica\Result
 */
function getName($result)
{
    $type = $result->getType();
    $data = $result->getData();

    if (in_array($type, array('order', 'customer'))) {
        return $data['fullname'];
    } elseif ($type == 'product') {
        return $data['name'];
    } elseif ($type == 'config') {
        return $data['field'];
    }

    throw new Exception("Can't determine the type of this result");
}

/**
 * @param $result \Elastica\Result
 */
function getDescription($result)
{
    $data = $result->getData();

    if ($result->getType() == 'customer') {
        return $data['email'];
    } elseif ($result->getType() == 'order') {
        return $data['increment_id'] . ' - ' . $data['sku_list'];
    } elseif ($result->getType() == 'product') {
        return $data['description'];
    } elseif ($result->getType() == 'config') {
        return $data['section'] . ' > ' . $data['group'];
    }
}

checkAccess();

$elasticaIndex = getElasticaIndex();

$query = isset($_REQUEST['query']) ? $_REQUEST['query'] : null;
if (!$query) {
    die("Missing ?query");
}

$elasticaQueryString  = new \Elastica\Query\MultiMatch();
$elasticaQueryString->setFields(array(
    'firstname', 'lastname', 'fullname',
    'email', 'increment_id',
    'field', 'section', 'group',
    'name'
));
$elasticaQueryString->setQuery($query);
$elasticaQueryString->setParam('type', 'phrase_prefix');

$elasticaQuery = new \Elastica\Query();
$elasticaQuery->setQuery($elasticaQueryString);

$elasticaResultSet = $elasticaIndex->search($elasticaQuery);
$elasticaResults = $elasticaResultSet->getResults();
$totalResults = $elasticaResultSet->getTotalHits();

?>

<ul>
    <?php if (! $totalResults): ?>
        <li id="error" url="#">
            <div style="float:right; color:red; font-weight:bold;">[Error]</div>
            <strong>No results</strong><br/>
            <span class="informal">No results found for <em><?php echo $query; ?></em></span>
        </li>
    <?php endif; ?>
    <?php foreach ($elasticaResults as $elasticaResult): $data = $elasticaResult->getData(); ?>
        <li id="" url="<?php echo getResultUrl($elasticaResult); ?>/">
            <div style="float:right; color:red; font-weight:bold;">[
                <?php echo $elasticaResult->getType(); ?>
            ]</div>
            <strong><?php echo getName($elasticaResult); ?></strong><br/>
            <span class="informal">
                <?php echo getDescription($elasticaResult); ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
