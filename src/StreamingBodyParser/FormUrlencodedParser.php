<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

class FormUrlencodedParser implements ParserInterface
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
        parse_str(trim($buffer), $result);
        foreach ($result as $key => $value) {
            $this->emit('post', [$key, $value]);
        }
        $this->emit('end');
    }
}
