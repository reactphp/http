<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;
use React\Promise\ExtendedPromiseInterface;

class RawBodyParser implements ParserInterface
{
    use EventEmitterTrait;

    /**
     * @var ExtendedPromiseInterface
     */
    private $promise;

    /**
     * @param Request $request
     * @return ParserInterface
     */
    public static function create(Request $request)
    {
        return new static($request);
    }

    /**
     * @param Request $request
     */
    private function __construct(Request $request)
    {
        $headers = $request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);

        $this->promise = ContentLengthBufferedSink::createPromise(
            $request,
            $headers['content-length']
        )->done([$this, 'finish']);
    }

    /**
     * @param string $buffer
     *
     * @internal
     */
    public function finish($buffer)
    {
        $this->emit('body', [$buffer]);
        $this->emit('end');
    }

    public function cancel()
    {
        $this->promise->cancel();
    }
}
