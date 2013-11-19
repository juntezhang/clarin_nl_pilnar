<?php
#@-------------------------------------------------------------------------------------
#@ By Junte Zhang <juntezhang@gmail.com> 2013 
#@ Distributed under the GNU General Public Licence
#@
#@ Script to search in index
#@-------------------------------------------------------------------------------------

mb_http_output("UTF-8");
ob_start("mb_output_handler");

/**
* @file
* Service for retrieval for PILNAR written by JZ
*
* Currently requires json_decode which is bundled with PHP >= 5.2.0.
*
* http://code.google.com/p/solr-php-client/
*/

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: content-type');
header("Content-type: application/json; charset=utf-8");

require_once(dirname(__FILE__) .'../../SolrPhpClient/Apache/Solr/Service.php');
require_once(dirname(__FILE__) .'../../SolrPhpClient/Apache/Solr/HttpTransport/CurlNoReuse.php');

$transportInstance = new Apache_Solr_HttpTransport_CurlNoReuse();

// Replace these arguments as needed.
//$solr = new Apache_Solr_Service( 'localhost', '8983', '/solr/pilnar', $transportInstance );
$solr = new Apache_Solr_Service( 'yago.meertens.knaw.nl', '80', '/solr/pilnar', $transportInstance );

$solr->setHttpTransport($transportInstance);

//$pingresponse = $solr->ping(); print $pingresponse;

$query = $_GET['query'];
$keys = '';
if (isset($query)) 
{
	$params = array();

	// The names of Solr parameters that may be specified multiple times.
	$multivalue_keys = array('bf', 'bq', 'facet.date', 'facet.date.other', 'facet.field', 'facet.query', 'fq', 'pf', 'qf');
	$pairs = explode('&', $query);
	foreach ($pairs as $pair) 
	{
		$value = "";
		$pattern = "/=/";
		if(preg_match($pattern, $pair)) 
		{
			list($key, $value) = explode('=', $pair, 2);
			$value = urldecode($value);
			if (in_array($key, $multivalue_keys)) 
			{
				$params[$key][] = $value;
			}
			elseif ($key == 'q') 
			{
				$keys = $value;
			}
			else 
			{
				$params[$key] = $value;
			}
		}
	}

	//$keys = utf8_urldecode($keys);
	try 
	{
		if (isset($params['start'])) 
		{
			$response = $solr->search($keys, $params['start'], $params['rows'], $params, Apache_Solr_Service::METHOD_POST);
		}
		else 
		{
			if (isset($params['rows'])) 
			{
				$response = $solr->search($keys, '0', $params['rows'], $params, Apache_Solr_Service::METHOD_POST);
			}
			else 
			{
				$response = $solr->search($keys, '0', '10', $params, Apache_Solr_Service::METHOD_POST);
			}
		}
	}
	catch (Exception $e) 
	{
		die($e->__toString());
	}
	print $response->getRawResponse();
}
?>