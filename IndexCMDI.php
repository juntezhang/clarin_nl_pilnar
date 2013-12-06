<?php
#@-------------------------------------------------------------------------------------
#@ By Junte Zhang <juntezhang@gmail.com> 2013 
#@ Distributed under the GNU General Public Licence
#@
#@ A PHP class for constructing a CMDI index, based on the CMDI MI approach (c) :-)
#@-------------------------------------------------------------------------------------
ini_set('error_reporting', E_ALL);

ini_set('log_errors',TRUE);
ini_set('html_errors',FALSE);
ini_set('error_log','/tmp/error_log.txt');
ini_set('display_errors',FALSE);
error_reporting(E_ALL | E_STRICT);

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
?>