<?php
/**
* Plugin Name: Connects - Klaviyo Addon
* Plugin URI:
* Description: Use this plugin to integrate Klaviyo with Connects.
* Version: 0.0.1
* Author: Bryce Hamrick
* Author URI: https://www.bhamrick.com/
*/


if(!class_exists('Smile_Mailer_Klaviyo')){

	class Smile_Mailer_Klaviyo{

		//Class variables
		private $slug;
		private $setting;

		/*
		 * Function Name: __construct
		 * Function Description: Constructor
		 */

		function __construct(){
			add_action( 'wp_ajax_get_klaviyo_data', array($this,'get_klaviyo_data' ));
			add_action( 'wp_ajax_update_klaviyo_authentication', array($this,'update_klaviyo_authentication' ));
			add_action( 'admin_init', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_ajax_disconnect_klaviyo', array($this,'disconnect_klaviyo' ));
			add_action( 'wp_ajax_klaviyo_add_subscriber', array($this,'klaviyo_add_subscriber' ));
			add_action( 'wp_ajax_nopriv_klaviyo_add_subscriber', array($this,'klaviyo_add_subscriber' ));
			$this->setting  = array(
				'name' => 'Klaviyo',
				'parameters' => array('api_key'),
				'where_to_find_url' => 'https://www.klaviyo.com/account#api-keys-tab',
				'logo_url' => plugins_url('images/logo.png', __FILE__)
			);
			$this->slug = 'klaviyo';
			$this->api_url = 'https://a.klaviyo.com/api/v1/';
		}


		/*
		 * Function Name: enqueue_scripts
		 * Function Description: Add custon scripts
		 */

		function enqueue_scripts() {
			if( function_exists( 'cp_register_addon' ) ) {
				cp_register_addon( $this->slug, $this->setting );
			}
			wp_register_script( $this->slug.'-script', plugins_url('js/'.$this->slug.'-script.js', __FILE__), array('jquery'), '1.1', true );
			wp_enqueue_script( $this->slug.'-script' );
			add_action( 'admin_head', array( $this, 'hook_css' ) );
		}


		/*
		 * Function Name: hook_css
		 * Function Description: Adds background style script for mailer logo.
		 */


		function hook_css() {
			if( isset( $this->setting['logo_url'] ) ) {
				if( $this->setting['logo_url'] != '' ) {
					$style = '<style>table.bsf-connect-optins td.column-provider.'.$this->slug.'::after {background-image: url("'.$this->setting['logo_url'].'");}.bend-heading-section.bsf-connect-list-header .bend-head-logo.'.$this->slug.'::before {background-image: url("'.$this->setting['logo_url'].'");}</style>';
					echo $style;
				}
			}

		}


		/*
		 * Function Name: get_klaviyo_data
		 * Function Description: Get klaviyo input fields
		 */

		function get_klaviyo_data() {
			$isKeyChanged = false;

			$connected = false;
			ob_start();

			$klaviyo_api = get_option($this->slug.'_api_key');

      if( $klaviyo_api != '' ) {
      	$res = wp_remote_get( $this->api_url . 'lists?api_key=' . $klaviyo_api );
				if( $res['response']['code'] == 200 ) {
					$formstyle = 'style="display:none;"';
				} else {
					$formstyle = '';
					$isKeyChanged = true;
				}
      } else {
      	$formstyle = '';
      }
      ?>
			<div class="bsf-cnlist-form-row" <?php echo $formstyle; ?>>
				<label for="cp-list-name" ><?php _e( $this->setting['name'] . " API Key", "smile" ); ?></label>
            	<input type="text" autocomplete="off" id="<?php echo $this->slug; ?>_api_key" name="<?php echo $this->slug; ?>_api_key" value="<?php echo esc_attr( $klaviyo_api ); ?>"/>
	        </div>

            <div class="bsf-cnlist-form-row <?php echo $this->slug; ?>-list">
	            <?php
	            if( $klaviyo_api != '' && !$isKeyChanged ) {
		            $klaviyo_lists = $this->get_klaviyo_lists( $klaviyo_api );

					if( !empty( $klaviyo_lists ) ){
						$connected = true;
					?>
					<label for="<?php echo $this->slug; ?>-list"><?php echo __( "Select List", "smile" ); ?></label>
					<select id="<?php echo $this->slug; ?>-list" class="bsf-cnlist-select" name="<?php echo $this->slug; ?>-list">
					<?php
						foreach($klaviyo_lists as $id => $name) {
					?>
						<option value="<?php echo $id; ?>"><?php echo $name; ?></option>
					<?php
						}
					?>
					</select>
					<?php
					} else {
					?>
						<label for="<?php echo $this->slug; ?>-list"><?php echo __( "You need at least one list added in " . $this->setting['name'] . " before proceeding.", "smile" ); ?></label>
					<?php
					}
				}
	            ?>
            </div>

            <div class="bsf-cnlist-form-row">
            	<?php if( $klaviyo_api == "" ) { ?>
	            	<button id="auth-<?php echo $this->slug; ?>" class="button button-secondary auth-button" disabled><?php _e( "Authenticate " . $this->setting['name'], "smile" ); ?></button><span class="spinner" style="float: none;"></span>
	            <?php } else {
	            		if( $isKeyChanged ) {
	            ?>
	            	<div id="update-<?php echo $this->slug; ?>" class="update-mailer" data-mailerslug="<?php echo $this->setting['name']; ?>" data-mailer="<?php echo $this->slug; ?>"><span><?php _e( "Your credentials seems to be changed.</br>Use different '" . $this->setting['name'] . "' credentials?", "smile" ); ?></span></div><span class="spinner" style="float: none;"></span>
	            <?php
	            		} else {
	            ?>
	            	<div id="disconnect-<?php echo $this->slug; ?>" class="button button-secondary" data-mailerslug="<?php echo $this->setting['name']; ?>" data-mailer="<?php echo $this->slug; ?>"><span><?php _e( "Use different '" . $this->setting['name'] . "' account?", "smile" ); ?></span></div><span class="spinner" style="float: none;"></span>
	            <?php
	            		}
	            ?>
	            <?php } ?>
	        </div>

            <?php
            $content = ob_get_clean();

            $result['data'] = $content;
            $result['helplink'] = $this->setting['where_to_find_url'];
            $result['isconnected'] = $connected;
            echo json_encode($result);
            exit();
        }


		/*
		 * Function Name: update_klaviyo_authentication
		 * Function Description: Update klaviyo values to ConvertPlug
		 */

		function update_klaviyo_authentication() {
			$post = $_POST;

			$data = array();
			$klaviyo_api = $post['klaviyo_api_key'];

			if( $post['klaviyo_api_key'] == "" ){
				print_r(json_encode(array(
					'status' => "error",
					'message' => __( "Please provide valid API Key for your " . $this->setting['name'] . " account.", "smile" )
				)));
				exit();
			}
			ob_start();

			$res = wp_remote_get( $this->api_url . 'lists?api_key=' . $klaviyo_api );
			if( !is_wp_error($res) && $res['response']['code'] == 200 ) {
				$campaigns = json_decode( wp_remote_retrieve_body( $res ) );

				if( count($campaigns->data) < 1 ) {
					print_r(json_encode(array(
            'status' => "error",
            'message' => __( "You have zero lists in your " . $this->setting['name'] . " account. You must have at least one list before integration." , "smile" )
          )));
          exit();
				}

				if( count($campaigns->data) > 0 ) {
					$query = '';
				?>
				<label for="<?php echo $this->slug; ?>-list">Select List</label>
				<select id="<?php echo $this->slug; ?>-list" class="bsf-cnlist-select" name="<?php echo $this->slug; ?>-list">
				<?php
					foreach ($campaigns->data as $key => $cm) {
						$query .= $cm->id.'|'.$cm->name.',';
						$klaviyo_lists[$cm->id] = $cm->name;
				?>
					<option value="<?php echo $cm->id; ?>"><?php echo $cm->name; ?></option>
				<?php
					}
				?>
				</select>
				<input type="hidden" id="mailer-all-lists" value="<?php echo $query; ?>"/>
				<input type="hidden" id="mailer-list-action" value="update_<?php echo $this->slug; ?>_list"/>
				<input type="hidden" id="mailer-list-api" value="<?php echo $klaviyo_api; ?>"/>
				<div class="bsf-cnlist-form-row">
					<div id="disconnect-<?php echo $this->slug; ?>" class="" data-mailerslug="<?php echo $this->setting['name']; ?>" data-mailer="<?php echo $this->slug; ?>">
						<span>
							<?php _e( "Use different '" . $this->setting['name'] . "' account?", "smile" ); ?>
						</span>
					</div>
					<span class="spinner" style="float: none;"></span>
				</div>
				<?php
				} else {
				?>
					<label for="<?php echo $this->slug; ?>-list"><?php echo __( "You need at least one list added in " . $this->setting['name'] . " before proceeding.", "smile" ); ?></label>
				<?php
				}
			} else {
				print_r(json_encode(array(
					'status' => "error",
					'message' => "Something went wrong!"
				)));
				exit();
			}

			$html = ob_get_clean();

			update_option( $this->slug.'_api_key', $klaviyo_api );
			update_option( $this->slug.'_lists', $klaviyo_lists );

			print_r(json_encode(array(
				'status' => "success",
				'message' => $html
			)));

			exit();
		}


		/*
		 * Function Name: klaviyo_add_subscriber
		 * Function Description: Add subscriber
		 */

		function klaviyo_add_subscriber() {
			$ret = true;
			$email_status = false;
      $style_id = isset( $_POST['style_id'] ) ? $_POST['style_id'] : '';
      $contact = $_POST['param'];
      $contact['source'] = ( isset( $_POST['source'] ) ) ? $_POST['source'] : '';
      $msg = isset( $_POST['message'] ) ? $_POST['message'] : __( 'Thanks for subscribing. Please check your mail and confirm the subscription.', 'smile' );

      if ( is_user_logged_in() && current_user_can( 'access_cp' ) ) {
          $default_error_msg = __( 'THERE APPEARS TO BE AN ERROR WITH THE CONFIGURATION.', 'smile' );
      } else {
          $default_error_msg = __( 'THERE WAS AN ISSUE WITH YOUR REQUEST. Administrator has been notified already!', 'smile' );
      }

			$klaviyo_api_key = get_option( 'klaviyo_api_key' );
			$klaviyo_list_id = $_POST['list_id'];

			//	Check Email in MX records
			if( isset( $_POST['param']['email'] ) ) {
        $email_status = ( !( isset( $_POST['only_conversion'] ) ? true : false ) ) ? apply_filters('cp_valid_mx_email', $_POST['param']['email'] ) : false;
      }

			if( $email_status ) {
				if( function_exists( "cp_add_subscriber_contact" ) ){
					$isuserupdated = cp_add_subscriber_contact( $_POST['option'] , $contact );
				}

				if ( !$isuserupdated ) {  // if user is updated dont count as a conversion
					// update conversions
					smile_update_conversions($style_id);
				}
				if( isset( $_POST['param']['email'] ) ) {
					$status = 'success';
					$errorMsg =  '';
					$ch = curl_init( $this->api_url . 'list/' . $klaviyo_list_id . '/members');

					$post_args = array(
						'api_key' => $klaviyo_api_key,
						'email' => $_POST['param']['email'],
						'confirm_optin' => "false"
					);
					if (isset( $_POST['param']['first_name'] )) {
						$properties = array();
						$properties['$first_name'] = $_POST['param']['first_name'];
						$post_args['properties'] = json_encode($properties);
					}

					curl_setopt( $ch,CURLOPT_POST, 2 );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($post_args) );
					curl_setopt( $ch, CURLOPT_FAILONERROR, 1 );
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

					$response = curl_exec($ch);
					$http_response_error = curl_error($ch);

					if( $http_response_error != '' )  {
						if( isset( $_POST['source'] ) ) {
	        		return false;
	        	} else {
	        		if(strpos($http_response_error, '404')){
	        			$errorMsg =  __('ListId is not present', 'smile' );
	        		}else{
	        			$errorMsg = $http_response_error;
	        		}

	        		if ( is_user_logged_in() && current_user_can( 'access_cp' ) ) {
                $detailed_msg = $errorMsg;
	            } else {
                $detailed_msg = '';
	            }
	            if( $detailed_msg !== '' & $detailed_msg !== null ) {
                $page_url = isset( $_POST['cp-page-url'] ) ? $_POST['cp-page-url'] : '';

                // notify error message to admin
                if( function_exists('cp_notify_error_to_admin') ) {
                  $result   = cp_notify_error_to_admin($page_url);
                }
	            }

	        		print_r(json_encode(array(
								'action' => ( isset( $_POST['message'] ) ) ? 'message' : 'redirect',
								'email_status' => $email_status,
								'status' => 'error',
								'message' => $default_error_msg,
								'detailed_msg' => $detailed_msg,
								'url' => ( isset( $_POST['message'] ) ) ? 'none' : $_POST['redirect'],
							)));
							exit();
	        	}

					}
				}

			} else {
				if( isset( $_POST['only_conversion'] ) ? true : false ){
					// update conversions
					$status = 'success';
					smile_update_conversions( $style_id );
					$ret = true;
				} else if( isset( $_POST['param']['email'] ) ) {
          $msg = ( isset( $_POST['msg_wrong_email']  )  && $_POST['msg_wrong_email'] !== '' ) ? $_POST['msg_wrong_email'] : __( 'Please enter correct email address.', 'smile' );
          $status = 'error';
          $ret = false;
        } else if( !isset( $_POST['param']['email'] ) ) {
          //$msg = __( 'Something went wrong. Please try again.', 'smile' );
          $msg  = $default_error_msg;
          $errorMsg = __( 'Email field is mandatory to set in form.', 'smile' );
          $status = 'error';
        }
			}

			if ( is_user_logged_in() && current_user_can( 'access_cp' ) ) {
        $detailed_msg = $errorMsg;
      } else {
        $detailed_msg = '';
      }

      if( $detailed_msg !== '' & $detailed_msg !== null ) {
        $page_url = isset( $_POST['cp-page-url'] ) ? $_POST['cp-page-url'] : '';

        // notify error message to admin
        if( function_exists('cp_notify_error_to_admin') ) {
          $result   = cp_notify_error_to_admin($page_url);
        }
      }

			if( isset( $_POST['source'] ) ) {
    		return $ret;
    	} else {
    		print_r(json_encode(array(
					'action' => ( isset( $_POST['message'] ) ) ? 'message' : 'redirect',
					'email_status' => $email_status,
					'status' => $status,
					'message' => $msg,
					'detailed_msg' => $detailed_msg,
					'url' => ( isset( $_POST['message'] ) ) ? 'none' : $_POST['redirect'],
				)));
				exit();
    	}
		}


		/*
		 * Function Name: disconnect_klaviyo
		 * Function Description: Disconnect current Klaviyo from wp instance
		 */

		function disconnect_klaviyo() {

			delete_option( 'klaviyo_api_key' );
			delete_option( 'klaviyo_username' );
			delete_option( 'klaviyo_password' );

			$smile_lists = get_option('smile_lists');
			if( !empty( $smile_lists ) ){
				foreach( $smile_lists as $key => $list ) {
					$provider = $list['list-provider'];
					if( strtolower( $provider ) == strtolower( $this->slug ) ){
						$smile_lists[$key]['list-provider'] = "Convert Plug";
						$contacts_option = "cp_" . $this->slug . "_" . preg_replace( '#[ _]+#', '_', strtolower( $list['list-name'] ) );
            $contact_list = get_option( $contacts_option );
            $deleted = delete_option( $contacts_option );
            $status = update_option( "cp_connects_" . preg_replace( '#[ _]+#', '_', strtolower( $list['list-name'] ) ), $contact_list );
					}
				}
				update_option( 'smile_lists', $smile_lists );
			}

			print_r(json_encode(array(
        'message' => "disconnected",
			)));
			exit();
		}


		/*
		 * Function Name: get_klaviyo_lists
		 * Function Description: Get Klaviyo Mailer Campaign list
		 */

		function get_klaviyo_lists( $klaviyo_api = '' ) {
			if( $klaviyo_api != '' ) {

				$res = wp_remote_get( $this->api_url . 'lists?api_key=' . $klaviyo_api );
      	if( $res['response']['code'] == 200 ) {
					$decoded = json_decode( wp_remote_retrieve_body( $res ) );
					$lists = array();
					foreach($decoded->data as $offset => $cm) {
						$lists[$cm->id] = $cm->name;
					}
					return $lists;
				} else {
					return array();
				}
			} else {
				return array();
			}
		}
	}
	new Smile_Mailer_Klaviyo;
}

$bsf_core_version_file = realpath(dirname(__FILE__).'/admin/bsf-core/version.yml');
if(is_file($bsf_core_version_file)) {
	global $bsf_core_version, $bsf_core_path;
	$bsf_core_dir = realpath(dirname(__FILE__).'/admin/bsf-core/');
	$version = file_get_contents($bsf_core_version_file);
	if(version_compare($version, $bsf_core_version, '>')) {
		$bsf_core_version = $version;
		$bsf_core_path = $bsf_core_dir;
	}
}
add_action('init', 'bsf_core_load', 999);
if(!function_exists('bsf_core_load')) {
	function bsf_core_load() {
		global $bsf_core_version, $bsf_core_path;
		if(is_file(realpath($bsf_core_path.'/index.php'))) {
			include_once realpath($bsf_core_path.'/index.php');
		}
	}
}
?>
