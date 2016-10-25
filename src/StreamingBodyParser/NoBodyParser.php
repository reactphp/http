<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

/**
 * @internal
 */
class NoBodyParser implements ParserInterface
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
        $this->request->on('end', [$this, 'end']);
    }

    public function cancel()
    {
        $this->request->removeListener('end', [$this, 'end']);
        $this->emit('end');
    }

    /**
     * @internal
     */
    public function end()
    {
        $this->emit('end');
    }
}
