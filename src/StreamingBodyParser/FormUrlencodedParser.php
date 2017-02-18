<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;
use React\Promise\CancellablePromiseInterface;

class FormUrlencodedParser implements ParserInterface
{
    use EventEmitterTrait;

    /**
     * @var CancellablePromiseInterface
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

    public function cancel()
    {
        $this->promise->cancel();
    }
}
