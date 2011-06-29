<?php
/*
 * @copyright 2010 Kontagent
 * @link http://www.kontagent.com
 */

  //
  // Assumption: Whereever kt_landing.php is included,
  // facebook.php and kontagent.php have already
  // been included prior to the loading of this file.
  //

$facebook = new KtFacebook(array('appId'  => FB_ID,
                                 'secret' => FB_SECRET,
                                 'cookie' => true,
                                 )
                           );

$kt = new Kontagent(KT_API_SERVER, KT_API_KEY, SEND_MSG_VIA_JS);


echo "<script>var KT_API_SERVER = '".KT_API_SERVER."';  var KT_API_KEY = '".KT_API_KEY."';</script>";


$uid = null;
$session = $facebook->getSession();
if($session){
    try{
        $uid = $session['uid'];
    } catch (FacebookApiException $e) {
        error_log($e);
    }
}

if(USE_FB_DIALOG_JS){
    echo "<script>var USE_FB_DIALOG_JS=true;</script>";
}

if(SEND_MSG_VIA_JS || isset($_GET['request_ids'])){
    echo "<script>var SEND_MSG_VIA_JS = true; var FB_ID='".FB_ID."'; var kt_message_queue = [];</script>";
    if($uid){
        echo "<script>var SESSION = ".json_encode($session).";</script>";
    }
}

if(KT_AUTO_PAGEVIEW_TRACKING){
    if($uid)
        echo "<img src='".$kt->gen_tracking_pageview_link($uid)."' width='0px' height='0px' style='display:none;'/>";
}

if($uid){
    if(!SEND_MSG_VIA_JS){
        //
        // Track Install
        //

        /****
         * Facebook has gotten rid of post-authorized url for all new apps. FB did grandfathered
         * old apps to keep their existing post-authroized url.
         *
        $browser_install_cookie_key = $kt->gen_kt_handled_installed_cookie_key(FB_ID, $uid);
        if( !isset($_COOKIE[$browser_install_cookie_key]) ){
            $fb_cookie_arry = $facebook->api(array('method' => 'data.getcookies',
                                                   'name'=>'kt_just_installed',
                                                   'uid' => $uid));
            $arry_size = sizeof($fb_cookie_arry);
            for($i = 0; $i < $arry_size; $i++)
            {
                $cookie = $fb_cookie_arry[$i];
                if( $cookie['name'] == 'kt_just_installed' &&
                    $cookie['uid'] == $uid &&
                    $cookie['value'] == 1)
                {
                    $kt->track_install($uid);
                    $server_output = $facebook->api(array('method' => 'data.setcookie',
                                                          'name' => 'kt_just_installed',
                                                          'uid' =>$uid,
                                                          'expires' => time()-345600));
                    break;
                }
            }

            // kt_handle_installed is set to prevent further round
            // trip to facebook to get the fb cookies
            if( $session ){
                if( !headers_sent() ) {
                    setcookie( $browser_install_cookie_key, 'done' );
                }
            }
        }
        ***/

        /***
         * Facebook made installed=1 available again. We are going to take advantage of that.
         */
        if(isset($_GET['installed']) && !isset($_GET['request_ids'])){
            $kt->track_install($uid);
        }

        //
        //Acquire User Info
        //
        $capture_user_info_key = $kt->gen_kt_capture_user_info_key(FB_ID, $uid);
        if(!isset($_COOKIE[$capture_user_info_key]))
        {
            $user_info = $facebook->api('/me');
            $friends_info = $facebook->api('/me/friends');
            $kt->track_user_info($uid, $user_info, $friends_info);
            if( !headers_sent() ){
                setcookie( $capture_user_info_key, 'done', time()+1209600); // 2 weeks
            }
        }
    }
}


//
// Track other messages
//

if(isset($_GET['kt_type']))
{
    switch($_GET['kt_type'])
    {
    case 'ins':
    {
        if(!$kt->get_send_msg_from_js()){
            $kt->track_invite_sent();
        }else{
            echo "<script>var kt_landing_str='".
                $kt->gen_tracking_invite_sent_url().
                "';</script>";
        }


        // If your user gets forwarded outside of facebook,
        // call $facebook->redirect([canvas url]) to forward your
        // user back.
        // $facebook->redirect(FB_CANVAS_URL);
        break;
    }
    case 'inr':
    {
        error_log($_SERVER['HTTP_REFERER']);//xxx
        if(!$kt->get_send_msg_from_js()){
            $kt->track_invite_received($uid);
            // If it doesn't get rid of the the forward the kt_* parameters, except
            // for the kt_ut tag, after install, we'll get another inr message.
            $no_kt_param_url = $kt->stripped_kt_args($_SERVER['HTTP_REFERER']);
            $facebook->redirect($no_kt_param_url);
        }else{
            echo "<script>var kt_landing_str='".
                $kt->gen_tracking_invite_click_url($uid).
                "';</script>";
        }
        break;
    }
    case 'stream':
    {
        if(!$kt->get_send_msg_from_js()){
            $kt->track_stream_click($uid);
        }
        else
        {
            echo "<script>var kt_landing_str='".
                $kt->gen_tracking_stream_click_url($uid).
                "';</script>";
        }
        break;
    }
    default:
    {
        // track_ucc_click for ads
        if (preg_match("/ad$|partner$|ad_buy(\..+)?$/", $_GET['kt_type'], $matches)){
            $short_tag = $kt->gen_short_tracking_code();
            if(!$kt->get_send_msg_from_js()){
                echo "track_ucc_click - ". $uid. ",". $short_tag;
                $kt->track_ucc_click($uid, $short_tag);
            }
            else{
                echo "<script>var kt_landing_str='".
                    $kt->gen_tracking_ucc_click_url($uid, $short_tag).
                    "';</script>";
            }
        }
        // Spruce Media Ad Tracking
        if ($_GET['kt_type'] == 'ad_buy.spruce') {
            if (isset($_GET['spruce_adid']) && isset($_GET['spruce_sid'])) {
                if(!$kt->get_send_msg_from_js()){
                    echo "track_spruce_ads - ". $_GET['spruce_adid']. ", ". $_GET['spruce_sid'];
                    $kt->track_spruce_ads();
                }else{
                    echo "<script>var kt_landing_str='".
                        $kt->gen_spruce_ads_tracking_url().
                        "';</script>";
                }
            }
        }
        break;
    }

    }// switch
}