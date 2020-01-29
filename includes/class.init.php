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
		add_action('wp_ajax_sync_tags_get',array($this,'sync_tags_get'));
		add_action('wp_ajax_sync_tags_put',array($this,'sync_tags_put'));
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
	function sync_tags_get(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_groundhogg_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-groundhogg').'</p></div>';
		}
		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		$tags = $gr->get_tags();
		print_r(json_encode($tags));
		die();
	}
	function sync_tags_put(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_groundhogg_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-groundhogg').'</p></div>';
		     die();
		}

		$emails = json_decode(stripcslashes($_POST['emails']));

		$all_emails = array();
	
		if(!empty($emails)){
			
			foreach ($emails as $email) {
				$all_emails[$email->contactId]=$email->email;
			
			}
		}

		if($_POST['element'] == 'all_course_students' && !isset($_POST['paged'])){
			//Get all students;
			global $wpdb;
			$results = $wpdb->get_results("SELECT user_id FROM {$wpdb->usermeta} 
				WHERE meta_key LIKE 'course_status%' GROUP BY user_id");

			$total_count = count($results);
			if($total_count > $this->loop_max){

			//Run loop in batches of 100;
				for($i=0;$i<$total_count;$i=$i+$this->loop_max){
					$return_chained_ajax[]=array(
						'action'=> 'sync_tags_put', 
	                  	'security'=> $_POST['security'],
	                  	'emails'=> $_POST['emails'],
	                  	'element'=> $_POST['element'],
	                  	'tag'=> $_POST['tag'],
	                  	'paged'=> $i
					);
				}
				echo json_encode($return_chained_ajax);
				die();
			}
		}

		if(isset($_POST['paged'])){
			$paged = $_POST['paged'];
		}else{
			$paged = 0;
		}

		$this->sync_tags_put_check($all_emails,$_POST['element'],$_POST['tag'],$paged);
		die();
	}

	function sync_tags_put_check($all_emails,$element,$tag,$paged=0){
		
		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		
		global $wpdb;
		switch($element){
			case 'all_course_students':
				
				$time = time();
				
				$all_users = $wpdb->get_results("SELECT u.user_email as email,
					u.display_name as name,
					p.post_title as course_name
					FROM {$wpdb->users} as u 
					LEFT JOIN {$wpdb->usermeta} as m 
					ON u.ID = m.user_id 
					LEFT JOIN {$wpdb->usermeta} as m2 
					ON u.ID = m2.user_id 
					LEFT JOIN {$wpdb->posts} as p 
					ON m.meta_key = p.ID 
					WHERE u.user_status = 0 
					AND m2.meta_key LIKE '%course_status%'
					AND p.post_type = 'course'
					AND p.post_status = 'publish'
					GROUP BY email, course_name
					LIMIT $paged,$this->loop_max");
				$all_user_mails = array();
				$merge_fields = array();
				if(!empty($all_users)){
					foreach($all_users as $user){
						$all_user_mails[] = $user->email;
						$merge_fields[$user->email] = array(
								'name'=>$user->name,
								'campaign'=>array('campaignId'=>$tag),
								'email'=>$user->email
							);
					}
				}
				$tobe_rejected_mails =  array_diff($all_emails, $all_user_mails);
				$tobe_added_mails =  array_diff($all_user_mails,$all_emails);
				if(!empty($tobe_rejected_mails)){
					foreach($tobe_rejected_mails as $email){
						$contactID=array_search($email,$all_emails);
						$gr->remove_contact($contactID);
					}
				}

				if(!empty($tobe_added_mails)){
					foreach($tobe_added_mails as $email){
						$gr->add_contact($merge_fields[$email]);
					}
				}
			break;
		}
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
							update_post_meta($course->ID,'vibe_wplms_groundhogg_tag',$id);
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
		}
		
		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
	
		$data = json_decode(stripslashes($_POST['data']));	
		if(!empty($data)){
			$tag_ids = array();
			foreach($data as $d){
				$all_tags[$d->tag_id] = $d->tag_name;
			}
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
						$synced_tags = get_transient('groundhogg_tags_synced');
						if(empty($synced_tags) || !in_array($tag_ids,$synced_tags)){
							$synced_tags[]=$tag_id;
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
							
							$all_tag_slug['tags'] = $gr->get_tags();
							$apply_tag = $remove_tag = array();
							if(!empty($all_course_users)){
								foreach($all_course_users as $key=>$user){
									$all_course_users[$user->email] = $user;
									unset($all_course_users[$key]);
								}
							}
							if(!empty($all_tag_slug)){
								/*
								new_list_members[$user->email][] = array(
									'name'=>$user->name,
									'course'=>$user->course_slug,
									'campaign'=>array(
										'campaignId'=>$list_id),
									'email'=>$user->email
								);*/
								$course_emails = array();
								if(!empty($all_course_users)){
									$course_emails = array_keys($all_course_users);
								}

								foreach($all_tag_slug['tags'] as $k=>$member){
									if(!in_array($member->tags->tag_id,$course_emails)){
										$remove_tag_contact[]=array(
							 					'id_or_email'=>$users->email,
							 					'tags'=>$member->tag_slug
							 				);
									}
								}
							}
							if(!empty($all_course_users)){
								$list_emails = array();
								if(!empty($all_tag_slug)){
									foreach($all_tag_slug['tags'] as $member){
										$list_emails[]=$member->tags->email;
									}
								}
								
								foreach($all_course_users as $user){
									if(!in_array($user->email,$list_emails)){
										$apply_tag_contact[] = array(
							 				'id_or_email'=>$user->email,
							 				'tags'=>$user->course_slug

							 			);
									}
								}
							}
							
							if(!empty($remove_tag_contact)){
								foreach($remove_tag_contact as $contact){
									$gr->remove_tag($contact);
								}
							}
							
							if(!empty($apply_tag_contact)){
								print_r($apply_tag_contact);
								foreach($apply_tag_contact as $contact){
									$gr->apply_tag($contact);
								}
							}

							// if(!empty($all_tag_slug['tags'])){
							//  	$course_slug = array();
							//  	if(!empty($all_course_users)){
							//  		$course_slug = array_keys($all_course_users);
							//  	}

							// 	foreach($all_tag_slug['tags']->tags as $k=>$member){
							// 	 	foreach($all_course_users as $key=>$user){
							// 	 	$all_course_users[$user->email] = $user;
							// 		 	if(!in_array($member->tag_slug,$course_slug)){
							// 	 		$remove_tag_contact[]=array(
							// 					'id_or_email'=>$users->email,
							// 					'tags'=>$member->tag_slug
							// 				);
							// 			}
							// 		}
							// 	}
							// }
							// if(!empty($all_course_users)){
							// 	$tag_emails = array();
							// 	foreach($all_course_users as $user){
							// 		if(!in_array($user->email,$tag_emails)){
							// 			$apply_tag_contact[]= array(
							// 				'id_or_email'=>$user->email,
							// 				'tags'=>$user->course_slug

							// 			);
							// 		}
							// 	}
							// }
							
							// if(!empty($remove_tag_contact)){
							// 	foreach($remove_tag_contact as $contact){
							// 		$gr->remove_tag($contact);
							// 	}
							// }
							
							// if(!empty($apply_tag_contact)){
							// 	foreach($apply_tag_contact as $contact){
							// 		$gr->apply_tag($contact);
							// 	}
							// }
							set_transient('groundhogg_tags_synced',$synced_tags,DAY_IN_SECONDS);	
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
		print_r('it is working.');

		return;		
	}

	function remove_from_tag($course_id,$user_id){

		if(empty($this->settings['groundhogg_api_key']) && isset($this->settings['groundhogg_token']))
			print_r('anyyyyyyy');
			return;

		if(!isset($this->settings['auto_course_tag_subscribe']))
			print_r('mistake is here.');
			return;

		$tag_id = get_post_meta($course_id,'vibe_wplms_groundhogg_tag',true);
		if(empty($tag_id))
			print_r('is anymistake?');
			return;

		$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		$tag_ids = $gr->get_tag();

		$tags=[];
		if(!empty($tag_ids)){
			foreach($tag_ids as $id=>$value){
				$tags[$value['tag_id']]=$value['tag_slug'];
				
			}
		}
		$user = get_user_by('ID',$user_id);
		$contact_id = array_search($user->data->user_email,$tags);
		if(!empty($contact_id)){
			$return = $gr->remove_contact($contact_id);
		}
		// $user = get_user_by('ID',$user_id);
		// 	$return = $gr->remove_tag(array(
		// 	'id_or_email'=>$user->user_email,
		// 	'tags'=>$tag_id)
		// 	);
		// 	print_r('no its fine.');
		return;
	}
}


Wplms_Groundhogg_Init::init();