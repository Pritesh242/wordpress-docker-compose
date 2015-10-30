<?php

namespace VersionPress\Api\BundledWpApi;

class WP_HTTP_Response implements WP_HTTP_ResponseInterface {
    

    public $data;
    

    public $headers;
    

    public $status;
    

    public function __construct( $data = null, $status = 200, $headers = array() ) {
        $this->data = $data;
        $this->set_status( $status );
        $this->set_headers( $headers );
    }

    public function get_headers() {
        return $this->headers;
    }

    public function set_headers( $headers ) {
        $this->headers = $headers;
    }

    public function header( $key, $value, $replace = true ) {
        if ( $replace || ! isset( $this->headers[ $key ] ) ) {
            $this->headers[ $key ] = $value;
        } else {
            $this->headers[ $key ] .= ', ' . $value;
        }
    }

    public function get_status() {
        return $this->status;
    }

    public function set_status( $code ) {
        $this->status = absint( $code );
    }

    public function get_data() {
        return $this->data;
    }

    public function set_data( $data ) {
        $this->data = $data;
    }

    public function jsonSerialize() {
        
        return $this->get_data();
    }
}
