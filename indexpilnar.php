<?php
#@-------------------------------------------------------------------------------------
#@ By Junte Zhang <juntezhang@gmail.com> 2013 
#@ Distributed under the GNU General Public Licence
#@
#@ Service for CMDI indexing for PILNAR 
#@-------------------------------------------------------------------------------------
ini_set('error_reporting', E_ALL);

ini_set('log_errors',TRUE);
ini_set('html_errors',FALSE);
ini_set('error_log','/tmp/error_log.txt');
ini_set('display_errors',FALSE);

error_reporting(E_ALL | E_STRICT);

mb_http_output("UTF-8");

//require_once('/var/www/html/apache/pilnar_search/IndexCMDI.php');

class IndexCMDI {
	private $elements = array();
	private $schema_lines = array();
	private $tags_merged = array();
	private $index_data = array();

	/* 
		set up the directory where the mapping should be stored
	*/
	private $dir_cache = "/data/PILNAR_SEARCH/mapping/";
	//private $dir_cache = "/Development/pilnar/scripts/mapping/";
	//private $dir_cache = "/tmp/";
	
	function __construct($url){
			$this->url = $url;
			$this->httppost = TRUE;
	}

	/* extract schema name */
	function extract_schema($file) {
		$lines = file_get_contents($file);
		
		if ($lines === false) {
			//header("HTTP/1.0 500 Internal Server Error");
			throw new Exception('Failed to open ' . $file);
		} else {
			$lines = explode("\n", $lines);
		}		

		$first_line = $lines[1];

		if(preg_match('/.+\s+xsi:schemaLocation=\".*\s*(http.+\/xsd)\".+/', $first_line)) {
			$schema_ref = preg_replace('/.+\s+xsi:schemaLocation=\".*\s*(http.+\/xsd)\".+/', "$1" , $first_line);
			return $schema_ref;
		} else {
			header("HTTP/1.1 500 Internal Server Error");
			echo "No schema name / CMDI profile present!\n";
			exit;
		}
	}
	
	function read_cache_schema($schema_ref) {
		$out_file = preg_replace('/.*\/(.+)\/xsd/', "$1" , $schema_ref);
		$out_file = preg_replace('/.*\/(.+\/.+)$/', "$1" , $out_file);
		
		// need to ESCAPE certain tokens to make it smooth
		$out_file = preg_replace('/\//', "_" , $out_file);
		$out_file = preg_replace('/\:/', "_" , $out_file);
		$out_file = preg_replace('/\?/', "_" , $out_file);
		$out_file = preg_replace('/\=/', "_" , $out_file);
		$out_file = preg_replace('/\./', "_" , $out_file);
		$out_file = preg_replace('/\r/', "" , $out_file);
		
		$csv_file =  $this->dir_cache . $out_file . ".csv";
		
		$this->construct_schema($csv_file);
	}
	
	/* get the elements from the schema parser */
	function map_schema($schema_ref) {
		$ch = curl_init();
		
		// clear cache first 
		$header0 = array("Accept: text/csv", "Content-Type: text/csv", "charset=utf-8");
		curl_setopt($ch, CURLOPT_URL, 'http://yago.meertens.knaw.nl/SchemaParser/clear');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
		
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, "0");
		curl_setopt($ch, CURLOPT_TIMEOUT, "0");
		
		curl_exec($ch);
		
		// schema parser in action
		$url = "http://yago.meertens.knaw.nl/SchemaParser/indexTypes?schemaReference=" . $schema_ref;
		
		$header = array("Accept: text/csv", "Content-Type: text/csv", "charset=utf-8");
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
		
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, "0");
		curl_setopt($ch, CURLOPT_TIMEOUT, "0");
		
		$data = curl_exec($ch);
		
		// write to a cache file
		$out_file = preg_replace('/.*\/(.+)\/xsd/', "$1" , $schema_ref);
		$out_file = preg_replace('/.*\/(.+\/.+)$/', "$1" , $out_file);
		
		// need to ESCAPE certain tokens to make it smooth
		$out_file = preg_replace('/\//', "_" , $out_file);
		$out_file = preg_replace('/\:/', "_" , $out_file);
		$out_file = preg_replace('/\?/', "_" , $out_file);
		$out_file = preg_replace('/\=/', "_" , $out_file);
		$out_file = preg_replace('/\./', "_" , $out_file);
		$out_file = preg_replace('/\r/', "" , $out_file);
		
		$out_file = $this->dir_cache . $out_file . ".csv";

		file_put_contents($out_file, $data, LOCK_EX);
		$this->construct_schema($out_file);
		
		if (curl_errno($ch)) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new Exception ( "curl_error:" . curl_error($ch) );
		} else {
			curl_close($ch);
			return TRUE;
		}
		
	}
	
	/* construct the indexing schema */
	private function construct_schema($file) {
		$lines = file_get_contents($file);
		$lines = explode("\n", $lines);
		array_shift($lines);
		array_pop($lines);
		if ($lines === false) {
			header("HTTP/1.0 500 Internal Server Error");
			throw new Exception('Failed to open ' . $file);
		}
		
		foreach ($lines as $line) {
			// $isocatno = XML element
			$xpath = preg_replace('/(.*);(.*);(.*);\[(.*)\]/' , "$1" , $line);
			$isocatno = preg_replace('/(.*);(.*);(.*);\[(.*)\]/' , "$2" , $line);
			$type = preg_replace('/(.*);(.*);(.*);\[(.*)\]/' , "$3" , $line);
			$isocatlabel = preg_replace('/(.*);(.*);(.*);\[(.*)\]/' , "$4" , $line);
			//print "$xpath / $isocatno / $type / $isocatlabel\n";
			
			if(preg_match('/\@/' , $xpath)) {
				;
			}
			else if(preg_match('/Not response received/' , $isocatlabel)) {
				;
			}
			else if($isocatlabel == "") {
				$label = $xpath;
				if(!preg_match('/^\/\/Md/', $label)) {
					$label = strtolower($label);
				}
				if($isocatno = "") {
					if(preg_match('/.*\/(.+)\/(.+)$/' , $label)) {
						$label = preg_replace('/.*\/(.+)\/(.+)$/' , "$1.$2" , $label);
					}
					else {
						$label = preg_replace('/.+\/(.+)$/' , "$1" , $label);
					}
					$isocatno = $label;
				}
				else {
					if(preg_match('/.*\/(.+)\/(.+)$/' , $label)) {
						$label = preg_replace('/.*\/(.+)\/(.+)$/' , "$1.$2" , $label);
					}
					else {
						$label = preg_replace('/.+\/(.+)$/' , "$1" , $label);
					}
					$isocatno = $label;
				}
				$isocatno = preg_replace('/http:\/\/www.isocat.org\/datcat\//' , "" , $isocatno);
				$isocatno = preg_replace('/http:\/\/www.isocat.org\/rest\/dc\/(\d+)/' , "DC-$1" , $isocatno);
				$isocatno = preg_replace('/http:\/\/purl.org\/dc\/terms\//' , "" , $isocatno);
				$isocatno = preg_replace('/http:\/\/purl.org\/dc\/elements\/1\.1\//' , "" , $isocatno);
				$isocatno = preg_replace('/DC\-471\./' , "" , $isocatno);
				
				$this->elements[$isocatno][] = $xpath; 
			}
			else {
				$isocatno = preg_replace('/http:\/\/www.isocat.org\/datcat\//' , "" , $isocatno);
				$isocatno = preg_replace('/http:\/\/www.isocat.org\/rest\/dc\/(\d+)/' , "DC-$1" , $isocatno);
				$isocatno = preg_replace('/http:\/\/purl.org\/dc\/terms\//' , "" , $isocatno);
				$isocatno = preg_replace('/http:\/\/purl.org\/dc\/elements\/1\.1\//' , "" , $isocatno);
				$isocatno = preg_replace('/DC\-471\./' , "" , $isocatno);
				
				$this->elements[$isocatno][] = $xpath;
			}
			
		}
	}
	
	/* check if schema has the elements of the profile or else append it to the existing indexing schema */
	function check_schema($schema, $schema_file_in, $schema_file_out) {
		// make a copy of the schema.xml first as a backup
		copy($schema_file_in, "/tmp/" . $schema_file_out . ".backup");
		
		// load all fields of schema.xml
		$this->schema_lines = explode("\r", $schema);
		$tags = array();
		foreach ($this->schema_lines as $field) {
			if(preg_match('/<field name=/', $field)) {
				$tag = preg_replace('/<field name=\"(.+)\"\s+type=.*/', "$1", $field);
				array_push($tags, $tag);
			}
		}
		// if a tag does not exist in schema.xml, merge
		$tags2 = array();
		foreach ($this->elements as $key => $element) {
			array_push($tags2, $key);
			//foreach ($element as $val) {
			//	print "$key: $val\n";
			//}
		}
		$this->tags_merged = array_merge(array_values($tags), array_values($tags2));
		$this->tags_merged = array_unique($this->tags_merged);
		
		// ready to generate the schema.xml
		$this->create_schema($schema_file_in);
	}
	
	private function create_schema($schema_file) {
		$contents = '';
		foreach ($this->schema_lines as $schema_line) {
			if(preg_match('/<fields>/' , $schema_line)) {
				$contents .= "<fields>" . "\r";
				
				foreach ($this->tags_merged as $tag) {
					//print "$tag\n";
					if($tag == "MdSelfLink") {
						$contents .= '<field name="MdSelfLink" type="string" indexed="true" stored="true" required="true"/>' . "\r";
					}
					elseif ($tag == "collection") {
						$contents .= '<field name="collection" type="string" indexed="true" stored="true" multiValued="true"/>' . "\r";
					}
					elseif ($tag == "schemaLocation") {
						$contents .= '<field name="schemaLocation" type="string" indexed="true" stored="true" multiValued="true"/>' . "\r";
					}
					elseif ($tag == "schemaName") {
						$contents .= '<field name="schemaName" type="string" indexed="true" stored="true" multiValued="true"/>' . "\r";
					}
					elseif ($tag == "owner") {
						$contents .= '<field name="owner" type="string" indexed="true" stored="true" multiValued="true"/>' . "\r";
					}
					elseif ($tag == "status") {
						$contents .= '<field name="status" type="string" indexed="true" stored="true" multiValued="true"/>' . "\r";
					}
					else {
						$contents .= '<field name="' . $tag . '" type="textgen" indexed="true" stored="true" multiValued="true"/>' . "\r";
					}
				}
				
			}
			else if(preg_match('/<field\s+/', $schema_line)) {
				;
			}
			else if(preg_match('/<copyField\s+/', $schema_line)) {
				;
			}
			else if(preg_match('/^\s+$/', $schema_line)) {
				;
			}
			else if(preg_match('/<\/schema>/', $schema_line)) {
				// also add all content to fulltext
				foreach ($this->tags_merged as $tag) {
					if($tag != "fulltext") {
						$contents .= '<copyField source="' . $tag . '" dest="fulltext"/>' . "\r";
					}
				}
				$contents .= "</schema>";
			}
			
			else {
				$contents .= $schema_line . "\r";
			}
		}
		file_put_contents($schema_file, $contents, LOCK_EX);		
	}
	
	function get_id($doc) {
		$xml = file_get_contents($doc);

		// get rid of the namespace or else it will give problems
		$xml = str_replace('xmlns=', 'ns=', $xml);
		//$xml = preg_replace('/\<CMD.+\>/', '<CMD>', $xml);
		$xml2 = simplexml_load_string($xml);

		if ($xml === FALSE) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new Exception('Failed to load XML string.');
		}
		
		// run the MdSelfLink
		$id = $xml2->xpath("//MdSelfLink");
		if ($id == null) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new Exception('Could not find the MdSelfLink.');
		} else {
			return $id[0];
		}
	}
	
	// extract values from doc
	private function extract_doc($doc) {
		$xml = file_get_contents($doc);
		// get rid of the namespace or else it will give problems
		//$xml = str_replace('xmlns', 'ns', $xml);	
		$xml = preg_replace('/\<CMD.+\>/', '<CMD>', $xml);
		$xml2 = simplexml_load_string($xml);	
		//$xml2['xmlns'] = '';
		
		if ($xml === FALSE) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new Exception('Failed to load XML string.');
		}
		
		// run the xpaths
		foreach ($this->elements as $key => $element) {
			foreach ($element as $xpath) {
				$values = $xml2->xpath($xpath);
				foreach ($values as $val) {
					if($val != "") {
						//echo "$key:$xpath\n";
						$this->index_data[$key][] = $val;
					}
				}
			}
		}
	}
	
	// extract full text from the attachment using the extract handler base on Solr Cell
	function extract_attach_txt($doc) {		
		$xml = file_get_contents($doc);
		// get rid of the namespace or else it will give problems
		//$xml = str_replace('xmlns', 'ns', $xml);
		$xml = preg_replace('/\<CMD.+\>/', '<CMD>', $xml);
		$xml2 = simplexml_load_string($xml);
		
		$txt_tika = '';
		if ($xml === FALSE) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new Exception('Failed to load XML string.');
		}		
		$values = $xml2->xpath("//ResourceRef");
		foreach ($values as $val) {
			// MS Doc , PDF , TXT files
			if(($val != "") && ((preg_match("/\.doc/", $val)) || (preg_match("/\.pdf/", $val)) || (preg_match("/\.txt/", $val)))) {
				$ch = curl_init();
				
				// extractor in action
				$post_url = $this->url.'update/extract?stream.file='.$val.'&extractOnly=true&extractFormat=text&resource.name='.$val;

				$header = array("Content-type:text/plain" , "charset=utf-8");
				curl_setopt($ch, CURLOPT_URL, $post_url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
				curl_setopt($ch, CURLINFO_HEADER_OUT, 0);
				
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, "0");
				curl_setopt($ch, CURLOPT_TIMEOUT, "0");
				
				$ft_output = curl_exec($ch);

				$ft_output = preg_replace("/\s+/", " ", $ft_output);
				$xml_tika = simplexml_load_string($ft_output);
				if ($xml_tika === FALSE) {
					header("HTTP/1.1 500 Internal Server Error");
					throw new Exception('Failed to load XML string.');
				}
				// extract only the content of the resource and not its metadata
				$txt_tika_tmp = $xml_tika->xpath("/response/str[1]");
				$txt_tika .= implode(",", $txt_tika_tmp);
			}
		}

		return $txt_tika;
	}
	
	private function index_doc($doc , $owner , $status , $attach_txt) {
		$fields = '';

		foreach ($this->index_data as $field_name => $values){
			foreach ($values as $val) {
				$fields .= sprintf('<field name="%s">%s</field>', $field_name, $val);
			}
		}

		/* add owner and status and attachment */
		$fields .= sprintf('<field name="owner">%s</field>', $owner);
		$fields .= sprintf('<field name="status">%s</field>', $status);
		
		$fields .= sprintf('<field name="attachment_txt">%s</field>', htmlspecialchars($attach_txt));
		
		return sprintf('<add><doc>%s</doc></add>', $fields);
	}

	/* use curl to index */
	private function post($xml){	
		$ch = curl_init();
		$post_url = $this->url.'update';
		
		$header = array("Content-type:text/xml" , "charset=utf-8");
		curl_setopt($ch, CURLOPT_URL, $post_url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, "0");
		curl_setopt($ch, CURLOPT_TIMEOUT, "0");

		$data = curl_exec($ch);
		#print_r($data);
		
		if (curl_errno($ch)) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new Exception ( "curl_error:" . curl_error($ch) );
		} else {
			//print "CMDI has been indexed!";
			curl_close($ch);
			return TRUE;
		}
	}

	function commit(){
		$this->post('<commit/>');
	}

	function optimize(){
		$this->post('<optimize/>');
	}

	function add_doc($document , $owner , $status , $attach_txt){
		$this->extract_doc($document);
		$xml = $this->index_doc($document , $owner , $status , $attach_txt);
		$this->post($xml);
	}

	function delete_by_id($id){
		$this->post( sprintf('<delete><id>%s</id></delete>', $id) );
	}
	
	function delete_all() {
		$url = $this->url.'update';
		$url = $url . "?stream.body=%3Cdelete%3E%3Cquery%3E*:*%3C/query%3E%3C/delete%3E&commit=true";
		
		$header = array("Content-type:text/xml; charset=utf-8");
		
		$ch = curl_init();
		print_r($post_string);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
		
		$dat = curl_exec($ch);
		//print $dat;
		
		if (curl_errno($ch)) {
			header("HTTP/1.1 500 Internal Server Error");
			print "curl_error:" . curl_error($ch);
		}
		else {
		  echo "\nEverything deleted!\n";
			curl_close($ch);
		}	
	}
	function reload_index($core) {
		//$url = "http://localhost:8983/solr/admin/cores?action=RELOAD&core=" . $core;
		$url = "http://yago.meertens.knaw.nl/solr/admin/cores?action=RELOAD&core=" . $core;
		
		$header = array("Content-type:text/xml; charset=utf-8");
		
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
		
		$dat = curl_exec($ch);
		
		if (curl_errno($ch)) {
			header("HTTP/1.1 500 Internal Server Error");
			print "curl_error:" . curl_error($ch);
		}
		else {
			curl_close($ch);
		}
	}
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: content-type');

// disable magic quotes -- who needs magic? (JZ)
if ( in_array( strtolower( ini_get( 'magic_quotes_gpc' ) ), array( '1', 'on' ) ) )
{
	$_POST = array_map( 'stripslashes', $_POST );
	$_POST = array_map( 'stripslashes', $_POST );
	$_COOKIE = array_map( 'stripslashes', $_COOKIE );
}

//$schema_dir = "/Development/apache-solr-4.4.0/example/solr/collection1/conf/";
//$schema_file = "schema.xml";

$schema_dir = "/data/solr/data/coreInstances/pilnar/conf/";
$schema_file = "schema.xml";

$schema = file_get_contents($schema_dir . $schema_file);

//$url = 'http://localhost:8983/solr/collection1/';
$url = 'http://yago.meertens.knaw.nl/solr/pilnar/';

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
		//echo "Please provide a reference of a CMDI file or content...\n";
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
	if($attach != "true") 
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
				$index->reload_index($core);
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
				if (file_exists("/tmp/" . $out_file . ".csv")) 
				{	
					$index->read_cache_schema($xsd);
					$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);					
				} 
				else 
				{
					$index->map_schema($xsd);
					$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);
				
					/* reload core only if the schema has changed */
					$index->reload_index($core);
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
				$index->reload_index($core);
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
				if (file_exists("/tmp/" . $out_file . ".csv")) 
				{
					$index->read_cache_schema($xsd);
					$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);					
				} 
				else 
				{
					$index->map_schema($xsd);
					$index->check_schema($schema, $schema_dir . $schema_file, $schema_file);
				
					/* reload core only if the schema has changed */
					$index->reload_index($core);
				}
			}
		
			/* indexing */
			
			$attach_txt = "";
			if($attach == "true") 
			{
        $attach_txt = $index->extract_attach_txt($file);
			}
			
			$index->add_doc($file , $owner , $status , $attach_txt);
			$index->commit();
			$index->optimize();
		}
		break;

	/* if delete == "all" */
	case "all":

		$index->delete_all();
		$index->commit();
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