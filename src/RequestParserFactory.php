<?php namespace React\Http;

class RequestParserFactory implements RequestParserFactoryInterface
{
    /**
     * @return RequestParser
     */
    public function create()
    {
        return new RequestParser();
    }
}