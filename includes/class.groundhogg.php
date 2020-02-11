<?php
/**
 * Groundhogg Class
 *
 * @author 		VibeThemes
 * @category 	Admin
 * @package 	Wplms-Groundhogg/Includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* = DEVS If you're copying then please give Credits @Vibethemes @Ripul = */

class Wplms_Groundhogg{

	
	/*
	Groundhogg key
	 */
	private $apikey = '';
	private $apitoken = '';

	/**
	* Constructor - if you're not using the class statically
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @param boolean $useSSL Enable SSL
	* @param string $endpoint Amazon URI
	* @return void
	*/
	public function __construct($api_key = null,$api_token = null)
	{
		$this->apikey = $api_key;
		$this->apitoken = $api_token;
		$this->apiurl = get_site_url().'/wp-json/gh/v3/';
		$this->interest_ids = array();
		$this->args = array(
		 	'headers' => array(
				'Gh-public-key' => $api_key,
				'Gh-token' => $api_token,
				'Content-Type' => 'application/json'
			)
		);
	}


	function get_tags(){
		$response = wp_remote_get( $this->apiurl.'tags', $this->args );
		$body = json_decode( wp_remote_retrieve_body( $response ));
		return $body;
	}
	function get_tag_contact($tag_args){
		/*{
		"query":{
			"tags_include" : 92
		}
		}*/
		$emails = array();
		$args = $this->args;
		$args['method'] = 'GET';
		$args['body'] = $tag_args;
		$response = wp_remote_get($this->apiurl.'contacts',$args);
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if(!empty($body)){
			foreach ($body->contacts as $contact) 
			{
				$emails[] = $contact->data->email;
       		}
			return $emails;
		}
	}

	function create_tag($tag_args){
		/*{
		    "tags": [
		        "Tag 1",
		        "Tag 2",
		        "Tag 3"
		    ]
		}*/
		$args = $this->args;
		$args['method'] = 'POST';
		$args['body'] = json_encode($tag_args);
		$response = wp_remote_post(  $this->apiurl.'tags', $args );
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if(!empty($body)){
			foreach ($body as $key['tags']) {
				foreach ($key['tags'] as $ids => $values) {
					return $ids;
				}
				
			}
			
		}
	}

	function apply_tag($contact_args){

		$args = $this->args;
		$args['method'] = 'PUT';
		/*
		{
		"id_or_email":"1", mandatory field.
		    "tags" : [ 
		        1, 
		        2, 
		        3, 
		        "user", 
		        "confirm" 
		    ]
		}*/
		$args['body'] = json_encode($contact_args);
		$response = wp_remote_post(  $this->apiurl.'tags/apply',$args );
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if(empty($body)){
			return true;
		}
	}

	function remove_tag($contact_args){

		$args = $this->args;
		$args['method'] = 'PATCH';
		/*
		{
		    "id_or_email":"1", mandatory field.
		    "tags" : [ 
		        1, 
		        2, 
		        3, 
		        "user", 
		        "confirm" 
		    ]
		}*/
		$args['body'] = json_encode($contact_args);
		$response = wp_remote_post(  $this->apiurl.'tags/remove',$args );
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if(empty($body)){
			return true;
		}
	}
	
	function get_all_tag($tag_name){

		$response = wp_remote_get( $this->apiurl.'tags?q='.$tag_name, $this->args );
		$body = json_decode( wp_remote_retrieve_body( $response ),true );
		$tags = array();

		foreach ($body['tags'] as $tag) {
			 $tags[] = array('tag_id'=>$tag['tag_id'], 'tag_name'=>$tag['tag_name']);
			return $tags;
		}
	}

	function debug($streamopt){
		$myFile = "groundhogg_debug.txt";
        if (file_exists($myFile)) {
          $fh = fopen($myFile, 'a');
          fwrite($fh, print_r($streamopt, true)."\n");
        } else {
          $fh = fopen($myFile, 'w');
          fwrite($fh, print_r($streamopt, true)."\n");
        }
        fwrite($fh, print_r(json_encode($data, JSON_PRETTY_PRINT), true)."\n");
        fclose($fh);  
	}
}
