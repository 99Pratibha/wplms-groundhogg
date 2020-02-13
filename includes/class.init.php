<?php
/**
 * Admin functions and actions.
 *
 * @author 		VibeThemes
 * @category 	Admin
 * @package 	Wplms-Groundhogg/Includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wplms_Groundhogg_Init{

	/*
	Stores all tags
	 */
	public $tags = array();
	/*
	Stores emails from tag id tag ID => Member emails
	 */
	public $tag_members = array();

	public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new Wplms_Groundhogg_Init();
        return self::$instance;
    }

	private function __construct(){
		$this->loop_max = 99;
		
				// add_action( 'save_post', 'save_metadata', 100);
			
		add_filter('wplms_course_subscribed',array($this,'add_to_tag'),10,2);	
		add_filter('wplms_course_unsubscribe',array($this,'remove_from_tag'),10,2);

		
		add_action('init',array($this,'auto_sync'));

		
		/* AJAX FUNCTIONS */
		add_action('wp_ajax_get_create_course_tags',array($this,'get_create_course_tags'));
		add_action('wp_ajax_course_tags_put',array($this,'course_tags_put'));
	}


	function get_settings(){
		if(empty($this->settings)){
			$this->settings = get_option(WPLMS_GROUNDHOGG_OPTION);
		}
	}

	function show_admin_notices(){

	}

	

	function auto_sync(){
		$this->get_settings();
		if(!empty($this->settings['auto_sync_tags'])){

			if (! wp_next_scheduled ( 'wplms_groundhogg_sync_tags' )) {
				wp_schedule_event(time(), $this->settings['auto_sync_tags'], 'wplms_groundhogg_sync_tags');
			}

			add_action('wplms_groundhogg_sync_tags',array($this,'sync_tags'));
		}
	}

	function get_tags(){
		$tags = array();
		if(!empty($this->tags))
			return $this->tags;

		if(isset($this->settings) && isset($this->settings['groundhogg_api_key']) && isset($this->settings['groundhogg_token'])){
			$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
			$this->tags = $gr->get_tags(); 
		}
		return $this->tags;
	}
	
	function get_create_course_tags(){

		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_groundhogg_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator HERE I AM.','wplms-groundhogg').'</p></div>';
		}
		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		if(empty($this->tags)){
			$this->get_tags();
		}

		global $wpdb;
		//Existing Course tag ids
		$course_tags = $wpdb->get_results("SELECT meta_value,post_id FROM {$wpdb->postmeta} WHERE meta_key = 'vibe_wplms_groundhogg_tag'");
		$course_tag_ids = $exclude_courses = array();
		$ex_tag_ids = array_keys($this->tags->tags); 
		if(!empty($course_tags)){
			foreach($course_tags as $tag){
				if($tag->meta_value == 'disable'){
					$exclude_courses[] = $tag->post_id;
				}else{
					if(in_array($tag->meta_value,$ex_tag_ids)){ // Check if tag exists
						$course_tag_ids[$tag->meta_value] = $tag->post_id;	
					}
				}
			}
		}

		$extra_q = '';
		if(!empty($exclude_courses)){ 
			$extra_q = ' AND ID NOT IN ('.implode(',',$exclude_courses).')';
		}
		$courses = $wpdb->get_results("SELECT ID,post_title,post_name FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type ='course'$extra_q");
		$tag_ids = array();
		if(!empty($courses)){
			foreach($courses as $course){
				if(in_array($course->ID,$course_tag_ids)){
					$id = array_search($course->ID,$course_tag_ids);
					$tag_ids[]=array('tag_id'=>$id,'tag_name'=>$course->post_name);
				}else{
					if(!in_array($course->post_name,(array)$this->tags,true)){
						$tag_args = array(
							"tags"=>array($course->post_name)
						);
						$id = $gr->create_tag($tag_args);
						if($id){
							$this->tags = $course->post_name;
							$tag_ids[]=array('tag_id'=>$id,'tag_name'=>$course->post_name);
							update_post_meta($course->ID,'vibe_wplms_groundhogg_tag',$id);
						}
						else{
							$id = array_search($course->post_name,$this->tags);
							$tag_ids[]=array('tag_id'=>$id,'tag_name'=>$course->post_name);
							//update_post_meta($course->ID,'vibe_wplms_groundhogg_tag',$id);
						}
					}
				}
			}	
		}
		print_r(json_encode($tag_ids));
		die();
	}

	function course_tags_put(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_groundhogg_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-groundhogg').'</p></div>';
		     die();
		}
		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		$data = JSON_decode(stripslashes($_POST['tag']));
		if(!empty($data)){
			$tag_ids = array();
			$all_tags[$data->tag_id] = $data->tag_name;
			$this->course_specific_tags($all_tags);
		}
		die();	
	}

	function course_specific_tags($tag_ids){
		
		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		if(!empty($tag_ids)){
			foreach($tag_ids as $tag_id => $tag_name){
				if(!empty($tag_id)){
					global $wpdb;
					$course_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'vibe_wplms_groundhogg_tag' AND meta_value = %s",$tag_id));
					if(!empty($course_id)){
						//get all emails from Course
						$all_course_users = $wpdb->get_results("
						SELECT 
							u.user_email as email,
							p.post_name as course_slug,
							u.display_name as name
						FROM {$wpdb->users} as u 
						LEFT JOIN {$wpdb->usermeta} as m 
						ON u.ID = m.user_id 
						LEFT JOIN {$wpdb->posts} as p 
						ON m.meta_key = p.ID
						WHERE u.user_status = 0 
						AND m.meta_key = $course_id",true);
						//get all emails from tag
						foreach ($all_course_users as $key => $value) {
							$all_course_slug = $value->course_slug;
						}
						if($all_course_slug == $tag_name)
						{
							$tag_args = array(
								"query" => array(
									"tags_include"=>$tag_id
								)
							);
							$all_tag_students = $gr->get_tag_contact($tag_args);
							foreach ($all_course_users as $key => $user) {
								$all_course_user_emails[] = $user->email;
									$tag_contact[$user->email]=array(
				 					'id_or_email'=>$user->email,
				 					'tags'=>$user->course_slug
				 				);
							}
							    $tobe_added_mails =  array_diff($all_course_user_emails,$all_tag_students);
								$tobe_rejected_mails =  array_diff($all_tag_students, $all_course_user_emails);
								
							if(!empty($tobe_rejected_mails)){
								foreach($tobe_rejected_mails as $contact){
									$gr->remove_tag($tag_contact[$contact]);
								}
							}
							if(!empty($tobe_added_mails)){
								foreach($tobe_added_mails as $contact){
									$gr->apply_tag($tag_contact[$contact]);
								}
							}
						}
					}			
				}
			}		 
		}
	}

	function add_to_tag($course_id,$user_id){
		if(empty($this->settings['groundhogg_api_key']) && isset($this->settings['groundhogg_token']))
			return;

		if(!isset($this->settings['auto_course_tag_subscribe']))
			return;
		
		$tag_id = get_post_meta($course_id,'vibe_wplms_groundhogg_tag',true);
		if(empty($tag_id) && $tag_id == 'disable')
			return;

		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		$user = get_user_by('ID',$user_id);

		$return = $gr->apply_tag(array(
			'id_or_email'=>$user->user_email,
			'tags'=>$tag_id)
			);
		return;		
	}

	function remove_from_tag($course_id,$user_id){

		if(!empty($this->settings['groundhogg_api_key']) && isset($this->settings['groundhogg_token']))
			

		if(isset($this->settings['auto_course_tag_subscribe']))
			

		$tag_id = get_post_meta($course_id,'vibe_wplms_groundhogg_tag',true);
		if(!empty($tag_id) && $tag_id == 'disable');
		

		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		$user = get_user_by('ID',$user_id);
			$return = $gr->remove_tag(array(
		 	'id_or_email'=>$user->user_email,
		 	'tags'=>$tag_id)
		 	);
		return;
	}
}


Wplms_Groundhogg_Init::init();