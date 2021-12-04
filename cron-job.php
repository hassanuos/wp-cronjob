<?php

    /* All domain list on the top before running cron job*/

    include 'Errors.class.php';
    include 'db.php';

    if (ERR_HANDLER_ENABLED) {
        register_shutdown_function('sysFatalErr');
        set_error_handler('sysErrorhandler');
    }

    $api_base_url = 'staging.econtentlibrary.com';

    $allDomainConfigs = [
        "http://shapely.test"
    ];

    cronJobInit($allDomainConfigs, $api_base_url);

    function cronJobInit($allDomainConfigs, $api_base_url)
    {
        global $DDEH;
        $autoSyncDate = date("D M d Y h:i A");

        try {

            $getAllDomainsData = [];
            $getAllMessages = [];

            foreach ($allDomainConfigs as $key => $domain){
                $wpOption = [];
                $parse = parse_url($domain);

                $curlSession = curl_init();
                curl_setopt($curlSession, CURLOPT_URL, $domain."/wp-content/e-content-library.json");
                curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
                curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);

                $jsonData = json_decode(curl_exec($curlSession), true);
                curl_close($curlSession);

                $getAllDomainsData[$parse['host']] = $jsonData;

                if(isset($jsonData['db_credentials']['db_host'])){

                    $dbhost = $jsonData['db_credentials']['db_host'];
                    $dbuser = $jsonData['db_credentials']['db_user'];
                    $dbpass = $jsonData['db_credentials']['db_pass'];
                    $dbname = $jsonData['db_credentials']['db_name'];

                    $db = new db($dbhost, $dbuser, $dbpass, $dbname);

                    $wpOption = $db->get_option('auto_sync_all');
                }else{
                    $getAllMessages[$key][$domain][] = 'Settings Not saved for this domain!';
                    $wpOption['option_value'] = 'no';

                    $db = new stdClass();
                }

                // print_r($wpOption);exit();

                if(isset($wpOption['option_value']) && $wpOption['option_value'] == 'yes'){

                    /* Delete key when get posts */
                    $db->del_option('last_auto_sync');

                    $checked_cb_data1 = $db->get_option('checked_cb_data');
                    $checked_cb_data2 = isset($checked_cb_data1['option_value']) ? $checked_cb_data1['option_value'] : [];
                    $checked_cb_data = unserialize(unserialize($checked_cb_data2));
                    $upload_dir = $jsonData['upload_dir'];

                    /* auth request */
                    $urlLogin = $api_base_url."/api/Login";
                    $postHeaders = [
                        "Content-Type: application/json",
                        "cache-control: no-cache"
                    ];
                    $webKey = $jsonData['api_credentials']['WebKey'];
                    $auth_data = $db->curl_post($urlLogin, $jsonData['api_credentials'], $postHeaders);

                    $requestGetHeaders = [
                        "authorization: Bearer ".$auth_data['accessToken'],
                        "cache-control: no-cache"
                    ];

                    /* Get Story Boards */
                    $urlStoryBoard = $api_base_url."/api/EclContent/Storyboards";
                    $storyboard_response = $db->curl_get($urlStoryBoard, $requestGetHeaders);

                    if(isset($storyboard_response['storyboards']) && !empty($storyboard_response['storyboards']) && !empty($checked_cb_data['selected_categories_checkboxes_sb'])){
                        $check_storyboard_exist_against_cat = array();
                        $total_count_st=0;

                        $storyboard = $storyboard_response['storyboards'];

                        foreach ($storyboard as $st_post){
                            $post_cat_ids = array();

                            if (!empty($st_post['tags'])) {

                                $wp_categories_list = $db->get_category_id(0, false);

                                foreach ($checked_cb_data['selected_categories_checkboxes_sb'] as $selected_category){

                                    if ( in_array($selected_category['value'], $st_post['tags']) ) {

                                        $check_storyboard_exist_against_cat[] = $selected_category['value'];

                                        foreach ( $wp_categories_list as $wp_category ){
                                            if ( in_array($wp_category['name'], $st_post['tags']) && $selected_category['value'] == $wp_category['name']) {
                                                $post_cat_ids[] = $db->get_cat_id_from_name($wp_category['name']);
                                            }
                                        }

                                        if( ! $db->get_post_title_exits( $st_post['title'] ) ) {

                                            // post does not exist
                                            if(filter_var($st_post['content'], FILTER_VALIDATE_URL)){
                                                $st_post_content = str_replace("'", "\'",$st_post['abstract']).'<br/><iframe src="https://'.$db->remove_http($st_post['content'], 3).$webKey.'" scrolling="no" seamless="" style="height:30vw !important;"></iframe>';
                                            }else{
                                                $st_post_content = str_replace("'", "\'",$st_post['abstract']).'<br/>'.$st_post['content'];
                                            }

                                            $inserted_id = $db->wp_insert_post( $st_post['title'],  (!empty($st_post_content) ? $st_post_content : '...'), $db->getUserId(), $post_cat_ids);

                                            //for tracking insertion
                                            if ($inserted_id) {
                                                $total_count_st++;
                                                $db->trackImportedPost($inserted_id, 'StoryBoard', $st_post['key']);
                                                $db->get_option('last_auto_sync', $autoSyncDate);
                                            }

                                            if(isset($st_post['thumbnail']) && !empty($st_post['thumbnail'])) {
                                                $db->set_featured_image_from_external_url($st_post['thumbnail'], $inserted_id, $upload_dir);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $getAllMessages[$key][$domain][] = "E-Storyboard total inserted Posts => ". $total_count_st;
                    }



                    /* Get Videos */
                    $urlVideos = $api_base_url."/api/EclContent/Videos";
                    $video_response = $db->curl_get($urlVideos, $requestGetHeaders);


                    if(isset($video_response['videos']) && !empty($video_response['videos']) && !empty($checked_cb_data['selected_categories_checkboxes_evid'])){
                        $check_video_exist_against_cat = array();
                        $total_count_vid=0;

                        $videos = $video_response['videos'];
                        $category_parent_id = $db->termExists('eVideo');

                        foreach ($videos as $st_post){
                            $post_cat_ids = array();

                            if (!empty($st_post['tags'])) {

                                $wp_categories_list = $db->get_category_id(0, false);

                                foreach ($checked_cb_data['selected_categories_checkboxes_evid'] as $selected_category){

                                    if ( in_array($selected_category['value'], $st_post['tags']) ) {

                                        $check_video_exist_against_cat[] = $selected_category['value'];

                                        foreach ( $wp_categories_list as $wp_category ){
                                            if ( in_array($wp_category['name'], $st_post['tags']) && $selected_category['value'] == $wp_category['name']) {
                                                $post_cat_ids[] = $db->get_cat_id_from_name($wp_category['name']);
                                            }
                                        }

                                        if( ! $db->get_post_title_exits( $st_post['title'] ) ) {

                                            // post does not exist
                                            if(filter_var($st_post['content'], FILTER_VALIDATE_URL)){
                                                $st_post_content = str_replace("'", "\'",$st_post['abstract']).'<br/><iframe src="https://'.$db->remove_http($st_post['content'], 3).$webKey.'" scrolling="no" seamless="" style="height:30vw !important;"></iframe>';
                                            }else{
                                                $st_post_content = str_replace("'", "\'",$st_post['abstract']).'<br/>'.$st_post['content'];
                                            }

                                            $inserted_id = $db->wp_insert_post( $st_post['title'],  (!empty($st_post_content) ? $st_post_content : '...'), $db->getUserId(), $post_cat_ids);

                                            //for tracking insertion
                                            if ($inserted_id) {
                                                $total_count_vid++;
                                                $db->trackImportedPost($inserted_id, 'StoryBoard', $st_post['key']);
                                                $db->get_option('last_auto_sync', $autoSyncDate);
                                            }

                                            if(isset($st_post['thumbnail']) && !empty($st_post['thumbnail'])) {
                                                $db->set_featured_image_from_external_url($st_post['thumbnail'], $inserted_id, $upload_dir);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $getAllMessages[$key][$domain][] = "E-Video total inserted Posts => ". $total_count_vid;
                    }


                    /* Get Articles */
                    $urlArticles = $api_base_url."/api/EclContent/Articles";
                    $article_response = $db->curl_get($urlArticles, $requestGetHeaders);

                    if(isset($article_response['articles']) && !empty($article_response['articles']) && !empty($checked_cb_data['selected_categories_checkboxes_earticle'])){
                        $article_exist_against_cat = array();
                        $total_count_art=0;

                        $articles = $article_response['articles'];
                        $category_parent_id = $db->termExists('eArticle');

                        foreach ($articles as $st_post){
                            $post_cat_ids = array();

                            if (!empty($st_post['tags'])) {

                                $wp_categories_list = $db->get_category_id(0, false);

                                foreach ($checked_cb_data['selected_categories_checkboxes_earticle'] as $selected_category){

                                    if ( in_array($selected_category['value'], $st_post['tags']) ) {

                                        $article_exist_against_cat[] = $selected_category['value'];

                                        foreach ( $wp_categories_list as $wp_category ){
                                            if ( in_array($wp_category['name'], $st_post['tags']) && $selected_category['value'] == $wp_category['name']) {
                                                $post_cat_ids[] = $db->get_cat_id_from_name($wp_category['name']);
                                            }
                                        }

                                        if( ! $db->get_post_title_exits( $st_post['title'] ) ) {

                                            // post does not exist
                                            if(filter_var($st_post['content'], FILTER_VALIDATE_URL)){
                                                $st_post_content = str_replace("'", "\'", $st_post['abstract']).'<br/><iframe src="https://'.$db->remove_http($st_post['content'], 3).$webKey.'" scrolling="no" seamless="" style="height:30vw !important;"></iframe>';
                                            }else{
                                                $st_post_content = str_replace("'", "\'", $st_post['abstract']).'<br/>'.$st_post['content'];
                                            }

                                            $inserted_id = $db->wp_insert_post( $st_post['title'],  (!empty($st_post_content) ? $st_post_content : '...'), $db->getUserId(), $post_cat_ids);

                                            //for tracking insertion
                                            if ($inserted_id) {
                                                $total_count_art++;
                                                $db->trackImportedPost($inserted_id, 'StoryBoard', $st_post['key']);
                                                $db->get_option('last_auto_sync', $autoSyncDate);
                                            }

                                            if(isset($st_post['thumbnail']) && !empty($st_post['thumbnail'])) {
                                                $db->set_featured_image_from_external_url($st_post['thumbnail'], $inserted_id, $upload_dir);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $getAllMessages[$key][$domain][] = "E-Articles total inserted Posts => ". $total_count_art;
                    }
                }

                sleep(2);
            }

            // print_r($getAllMessages);

            $stringInfo = '[Information => ] ' . linending();
            $stringInfo .= '[Date/Time: ' . date('j F Y @ H:iA') . ']' . linending();
            $stringInfo .= json_encode($getAllMessages);
            $DDEH->log($stringInfo, FILE_ERR_LOG_FILE);

        } catch (Exception $exception) {
            $string = '[Error Code: ' . $exception->getCode() . '] ' . $exception->getMessage() . linending();
            $string .= '[Date/Time: ' . date('j F Y @ H:iA') . ']' . linending();
            $string .= '[Error on line ' . $exception->getLine() . ' in file ' . $exception->getFile() . ']';

            $DDEH->generalErr($string);
        }
    }




?>