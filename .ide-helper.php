<?php

namespace {
    /**
     * @mixin \Illuminate\Foundation\Testing\TestCase
     */
    class TestCase
    {
        /**
         * Visit the given URI with a JSON request.
         *
         * @param string $method
         * @param string $uri
         * @param array $data
         * @param array $headers
         * @return \Illuminate\Testing\TestResponse
         */
        public function json($method, $uri, array $data = [], array $headers = [])
        {
            return new \Illuminate\Testing\TestResponse;
        }

        /**
         * Visit the given URI with a POST request.
         *
         * @param string $uri
         * @param array $data
         * @param array $headers
         * @return \Illuminate\Testing\TestResponse
         */
        public function post($uri, array $data = [], array $headers = [])
        {
            return new \Illuminate\Testing\TestResponse;
        }

        /**
         * Visit the given URI with a JSON POST request.
         *
         * @param string $uri
         * @param array $data
         * @param array $headers
         * @return \Illuminate\Testing\TestResponse
         */
        public function postJson($uri, array $data = [], array $headers = [])
        {
            return new \Illuminate\Testing\TestResponse;
        }

        /**
         * Visit the given URI with a GET request.
         *
         * @param string $uri
         * @param array $headers
         * @return \Illuminate\Testing\TestResponse
         */
        public function get($uri, array $headers = [])
        {
            return new \Illuminate\Testing\TestResponse;
        }

        /**
         * Visit the given URI with a JSON GET request.
         *
         * @param string $uri
         * @param array $headers
         * @return \Illuminate\Testing\TestResponse
         */
        public function getJson($uri, array $headers = [])
        {
            return new \Illuminate\Testing\TestResponse;
        }

        /**
         * Set the authentication headers for the request.
         *
         * @param string $token
         * @param string $type
         * @return $this
         */
        public function withToken($token, $type = 'Bearer')
        {
            return $this;
        }
    }
}
