<?php

class Latipay_RestClient
{

    private $timeout = 30;
    private $url;
    private $headers = array();
    private $post = false;
    private $data = array();

    public function execute( $args = array() )
    {
        $query = array(
            'headers' => $this->headers,
            'timeout' => $this->timeout,
            'sslverify' => false,
        );

        $args = array_merge( $args, $query );
        if ( $this->post ) {
            $args['body'] = $this->data;
            $response = wp_remote_post( $this->url, $args );
        } else {
            $response = wp_remote_get( $this->url, $args );
        }
        return wp_remote_retrieve_body( $response );
    }

    public function url($url)
    {
        $this->url = $url;
        return $this;
    }

    public function headers($headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function post($data = array())
    {
        $this->post = true;
        if ( $data ) {
            $this->data = $data;
        }
        return $this->execute();
    }

    public function postJson($data = array())
    {
        $this->post = true;
        $this->headers['Content-Type'] = 'application/json';
        $this->data = wp_json_encode( $data );
        return $this->execute();
    }

    public function get()
    {
        return $this->execute();
    }

}
