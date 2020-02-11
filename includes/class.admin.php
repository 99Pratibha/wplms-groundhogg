<?php
/**
 * Admin functions and actions.
 *
 * @author 		VibeThemes
 * @category 	Admin
 * @package 	wplms-groundhogg/Includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wplms_Groundhogg_Admin{

	public static $instance;
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new Wplms_Groundhogg_Admin();
        return self::$instance;
    }

	private function __construct(){
		//$this->init = Wplms_Groundhogg_Init::init();
		$this->settings = get_option(WPLMS_GROUNDHOGG_OPTION);
		add_action('admin_notices', array( $this, 'show_admin_notices' ), 10);
		add_filter('wplms_lms_settings_tabs',array($this,'setting_tab'));
		add_filter('lms_settings_tab',array($this,'tab'));

	}

	function show_admin_notices(){

	}

	function setting_tab($tabs){
		$tabs['wplms-groundhogg'] = __('Groundhogg','wplms-groundhogg');
		return $tabs;
	}

	function tab($name){
		if($name == 'wplms-groundhogg')
			return 'wplms_groundhogg_settings';
		return $name;
	}

	function get_settings(){

		if(!empty($this->settings)){
				$tag_creation_button = __('Click to create course specific tags','wplms-groundhogg');
		}else{
			$tag_creation_button = __('Click to create course specific tags','wplms-groundhogg');
		}
		
		return apply_filters('wplms_groundhogg_settings',array(
			array(
				'label' => __( 'GroundHogg Public Key', 'wplms-groundhogg' ),
				'name' => 'groundhogg_api_key',
				'type' => 'text',
				'desc' => sprintf(__( 'Enter the GroundHogg Public key here.' ),'<a href="http://kb.groundhogg.com/integrations/api-integrations/about-api-keys" target="_blank">','</a>'),
			),
			array(
				'label' => __( 'GroundHogg Token', 'wplms-groundhogg' ),
				'name' => 'groundhogg_token',
				'type' => 'text',
				'desc' => sprintf(__( 'Enter the GroundHogg Token here.%s', 'wplms-groundhogg' ),'<a href="http://kb.groundhogg.com/integrations/api-integrations/about-api-keys" target="_blank">','</a>'),
			),
			array(
				'label' => __( 'Create Course specific Tags', 'wplms-groundhogg' ),
				'name' => 'course_tag',
				'type' => 'checkbox',
				'desc' => sprintf(__( '%s %s %s To create a tag for every course, Tags creation details must be saved. reload the page after saving details. ', 'wplms-groundhogg' ),'<a id="sync_course_tags_now" class="button"><span></span>',$tag_creation_button,'</a>'),
			),
			array(
				'label' => __( 'Auto-Subscribe/unsubscribe user on Course subscribe/unsubscribe', 'wplms-groundhogg' ),
				'name' => 'auto_course_tag_subscribe',
				'type' => 'checkbox',
				'desc' => _x( 'Auto-Subscribe user to course tag on course subscription. Auto-remove user from Course when user is removed from the course.','WPLMS Groundhogg setting', 'wplms-groundhogg'),
			)
		));	
	}
	function settings(){

		echo '<form method="post">';
		wp_nonce_field('wplms_groundhogg_settings');   
		echo '<table class="form-table">
				<tbody>';

		$settings = $this->get_settings();
		$this->save();
		$this->generate_form($settings);
		if(isset($_GET['batch'])){
			$gr = new Wplms_Groundhogg($this->settings['groundhogg_api_key'],$this->settings['groundhogg_token']);
		}
		
		?>

		<?php
		echo '<tr valign="top"><th colspan="2"><input type="submit" name="save_wplms_groundhogg_settings" class="button button-primary" value="'.__('Save Settings','wplms-groundhogg').'" /></th>';
		echo '</tbody></table></form>'; ?><style>#sync_course_tags_now span,.sync_tags span{padding:0;} .sync_tags,#sync_course_tags_now{position:relative;}.sync_tags.active,#sync_course_tags_now.active{color: rgba(255,255,255,0.2);} #sync_course_tags_now.active span,.sync_tags.active span{position:absolute;left:0;top:0;width:0;transition: width 1s;height:100%;background:#009dd8;text-align: center;color: #fff;}.company,.company_address,.company_country,.company_zip,.company_state,.company_city,.permission_reminder,.from_name,.from_email,.subject,.language{display:none;}</style><script>

			function isJson(str) {

				if(str == null)
					return false;

			    try {
			        JSON.parse(str);
			    } catch (e) {
			        return false;
			    }
			    if(Object.keys(str).length === 0 && str.constructor === Object){
			    	return false;
			    }


			    return true;
			}
			jQuery(document).ready(function($){

				$('#sync_course_tags_now').on('click',function(event){
					if(!$(this).hasClass('filled')){
						event.preventDefault();
						$('.language').toggle(200);
						if(!$(this).hasClass('filled')){
							$(this).addClass('filled button-primary');
						}
					}else{
				
					var $this = $(this);
					if($this.hasClass('active')){return;}
					//var language =$('input[name="language"]').val();
					//var permission_reminder =$('input[name="permission_reminder"]').val();

				    $this.addClass('active');
				    let width = 10;
					$this.find('span').css('width',width+'%');
					$.ajax({
                      	type: 	"POST",
                      	url: 	ajaxurl,
                      	dataType: "json",
                      	data: { action: 'get_create_course_tags', //Fetches from 
                              	security: $('#_wpnonce').val(),
                            },
                      	cache: false,
                      	success:function(json){
                      		width = 40;
                      		$this.find('span').css('width',width+'%'); 
                      		if(Array.isArray(json)){
                      			json.map(function(tag){
                      				$.ajax({
				                      	type: 	"POST",
				                      	url: 	ajaxurl,
				                      	data: { action: 'course_tags_put', 
				                              	security: $('#_wpnonce').val(),
				                              	tag:JSON.stringify(tag),
				                            },
				                        cache: false,
				                      	complete: function (html) {
				                      		width += Math.round(60/json.length);
				                      		if(width> 100){width = 100;}
				                      		$this.find('span').css('width',width+'%');
				                      		if(width == 100){
				                      			$this.find('span').text('Sync Complete');
					                      		setTimeout(function(){
					                      			$this.removeClass('active');
					                      			$this.find('span').text('');
					                      			$this.find('span').css('width','0%');
					                      		},2000);
				                      		}
				                      	}
			                      	}); 
                      			});
                      		}
                		}
					});
				}
				});
			});
			</script>
			<?php
	}
	function generate_form($settings){
		
		foreach($settings as $setting ){
			echo '<tr valign="top" class="'.$setting['name'].'">';
			switch($setting['type']){
				case 'textarea':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><textarea name="'.$setting['name'].'" style="max-width: 560px; height: 240px;border:1px solid #DDD;">'.(isset($this->settings[$setting['name']])?$this->settings[$setting['name']]:'').'</textarea>';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
				case 'select':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><select name="'.$setting['name'].'">';
					foreach($setting['options'] as $key=>$option){
						echo '<option value="'.$key.'" '.(isset($this->settings[$setting['name']])?selected($key,$this->settings[$setting['name']]):'').'>'.$option.'</option>';
					}
					echo '</select>';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
				case 'checkbox':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><input type="checkbox" name="'.$setting['name'].'" '.(isset($this->settings[$setting['name']])?'CHECKED':'').' />';
					echo '<span>'.$setting['desc'].'</span>';
				break;
				case 'number':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><input type="number" name="'.$setting['name'].'" value="'.(isset($this->settings[$setting['name']])?$this->settings[$setting['name']]:$setting['std']).'" />';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
				case 'text':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><input type="text" name="'.$setting['name'].'" value="'.(isset($this->settings[$setting['name']])?$this->settings[$setting['name']]:$setting['std']).'" />';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
				case 'groundhogg_tags':

					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><select class="sync_tag_selection" name="'.$setting['name'].'">
					<option value="">'._x('Disable','disable switch in WPLMS Groundhogg settings','wplms-groundhogg').'</option>';
					$gr_tags = $this->init->get_tags();

					foreach($gr_tags as $key=>$option){
						echo '<option value="'.$key.'" '.(isset($this->settings[$setting['name']])?selected($key,$this->settings[$setting['name']]):'').'>'.$option.'</option>';
					}
					echo '</select><a id="'.$setting['name'].'" class="button sync_tags"><span></span>'.__('Sync all Users','wplms-cc').'</a>';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
			}
		}	
	}
	/*a#enable_registration:before { position: absolute; content: ''; width: 10%; background: #009dd8; left: 0; top: 0; height: 100%; z-index: 2; border-radius: 2px; } a#enable_registration:after { position: absolute; content: ''; width: 100%; left: 0; top: 0; height: 100%; background: rgba(255,255,255,0.8); border-radius: 2px; z-index: 1; }*/
	function save(){
		

		if(!isset($_POST['save_wplms_groundhogg_settings']))
			return;

		if ( !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'wplms_groundhogg_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-groundhogg').'</p></div>';
		}

		$settings = $this->get_settings();
		foreach($settings as $setting){
			if(isset($_POST[$setting['name']])){
				$this->settings[$setting['name']] = $_POST[$setting['name']];
			}else if($setting['type'] == 'checkbox' && isset($this->settings[$setting['name']])){
				unset($this->settings[$setting['name']]);
			}
		}

		update_option(WPLMS_GROUNDHOGG_OPTION,$this->settings);
		echo '<div class="updated notice is-dismissible"><p>'.__('Settings Saved.','wplms-groundhogg').'</p></div>';
	}

}

add_action('admin_init','wplms_groundhogg_admin_initialise');
function wplms_groundhogg_admin_initialise(){
	Wplms_Groundhogg_Admin::init();	
}

function wplms_groundhogg_settings(){
	$init = Wplms_Groundhogg_Admin::init();
	$init->save();
	$init->settings();
}