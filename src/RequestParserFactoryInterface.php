<?php namespace React\Http;

interface RequestParserFactoryInterface
{
    /**
     * @return RequestParserInterface
     */
    public function create();
}