<?php


class VideoRepository
{
    /**
     * @param $id
     * @return array|object|void|null
     */
    public static function find($id)
    {
        global $wpdb;

        $table = "{$wpdb->base_prefix}videos";

        $video = $wpdb->get_row(
            $wpdb->prepare("
                SELECT * 
                FROM $table 
                WHERE 
                    id = %d 
                    AND user_id = %d
                    AND deleted_at IS NULL", $id, get_current_user_id())
        );

        if($wpdb->last_error){
            wp_die($wpdb->last_error);
        }

        return $video;
    }

    /**
     * @param $id
     * @return array|object|void|null
     */
    public static function findWithTrashed($id)
    {
        global $wpdb;

        $table = "{$wpdb->base_prefix}videos";

        $video = $wpdb->get_row(
            $wpdb->prepare("
                SELECT * 
                FROM $table 
                WHERE 
                    id = %d 
                    AND user_id = %d", $id, get_current_user_id())
        );

        if($wpdb->last_error){
            wp_die($wpdb->last_error);
        }

        return $video;
    }

    /**
     * @param $data
     * @return array|object|void|null
     */
    public static function store($data){
        global $wpdb;

        $table = "{$wpdb->base_prefix}videos";

        $wpdb->insert(
            $table,
            $data
        );

        if($wpdb->last_error){
            wp_die($wpdb->last_error);
        }

        return self::find($wpdb->insert_id);
    }

    /**
     * @param $data
     * @param $id
     * @param array $where
     * @return array|object|void|null
     */
    public static function update($data, $id, $where = [])
    {
        global $wpdb;

        $table = "{$wpdb->base_prefix}videos";

        $where['id'] = $id;
        $where['deleted_at'] = null;

        $wpdb->update($table, $data, $where);

        if($wpdb->last_error){
            wp_die($wpdb->last_error);
        }

        return self::find($id);
    }

    /**
     * @param $course_id
     * @return array|object|null
     */
    public static function getAllFromCourse($course_id)
    {
        global $wpdb;

        $table = "{$wpdb->base_prefix}videos";

        $videos = $wpdb->get_results(
            $wpdb->prepare("
                SELECT * 
                FROM $table 
                WHERE 
                    course_id = %d 
                    AND user_id = %d
                    AND deleted_at is null"
                , $course_id
                , get_current_user_id()
            )
        );

        if($wpdb->last_error){
            error_log($wpdb->last_error);
        }

        return $videos;
    }

    /**
     * @param $id
     * @param array $where
     * @return bool|int
     */
    public static function delete($id, $where = [])
    {
        global $wpdb;

        $table = "{$wpdb->base_prefix}videos";

        $where['id'] = $id;

        return $wpdb->delete($table, $where);
    }

    public static function softDelete($id, $where = [])
    {
        $data['deleted_at'] = date('Y-m-d H:i:s');

        self::update($data, $id, $where);

        return self::findWithTrashed($id);
    }
}