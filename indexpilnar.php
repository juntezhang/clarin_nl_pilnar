<?php
#@-------------------------------------------------------------------------------------
#@ By Junte Zhang <juntezhang@gmail.com> 2013 
#@ Distributed under the GNU General Public Licence
#@
#@ Service for CMDI indexing for PILNAR 
#@-------------------------------------------------------------------------------------

require_once(dirname(__FILE__) .'/IndexCMDI.php');

mb_http_output("UTF-8");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: content-type');

// disable magic quotes -- who needs magic? (JZ)
if ( in_array( strtolower( ini_get( 'magic_quotes_gpc' ) ), array( '1', 'on' ) ) )
{
	$_POST = array_map( 'stripslashes', $_POST );
	$_GET = array_map( 'stripslashes', $_GET );
	$_COOKIE = array_map( 'stripslashes', $_COOKIE );
}

$schema_dir = "/Development/apache-solr-3.6.2/example/pilnar/conf/";
$schema_file = "schema.xml";

//$schema_file = "/data/solr/data/coreInstances/pilnar/conf/schema.xml";
$schema = file_get_contents($schema_dir . $schema_file);

$url = 'http://localhost:8983/solr/pilnar/';
//$url = 'http://yago.meertens.knaw.nl/solr/pilnar/';

/* input CMDI file */
$file = '';

/* input CMDI string */
$input_string = '';
if (isset($_POST['cmdi'])) 
{
	$file = $_POST['cmdi'];
} 
else 
{
	if (isset($_POST['cmdi_string'])) 
	{
		$input_string = $_POST['cmdi_string'];
	} 
	else 
	{
		header("HTTP/1.1 500 Internal Server Error");
		echo "Please provide a reference of a CMDI file or content...\n";
		exit;
	} 
}

/* 
- owner  
- status
- attachment 
*/
$owner = "";
if (isset($_POST['owner'])) 
{
	$owner = $_POST['owner'];
} 
else 
{
	$owner = "";
}

$status = "";
if (isset($_POST['status'])) 
{
	$status = $_POST['status'];
} 
else 
{
	$status = "draft";
}

$attach = "false";
if (isset($_POST['attach'])) 
{
	$attach = $_POST['attach'];
	if($delete != "true") 
	{
		$attach = "false";
	}
} 
else 
{
	$attach = "false";
}

/*
	setting up cache
*/
$cache = "false";
if (isset($_POST['cache'])) 
{
	$cache = $_POST['cache'];
} 
else 
{
	$cache = "false";
}

/* 
	when a CMDI has to be deleted from the index 
*/
$delete = "false";
if (isset($_POST['del'])) 
{
	$delete = $_POST['del'];
	if($delete != "true") 
	{
		if($delete != "all") 
		{
			$delete = "false";
		}
	}
} 
else
{
	$delete = "false";
}


/*
	Solr core
*/
$core = "pilnar";

/*
create new object of class IndexCMDI
*/
$index = new IndexCMDI($url);

/**
* 1. Load CMDI file
* 2. schemaParser
* 3. parse the CMDI
* 4. do the transformation
* 5. index the transformation
*/
switch ($delete) 
{
	/* if delete == "false" */
	case "false":
	
		if(isset($_POST['cmdi_string'])) 
		{
			/*save the string as file first! */
			file_put_contents("/tmp/cmdi_tmp.xml", $input_string, LOCK_EX);
		
			/* schema first */
			$xsd = $index->extract_schema("/tmp/cmdi_tmp.xml");

			// if cache is set to false, then do the schema parse again
			if($cache == "false") 
			{
				$index->map_schema($xsd);
				$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);
				
				/* reload core only if the schema has changed */
				$index->reload_index("/tmp/cmdi_tmp.xml", $core);
				//echo "$core";
			} 
			else 
			{
				$out_file = preg_replace('/.*\/(.+)\/xsd/', "$1" , $xsd);
				$out_file = preg_replace('/.*\/(.+\/.+)$/', "$1" , $out_file);
				
				// need to ESCAPE certain tokens to make it smooth
				$out_file = preg_replace('/\//', "_" , $out_file);
				$out_file = preg_replace('/\:/', "_" , $out_file);
				$out_file = preg_replace('/\?/', "_" , $out_file);
				$out_file = preg_replace('/\=/', "_" , $out_file);
				$out_file = preg_replace('/\./', "_" , $out_file);
				$out_file = preg_replace('/\r/', "" , $out_file);
			
				// check if this schema exists, or else generate it!!
				//if (file_exists("/data/PILNAR_SEARCH/mapping/" . $out_file . ".csv")) {
				if (file_exists("/Development/pilnar/scripts/mapping/" . $out_file . ".csv")) 
				{	
					$index->read_cache_schema($xsd);
					$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);					
				} 
				else 
				{
					$index->map_schema($xsd);
					$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);
				
					/* reload core only if the schema has changed */
					$index->reload_index("/tmp/cmdi_tmp.xml", $core);
				}
			}
		
			/* indexing */
			$attach_txt = "";
			if($attach == "true") 
			{
				$attach_txt = $index->extract_attach_txt("/tmp/cmdi_tmp.xml");
			}
			$index->add_doc("/tmp/cmdi_tmp.xml" , $owner , $status , $attach_txt);
			$index->commit();
			$index->optimize();		
		}
		else 
		{
			/* schema first */
			$xsd = $index->extract_schema($file);
		
			// if cache is set to false, then do the schema parse again
			if($cache == "false") 
			{
				$index->map_schema($xsd);
				$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);
			
				/* reload core only if the schema has changed */
				$index->reload_index($file, $core);
			} 
			else 
			{
				$out_file = preg_replace('/.*\/(.+)\/xsd/', "$1" , $xsd);
				$out_file = preg_replace('/.*\/(.+\/.+)$/', "$1" , $out_file);
				
				// need to ESCAPE certain tokens to make it smooth
				$out_file = preg_replace('/\//', "_" , $out_file);
				$out_file = preg_replace('/\:/', "_" , $out_file);
				$out_file = preg_replace('/\?/', "_" , $out_file);
				$out_file = preg_replace('/\=/', "_" , $out_file);
				$out_file = preg_replace('/\./', "_" , $out_file);
				$out_file = preg_replace('/\r/', "" , $out_file);
						
				// check if this schema exists, or else generate it!!
				//if (file_exists("/data/PILNAR_SEARCH/mapping/" . $out_file . ".csv")) {
				if (file_exists("/Development/pilnar/scripts/mapping/" . $out_file . ".csv")) 
				{
					$index->read_cache_schema($xsd);
					$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);					
				} 
				else 
				{
					$index->map_schema($xsd);
					$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);
				
					/* reload core only if the schema has changed */
					$index->reload_index($file, $core);
				}
			}
		
			/* indexing */
			$attach_txt = $index->extract_attach_txt($file);
			$index->add_doc($file , $owner , $status , $attach_txt);
			$index->commit();
			$index->optimize();
		}
		break;

	/* if delete == "all" */
	case "all":

		$index->delete_all("/tmp/cmdi_tmp.xml");
		$index->commit();
		echo "\nEverything deleted!\n";
		exit;	

		break; 

	/* else */
	default:

		if(isset($_POST['cmdi_string'])) 
		{
			file_put_contents("/tmp/cmdi_tmp.xml", $input_string, LOCK_EX);
		
			$id = $index->get_id("/tmp/cmdi_tmp.xml");
			$index->delete_by_id($id);
			$index->commit();
			echo "\nCMDI with MdSelfLink $id deleted!\n";
			exit;		
		} 
		else 
		{
			$id = $index->get_id($file);
			$index->delete_by_id($id);
			$index->commit();
			echo "\nCMDI with MdSelfLink $id deleted!\n";
			exit;
		}
		break;
}


/* Functions that do not belong to a Class */
function utf8_urldecode($str) {
	$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", urldecode($str));
	return html_entity_decode($str,null,'UTF-8');
}

/* 
 * /var/www/html/apache/pilnar_search
 *
 */
?>