<?php

require_once 'CloudflareStreamAPI.php';

class VideoUploader
{
    private static $default_video_upload_storage_path = 'video_uploads';
    private static $default_video_public_url = 'http://originalvideos.proaudio.courses/';

    public static function init()
    {
        add_action( 'wp_ajax_nopriv_upload_video', ['VideoUploader', 'upload'] );
        add_action( 'wp_ajax_upload_video', ['VideoUploader', 'upload'] );

        add_action( 'wp_ajax_nopriv_get_course_videos', ['VideoUploader', 'getCourseVideos'] );
        add_action( 'wp_ajax_get_course_videos', ['VideoUploader', 'getCourseVideos'] );

        add_action( 'wp_ajax_nopriv_get_video_cloudflare_status', ['VideoUploader', 'getVideoCloudflareStatus'] );
        add_action( 'wp_ajax_get_video_cloudflare_status', ['VideoUploader', 'getVideoCloudflareStatus'] );

        add_action( 'wp_ajax_nopriv_delete_video', ['VideoUploader', 'delete'] );
        add_action( 'wp_ajax_delete_video', ['VideoUploader', 'delete'] );
    }

    /**
     * Method to be executed on plugin activation
     *
     * @return void
     * @static
     */
    public static function activate()
    {
        update_option('VU_VERSION', VU_VERSION);

        self::migrate();
    }

    /**
     * Method to be executed on plugin deactivation
     *
     * @return void
     * @static
     */
    public static function deactivate( )
    {
        // ToDo: Decide whether to delete the created table on plugin uninstall or anything else.
    }

    /**
     * @param $filename
     * @return string
     */
    public static function getPublicUrl($filename)
    {
        return get_option('vu_public_url', self::$default_video_public_url) . $filename;
    }

    public static function upload()
    {
        if (
            !is_user_logged_in()
            || ! wp_verify_nonce( $_REQUEST['front_course_video_uploader'], 'front_course_video_uploader' )
        ) {
            wp_send_json_error("Chunk or file not found.", 401);
        }

        if ( empty( $_FILES ) || !isset($_FILES['file']) ) {
            wp_send_json_error("Chunk or file not found.", 404);
        }

        if ( empty( $_POST ) || !isset( $_POST['course_id'] ) ) {
            //ToDo: Query course_id if found into db
            wp_send_json_error("Course not found.", 404);
        }

        if(!self::validateMimeType($_FILES['file'])){
            wp_send_json_error("File not accepted.", 403);
        }

        // chunk variables
        $fileId = $_POST['dzUuid'];
        $chunkIndex = $_POST['dzChunkIndex'] + 1;
        $chunkTotal = $_POST['dzTotalChunkCount'];
        $couse_id = $_POST['course_id'];

        // file path variables
        $targetPath = ABSPATH . DIRECTORY_SEPARATOR . self::getStoragePath();

        if ( !is_dir($targetPath) )
        {
            mkdir($targetPath, 0777, true);
        }

        $fileType = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $fileSize = $_FILES["file"]["size"];
        $original_name = $_FILES['file']['name'];
        $filename = "{$fileId}-{$chunkIndex}.{$fileType}";
        $targetFile = $targetPath . $filename;

        chmod(realpath($targetPath), 0777);

        move_uploaded_file($_FILES['file']['tmp_name'], $targetFile);

        // Be sure that the file has been uploaded
        if ( !file_exists($targetFile) ) {
            wp_send_json("An error occurred and we couldn't upload the requested file.", 500);
        }

        if ( $chunkIndex == $chunkTotal ) {
            $filename = "{$fileId}.{$fileType}";
            $file_url = "{$targetPath}{$filename}";
            $fp = fopen($file_url, 'w');

            if ($fp === false) {
                wp_send_json_error("Cannot create destination folder.", 500);
            }

            // loop through temp files and grab the content
            for ($i = 1; $i <= $chunkTotal; $i++) {

                $temp_file_path ="{$targetPath}{$fileId}-{$i}.{$fileType}";

                $chunk = file_get_contents(realpath($temp_file_path));

                // check chunk content
                if ( empty($chunk) ) {
                    wp_send_json("Chunks are uploading as empty strings.");
                }

                fwrite($fp, $chunk);

                unlink($temp_file_path);

                if ( file_exists($temp_file_path) ) {
                    error_log("Your temp files could not be deleted.");
                }
            }

            fclose($fp);

            $data = [
                'course_id' => $couse_id,
                'user_id' => get_current_user_id(),
                'filename' => $filename,
                'original_name' => $original_name,
                'realpath' => $file_url
            ];

            self::cloudFlareUpload($filename, $original_name, $data);

            $video = VideoRepository::store($data);

            wp_send_json($video, 202);
        } else {
            wp_send_json(null, 204);
        }
    }

    public static function getCourseVideos()
    {
        $course_id = $_REQUEST['course_id'];

        if (
            !is_user_logged_in()
            || ! wp_verify_nonce( $_REQUEST['nonce'], 'front_course_videos' )
            || !$course_id
        ) {
            wp_send_json("Course not found.", 403);
        }

        $videos = VideoRepository::getAllFromCourse($course_id);

        wp_send_json($videos, 200);
    }

    public static function getVideoCloudflareStatus()
    {
        $video_id = $_REQUEST['video_id'];

        if (
            !is_user_logged_in()
            || ! wp_verify_nonce( $_REQUEST['nonce'], 'front_course_video_cloudflare_status' )
            || !$video_id
        ) {
            wp_send_json("Video not found.", 403);
        }

        $video = VideoRepository::find($video_id);

        if($video)
        {
            if (!isset($video->cf_uid)) {
                // ToDo: Re-upload video to CF
                error_log("Failed Video: $video_id");
            }

            if( $video->ready_to_stream == false ) {
                $api = new CloudflareStreamAPI();

                $response = $api->status(['uid' => $video->cf_uid], true);

                $data = [];

                CloudflareStreamAPI::responseToArray($response, $data);

                $video = VideoRepository::update($data, $video_id);
            }

            wp_send_json($video, 200);
        }

        wp_send_json(null, 404);
    }

    public static function delete()
    {
        $video_id = (int)$_REQUEST['video_id'];

        if (
            !is_user_logged_in()
            || ! wp_verify_nonce( $_REQUEST['nonce'], 'front_course_delete_video' )
            || !$video_id
        ) {
            wp_send_json("Video not found.", 403);
        }

        $video = VideoRepository::find($video_id);

        if($video)
        {
            $deleted = VideoRepository::softDelete($video_id, ['user_id' => get_current_user_id()]);

            if($deleted && $deleted->deleted_at != null) {
                // Delete video from server
                // unlink($video->realpath);

                // Delete video from cloudflare
                $api = new CloudflareStreamAPI();

                $response = $api->delete(['uid' => $video->cf_uid], true);

                error_log($response);

                wp_send_json(null, 204);
            }

            error_log($deleted);
        }

        wp_send_json('Something went wrong!', 404);
    }

    /**
     * Create the DB table videos needed for storing uploaded videos
     * It kills WordPress execution and displays HTML page with an error message in case of any sql error of the function.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @return void
     */
    private static function migrate()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        if ( VU_VERSION == '1.0.0' )
        {
            $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}videos` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            course_id mediumint(9) DEFAULT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            filename text NOT NULL,
            original_name text NOT NULL,
            thumbnail text null,
            realpath text null,
            status tinyint(1) NOT NULL DEFAULT 0,            
            ready_to_stream tinyint(1) NOT NULL DEFAULT 0,     
            duration DECIMAL(10,1) NOT NULL DEFAULT -1,
            cf_uid text NULL,
            cf_url text NULL,
            cf_status varchar(32) NULL,
            cf_progress int(11) NOT NULL DEFAULT 0,
            cf_response text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NULL,
            deleted_at TIMESTAMP NULL DEFAULT NULL
            PRIMARY KEY  (id)
        ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if($wpdb->last_error){
                wp_die($wpdb->last_error);
            }
        }
    }

    /**
     * @return string $vu_upload_storage_path
     */
    private static function getStoragePath()
    {
        return get_option('vu_upload_storage_path', self::$default_video_upload_storage_path) . DIRECTORY_SEPARATOR;
    }

    /**
     * Move video to cloudflare
     *
     * @param string $filename
     * @param string $original_name
     * @return
     */
    private static function cloudFlareUpload($filename, $original_name, &$data)
    {
        $url = self::getPublicUrl($filename);

        try {
            $api = new CloudflareStreamAPI();

            $args = [
                'body' => wp_json_encode([
                    'url' => $url,
                    'meta' => [
                        'name' => $original_name
                    ]
                ])
            ];

            $response = $api->move($args);

            CloudflareStreamAPI::responseToArray($response, $data);

        } catch (Exception $e){
            error_log(print_r([$e->getMessage(), $e->getLine()], true));

            $data['ready_to_stream'] = 0;
            $data['cf_status'] = 'error';
        }
    }

    /**
     * Validate file: only videos accepted
     * @param $file
     * @return bool
     */
    private static function validateMimeType($file)
    {
        return in_array($file['type'], [
            'video/x-flv',
            'video/mp4',
            'application/x-mpegURL',
            'video/MP2T',
            'video/3gpp',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'application/octet-stream',
        ]);
    }
}