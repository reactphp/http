<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use InvalidArgumentException;
use React\Http\Request;
use React\Promise\ExtendedPromiseInterface;

/**
 * @internal
 */
class RawBodyParser implements ParserInterface
{
    use EventEmitterTrait;

    /**
     * @var Request
     */
    private $request;

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
        $this->request = $request;
        $this->request->on('data', [$this, 'body']);
        $this->request->on('end', [$this, 'end']);
    }

    /**
     * @param string $data
     *
     * @internal
     */
    public function body($data)
    {
        $this->emit('body', [$data]);
    }

    /**
     * @internal
     */
    public function end()
    {
        $this->emit('end');
    }

    public function cancel()
    {
        $this->request->removeListener('data', [$this, 'body']);
        $this->request->removeListener('end', [$this, 'end']);
        $this->emit('end');
    }
}
