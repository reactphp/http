<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

class RawBody implements StreamingParserInterface
{
    use EventEmitterTrait;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $headers = $request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);

        ContentLengthBufferedSink::createPromise(
            $request,
            $headers['content-length']
        )->then([$this, 'finish']);
    }

    /**
     * @param string $buffer
     */
    public function finish($buffer)
    {
        $this->emit('body', [$buffer]);
        $this->emit('end');
    }
}
