<?php


class db {

    protected $connection;
    protected $query;
    protected $show_errors = TRUE;
    protected $query_closed = TRUE;
    public $query_count = 0;

    public function __construct($dbhost = 'localhost', $dbuser = 'root', $dbpass = '', $dbname = '', $charset = 'utf8') {
        $this->connection = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
        if ($this->connection->connect_error) {
            $this->error('Failed to connect to MySQL - ' . $this->connection->connect_error);
        }
        $this->connection->set_charset($charset);
    }

    public function query($query) {
        if (!$this->query_closed) {
            $this->query->close();
        }
        if ($this->query = $this->connection->prepare($query)) {
            if (func_num_args() > 1) {
                $x = func_get_args();
                $args = array_slice($x, 1);
                $types = '';
                $args_ref = array();
                foreach ($args as $k => &$arg) {
                    if (is_array($args[$k])) {
                        foreach ($args[$k] as $j => &$a) {
                            $types .= $this->_gettype($args[$k][$j]);
                            $args_ref[] = &$a;
                        }
                    } else {
                        $types .= $this->_gettype($args[$k]);
                        $args_ref[] = &$arg;
                    }
                }
                array_unshift($args_ref, $types);
                call_user_func_array(array($this->query, 'bind_param'), $args_ref);
            }
            $this->query->execute();
            if ($this->query->errno) {
                $this->error('Unable to process MySQL query (check your params) - ' . $this->query->error);
            }
            $this->query_closed = FALSE;
            $this->query_count++;
        } else {
            $this->error('Unable to prepare MySQL statement (check your syntax) - ' . $this->connection->error);
        }
        return $this;
    }


    public function fetchAll($callback = null) {
        $params = array();
        $row = array();
        $meta = $this->query->result_metadata();
        while ($field = $meta->fetch_field()) {
            $params[] = &$row[$field->name];
        }
        call_user_func_array(array($this->query, 'bind_result'), $params);
        $result = array();
        while ($this->query->fetch()) {
            $r = array();
            foreach ($row as $key => $val) {
                $r[$key] = $val;
            }
            if ($callback != null && is_callable($callback)) {
                $value = call_user_func($callback, $r);
                if ($value == 'break') break;
            } else {
                $result[] = $r;
            }
        }
        $this->query->close();
        $this->query_closed = TRUE;
        return $result;
    }

    public function fetchArray() {
        $params = array();
        $row = array();
        $meta = $this->query->result_metadata();
        while ($field = $meta->fetch_field()) {
            $params[] = &$row[$field->name];
        }
        call_user_func_array(array($this->query, 'bind_result'), $params);
        $result = array();
        while ($this->query->fetch()) {
            foreach ($row as $key => $val) {
                $result[$key] = $val;
            }
        }
        $this->query->close();
        $this->query_closed = TRUE;
        return $result;
    }

    public function close() {
        return $this->connection->close();
    }

    public function numRows() {
        $this->query->store_result();
        return $this->query->num_rows;
    }

    public function affectedRows() {
        return $this->query->affected_rows;
    }

    public function lastInsertID() {
        return $this->connection->insert_id;
    }

    public function error($error) {
        if ($this->show_errors) {
            exit($error);
        }
    }

    private function _gettype($var) {
        if (is_string($var)) return 's';
        if (is_float($var)) return 'd';
        if (is_int($var)) return 'i';
        return 'b';
    }
    
    public function curl_post($url, $postData = [], $headers = []){

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
        }

        if (isset($error_msg)) {
            return $error_msg;
        }

        return json_decode($response, true);

    }

    public function curl_get($url, $headers = []){

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_POSTFIELDS => "",
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
        }

        if (isset($error_msg)) {
            return $error_msg;
        }

        return json_decode($response, true);
    }

    public function test(){
        echo "asdfasdfasdfasdfasdfasdf 12222222";
    }

    public function get_category_id($category_parent_id, $hasParam = true){

        if($hasParam){

            $sql = "SELECT  t.*, tt.* FROM wp_terms AS t  INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ('category') AND tt.parent = '$category_parent_id' ORDER BY t.name ASC";

        }else{

            $sql = "SELECT  t.*, tt.* FROM wp_terms AS t  INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ('category') ORDER BY t.name ASC";

        }

        $wp_categories_list = $this->query($sql)->fetchAll();

        return $wp_categories_list;
    }

    public function get_option($key, $value = '0'){

        if(!empty($key)){

            $sql = "SELECT * FROM `wp_options` where option_name = '$key' limit 1";
            $alreadyHas = $this->query($sql)->numRows();

            if($alreadyHas > 0){
                return $this->query($sql)->fetchArray();
            }else{
                $optSql = "INSERT INTO `wp_options` (`option_name`, `option_value`) VALUES ('$key', '$value')";
                $res = $this->query($optSql);

                if($res->affected_rows > 0){
                    return $this->query($sql)->fetchArray();
                }

            }
        }

        return [];

    }

    public function del_option($key){

        if(!empty($key)) {
            $sql = "DELETE FROM wp_options WHERE option_name = '$key'";
            return $this->query($sql);
        }

        return false;
    }

    public function get_cat_id_from_name($cat_name = 'null'){
        $catData = $this->query("SELECT t.*, tt.* FROM wp_terms AS t INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ('category') AND t.name IN ('$cat_name') LIMIT 1")->fetchArray();

        return isset($catData['term_id']) ? $catData['term_id'] : 0;
    }

    public function get_post_title_exits($post_title = 'null'){

        $postData = $this->query("SELECT ID FROM wp_posts WHERE post_title = '$post_title'")->fetchArray();

        return sizeof($postData) > 0 ? true : false;
    }

    public function remove_http($url, $remove = '1') {

        if($remove == 1){
            $disallowed = array('http://', 'https://');
        }elseif ($remove == 2){
            $disallowed = array('http://');
        }elseif($remove == 3){
            $disallowed = array('https://');
        }

        foreach($disallowed as $d) {
            if(strpos($url, $d) === 0) {
                return str_replace($d, '', $url);
            }
        }
        return $url;
    }

    public function getUserId(){
        // Add new user eContentLibrary

        if($this->getUserIdFromUser('eContentLibrary') == 0) {
            $user_author_id = $this->insertNewUser('eContentLibrary');
        }else{
            $user_author_id = $this->getUserIdFromUser('eContentLibrary');
        }

        return $user_author_id;
    }

    public function getUserIdFromUser($user_name){

        $sql = "SELECT SQL_CALC_FOUND_ROWS wp_users.* FROM wp_users WHERE 1=1 AND (user_login LIKE '$user_name' OR user_url LIKE '$user_name' OR user_email LIKE '$user_name' OR user_nicename LIKE '$user_name' OR display_name LIKE '$user_name') ORDER BY user_login ASC";

        $userData = $this->query($sql)->fetchArray();

        return !empty($userData) ? $userData['ID'] : 0;
    }

    public function insertNewUser($user_name){

        $insertSql = "INSERT INTO `wp_users` (`ID`, `user_login`, `user_pass`, `user_nicename`, `user_email`, `user_url`, `user_registered`, `user_activation_key`, `user_status`, `display_name`) VALUES (NULL, '$user_name', 'testing', '$user_name', '', '', '2021-11-19 00:00:00', '', '0', '$user_name');";
        $this->query($insertSql);

        return $this->lastInsertID();

    }

    public function wp_insert_post($post_title, $post_content, $post_author, $post_category, $post_status = 'publish', $post_type = 'post'){

        $currentDate = gmdate('Y-m-d H:i:s');

        $insertPostSql = "INSERT INTO `wp_posts` (`post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_content_filtered`, `post_title`, `post_excerpt`, `post_status`, `post_type`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_parent`, `menu_order`, `post_mime_type`, `guid`) 
                                          VALUES ($post_author, '$currentDate', '$currentDate', '$post_content', '', '$post_title', '', '$post_status', '$post_type', 'open', 'open', '', '$post_title', '', '', '$currentDate', '$currentDate', 0, 0, '', '')";
//        print_r($insertPostSql);exit();
        $this->query($insertPostSql);
        $post_last_id = $this->lastInsertID();

        if($post_last_id > 0){

            foreach ($post_category as $cat_val){
                $numRows = $this->query("SELECT * FROM `wp_term_relationships` where object_id = '$post_last_id' AND term_taxonomy_id = '$cat_val'")->numRows();
                if($numRows == 0){
                    $saveCatSql = "INSERT INTO `wp_term_relationships` (`object_id`, `term_taxonomy_id`) VALUES ('$post_last_id', '$post_category[0]')";
                    $this->query($saveCatSql);
                }
            }

        };

        return $post_last_id;
    }

    public function trackImportedPost($post_id, $api_import_module_type, $api_import_post_id){

        $currentDate = gmdate('Y-m-d H:i:s');
        $trackSql = "INSERT INTO `wp_track_imported_posts` (`wp_post_id`, `api_import_module_type`, `api_import_post_id`, `created_date`) 
                                                    VALUES ('$post_id', '$api_import_module_type', '$api_import_post_id', '$currentDate')";
        $this->query($trackSql);
        return $this->lastInsertID();
    }

    public function termExists($slug = 'null'){

        $termSql = "SELECT tt.term_id, tt.term_taxonomy_id FROM wp_terms AS t INNER JOIN wp_term_taxonomy as tt ON tt.term_id = t.term_id WHERE t.slug = '$slug' AND tt.taxonomy = 'category' ORDER BY t.term_id ASC LIMIT 1";
        $userData = $this->query($termSql)->fetchArray();

        return !empty($userData) ? $userData['term_id'] : 0;
    }

    public function set_featured_image_from_external_url($url, $post_id, $upload_dir){

        $url = $this->remove_http_new($url);
        $url = "http://".$url;

        if ( ! filter_var($url, FILTER_VALIDATE_URL) ||  empty($post_id) ) {
            return;
        }

        // Add Featured Image to Post
        $image_url 		  = preg_replace('/\?.*/', '', $url); // removing query string from url & Define the image URL here
        $image_name       = basename($image_url);
        $image_data       = file_get_contents($url); // Get image data
        $unique_file_name = $upload_dir['path'].DIRECTORY_SEPARATOR.$image_name; // Generate unique name
        $filename         = basename( $unique_file_name ); // Create image file name

        // Check folder permission and define file location
        if( is_writeable( $upload_dir['path'] ) ) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        // Create the image  file on the server
        file_put_contents( $file, $image_data );

        // Check image file type
        $wp_filetype = $this->_mime_content_type( $filename );

        // Set attachment data
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => $filename ,
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        // Create the attachment
        $attach_id = $this->wp_insert_post_attachment( $wp_filetype['type'], $filename, '', $post_id, $upload_dir );

        // Include image.php
//        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Define attachment metadata
//        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

        // Assign metadata to attachment
//        wp_update_attachment_metadata( $attach_id, $attach_data );

        // And finally assign featured image to post
//        set_post_thumbnail( $post_id, $attach_id );
    }

    public function remove_http_new($url, $remove = '1') {

        if($remove == 1){
            $disallowed = array('http://', 'https://');
        }elseif ($remove == 2){
            $disallowed = array('http://');
        }elseif($remove == 3){
            $disallowed = array('https://');
        }

        foreach($disallowed as $d) {
            if(strpos($url, $d) === 0) {
                return str_replace($d, '', $url);
            }
        }
        return $url;
    }

    public function _mime_content_type($filename) {
        $result = new finfo();

        if (is_resource($result) === true) {
            return $result->file($filename, FILEINFO_MIME_TYPE);
        }

        return false;
    }


    public function wp_insert_post_attachment($post_mime_type, $post_title, $post_content, $parent_id, $upload_dir, $post_status = 'inherit', $post_type = 'attachment'){


        $currentDate = gmdate('Y-m-d H:i:s');

        $insertPostSql = "INSERT INTO `wp_posts` (`post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_content_filtered`, `post_title`, `post_excerpt`, `post_status`, `post_type`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_parent`, `menu_order`, `post_mime_type`, `guid`) 
                                          VALUES (1, '$currentDate', '$currentDate', '$post_content', '', '$post_title', '', '$post_status', '$post_type', 'open', 'closed', '', '$post_title', '', '', '$currentDate', '$currentDate', $parent_id, 0, '$post_mime_type', '')";

        $this->query($insertPostSql);
        $post_last_id = $this->lastInsertID();

        if($post_last_id > 0){
            $wpPmetaSql = "INSERT INTO `wp_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES ($parent_id, '_thumbnail_id', $post_last_id)";
            $this->query($wpPmetaSql);

            $makeFileName = $upload_dir['subdir'].'/'.$post_title;

            $wpPmetaSql1 = "INSERT INTO `wp_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES ($post_last_id, '_wp_attached_file', '$makeFileName')";
            $this->query($wpPmetaSql1);
            
            $attachMetaData = unserialize('a:5:{s:5:"width";i:165;s:6:"height";i:220;s:4:"file";s:26:"2021/11/71_htmlThumb-2.jpg";s:5:"sizes";a:0:{}s:10:"image_meta";a:12:{s:8:"aperture";s:1:"0";s:6:"credit";s:0:"";s:6:"camera";s:0:"";s:7:"caption";s:0:"";s:17:"created_timestamp";s:1:"0";s:9:"copyright";s:0:"";s:12:"focal_length";s:1:"0";s:3:"iso";s:1:"0";s:13:"shutter_speed";s:1:"0";s:5:"title";s:0:"";s:11:"orientation";s:1:"0";s:8:"keywords";a:0:{}}}');

            $attachMetaData['file'] = $makeFileName;

            $attachMetaData = serialize($attachMetaData);


            $wpPmetaSql2 = "INSERT INTO `wp_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES ($post_last_id, '_wp_attachment_metadata', '$attachMetaData')";
            $this->query($wpPmetaSql2);

        }



        return $post_last_id;
    }
}
?>