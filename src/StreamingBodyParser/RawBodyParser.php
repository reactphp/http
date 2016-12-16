<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use InvalidArgumentException;
use React\Http\Request;
use React\Promise\ExtendedPromiseInterface;

/**
 * @internal
 */
final class RawBodyParser implements ParserInterface
{
    use EventEmitterTrait;

    /**
     * @var Request
     */
    private $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
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

    /**
     * Cancel 'parsing' the request body
     */
    public function cancel()
    {
        $this->request->removeListener('data', [$this, 'body']);
        $this->request->removeListener('end', [$this, 'end']);
        $this->emit('end');
    }
}
