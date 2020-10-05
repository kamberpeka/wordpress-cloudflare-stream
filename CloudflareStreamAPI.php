<?php

class CloudflareStreamAPI
{
    /**
     * API Email
     *
     * @var string $api_email Cloudflare API email address.
     */
    private $api_email = '';

    /**
     * Last video seen when retrieving paginated results.
     *
     * @var string $last_seen Timestamp of the last returned result.
     */
    public $last_seen = false;

    /**
     * API Key
     *
     * @var string $api_email Cloudflare API key.
     */
    private $api_key = '';

    /**
     * API Account ID
     *
     * @var string $api_email Cloudflare API account ID.
     */
    private $api_account = '';

    /**
     * REST API limit
     *
     * @var string $api_limit Number of results to return from the API by default.
     */
    public $api_limit = 40;

    /**
     * Make the request to the API
     *
     * @param string $endpoint API Endpoint.
     * @param array  $args Additional API arguments.
     * @param bool   $return_headers Return the response headers intead of the response body.
     * @since 1.0.0
     */
    public function request( $endpoint, $args = array(), $return_headers = false )
    {
        $this->api_email   = get_option( Cloudflare_Stream_Settings::OPTION_API_EMAIL );
        $this->api_key     = get_option( Cloudflare_Stream_Settings::OPTION_API_KEY );
        $this->api_account = get_option( Cloudflare_Stream_Settings::OPTION_API_ACCOUNT );

        if ( isset( $args['method'] ) ) {
            $method = $args['method'];
        } else {
            $method = 'GET';
        }

        $query_string = isset( $args['query'] ) ? '?' . $args['query'] : '';
        $endpoint    .= $query_string;
        $base_url     = 'https://api.cloudflare.com/client/v4/accounts/' . $this->api_account . $endpoint;
//        $route        = $base_url . $endpoint;

        $args['headers'] = array(
            'X-Auth-Email' => $this->api_email,
            'X-Auth-Key'   => $this->api_key,
            'Content-Type' => 'application/json',
        );

        // Get remote HTML file.
        $response = wp_remote_request( $base_url, $args );

        // Check for error.
        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        } elseif ( 'headers' === $return_headers ) {
            return wp_remote_retrieve_headers( $response );
        }
        return wp_remote_retrieve_body( $response );
    }

    public function move( $args = array(), $return_headers = true ) {

        $args['method'] = 'POST';

        return $this->request( '/stream/copy/', $args, $return_headers );
    }

    public function status( $args = array(), $return_headers = true ) {

        $args['method'] = 'GET';

        return $this->request( '/stream/' . $args['uid'], $args, $return_headers );
    }

    /**
     * Make a DELETE request
     *
     * @param array  $args Additional API arguments.
     * @param bool   $return_headers Return the response headers intead of the response body.
     * @since 1.0.0
     *
     * @returns object $response HTTP response object.
     */
    public function delete( $args = array(), $return_headers = false )
    {
        $args['method'] = 'DELETE';

        return $this->request( '/stream/' . $args['uid'], $args, $return_headers );
    }

    public static function responseToArray ($response, &$data)
    {
        $data['cf_response'] = $response;
        $response = json_decode($response);

        $result = $response->result;

        error_log(print_r($response, true));

        if($response->success == true) {

            $data['cf_uid'] = $result->uid;
            $data['thumbnail'] = $result->thumbnail;
            $data['ready_to_stream'] = $result->readyToStream;
            $data['duration'] = $result->duration;
            $data['cf_url'] = $result->preview;
            $data['cf_status'] = $result->status->state;
            $data['cf_progress'] = $result->status->pctComplete ?: 0;
        } else {

            $data['ready_to_stream'] = 0;
            $data['cf_status'] = 'error';
        }
    }
}